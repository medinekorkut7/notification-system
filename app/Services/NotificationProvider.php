<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Services\NotificationSettings;

class NotificationProvider
{
    public function __construct(private NotificationSettings $settings)
    {
    }

    public function send(array $payload, ?string $correlationId = null): array
    {
        $webhookUrl = $this->settings->get('provider_webhook_url', config('notifications.provider.webhook_url'));
        $fallbackUrl = $this->settings->get('provider_fallback_webhook_url', config('notifications.provider.fallback_webhook_url'));
        $idempotencyHeader = config('notifications.provider.idempotency_header', 'X-Idempotency-Key');
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        $traceparent = $payload['traceparent'] ?? null;

        if (!$webhookUrl) {
            throw new \RuntimeException('Notification provider webhook URL is not configured.');
        }

        $response = null;
        $primaryHealthy = $this->primaryHealthy();
        $shouldUseFallbackFirst = !$primaryHealthy && !empty($fallbackUrl);

        if (!$shouldUseFallbackFirst) {
            try {
                $response = Http::timeout(config('notifications.provider.timeout_seconds'))
                    ->withHeaders([
                        'X-Correlation-Id' => $correlationId ?? Str::uuid()->toString(),
                        $idempotencyHeader => $idempotencyKey,
                        'traceparent' => $traceparent,
                    ])
                    ->post($webhookUrl, $payload);
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
            $response = Http::timeout(config('notifications.provider.timeout_seconds'))
                ->withHeaders([
                    'X-Correlation-Id' => $correlationId ?? Str::uuid()->toString(),
                    $idempotencyHeader => $idempotencyKey,
                    'traceparent' => $traceparent,
                ])
                ->post($fallbackUrl, $payload);

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

    private function primaryHealthy(): bool
    {
        return !Redis::exists($this->primaryUnhealthyKey());
    }

    private function recordPrimarySuccess(): void
    {
        Redis::del($this->primaryFailuresKey());
        Redis::del($this->primaryUnhealthyKey());
    }

    private function recordPrimaryFailure(): void
    {
        $threshold = (int) config('notifications.provider.health_failure_threshold', 3);
        $window = (int) config('notifications.provider.health_window_seconds', 60);
        $openSeconds = (int) config('notifications.provider.health_open_seconds', 60);

        $key = $this->primaryFailuresKey();
        $count = Redis::incr($key);
        Redis::expire($key, $window);

        if ($count >= $threshold) {
            Redis::setex($this->primaryUnhealthyKey(), $openSeconds, '1');
            Redis::del($key);
        }
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
