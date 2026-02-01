<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Services\NotificationSettings;

class NotificationProvider
{
    /**
     * Lua script for atomic failure recording with threshold check.
     */
    private const LUA_RECORD_FAILURE = <<<'LUA'
        local count = redis.call('INCR', KEYS[1])
        redis.call('EXPIRE', KEYS[1], ARGV[1])
        if tonumber(count) >= tonumber(ARGV[2]) then
            redis.call('SETEX', KEYS[2], ARGV[3], '1')
            redis.call('DEL', KEYS[1])
        end
        return count
    LUA;

    /**
     * Cached config values.
     */
    private int $timeout;
    private string $idempotencyHeader;
    private int $healthFailureThreshold;
    private int $healthWindowSeconds;
    private int $healthOpenSeconds;

    public function __construct(private NotificationSettings $settings)
    {
        // Cache config values once on construction
        $this->timeout = (int) config('notifications.provider.timeout_seconds', 5);
        $this->idempotencyHeader = config('notifications.provider.idempotency_header', 'X-Idempotency-Key');
        $this->healthFailureThreshold = (int) config('notifications.provider.health_failure_threshold', 3);
        $this->healthWindowSeconds = (int) config('notifications.provider.health_window_seconds', 60);
        $this->healthOpenSeconds = (int) config('notifications.provider.health_open_seconds', 60);
    }

    public function send(array $payload, ?string $correlationId = null): array
    {
        $webhookUrl = $this->settings->get('provider_webhook_url', config('notifications.provider.webhook_url'));
        $fallbackUrl = $this->settings->get('provider_fallback_webhook_url', config('notifications.provider.fallback_webhook_url'));
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        $traceparent = $payload['traceparent'] ?? null;

        if (!$webhookUrl) {
            throw new \RuntimeException('Notification provider webhook URL is not configured.');
        }

        $headers = [
            'X-Correlation-Id' => $correlationId ?? Str::uuid()->toString(),
            $this->idempotencyHeader => $idempotencyKey,
            'traceparent' => $traceparent,
        ];

        $response = null;
        $primaryHealthy = $this->primaryHealthy();
        $shouldUseFallbackFirst = !$primaryHealthy && !empty($fallbackUrl);

        if (!$shouldUseFallbackFirst) {
            try {
                $response = $this->makeRequest($webhookUrl, $payload, $headers);
            } catch (\Throwable $exception) {
                $this->recordPrimaryFailure();
                throw $exception;
            }

            Log::info('Notification provider response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                $this->recordPrimarySuccess();
            } else {
                $this->recordPrimaryFailure();
            }
        }

        if ((!$response || !$response->successful()) && $fallbackUrl) {
            $response = $this->makeRequest($fallbackUrl, $payload, $headers);

            Log::info('Notification provider fallback response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        if (!$response->successful()) {
            throw new \RuntimeException('Provider request failed with status ' . $response->status(), $response->status());
        }

        return [
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }

    /**
     * Make HTTP request with optimized client settings.
     */
    private function makeRequest(string $url, array $payload, array $headers): \Illuminate\Http\Client\Response
    {
        return Http::timeout($this->timeout)
            ->connectTimeout(3)
            ->withOptions([
                'http_errors' => false,
            ])
            ->withHeaders($headers)
            ->post($url, $payload);
    }

    private function primaryHealthy(): bool
    {
        return !Redis::exists($this->primaryUnhealthyKey());
    }

    private function recordPrimarySuccess(): void
    {
        // Pipeline multiple DEL operations into single round-trip
        Redis::pipeline(function ($pipe) {
            $pipe->del($this->primaryFailuresKey());
            $pipe->del($this->primaryUnhealthyKey());
        });
    }

    private function recordPrimaryFailure(): void
    {
        // Atomic failure recording using Lua script
        Redis::eval(
            self::LUA_RECORD_FAILURE,
            2,
            $this->primaryFailuresKey(),
            $this->primaryUnhealthyKey(),
            $this->healthWindowSeconds,
            $this->healthFailureThreshold,
            $this->healthOpenSeconds
        );
    }

    private function primaryFailuresKey(): string
    {
        return 'notifications:provider:primary:failures';
    }

    private function primaryUnhealthyKey(): string
    {
        return 'notifications:provider:primary:unhealthy';
    }
}
