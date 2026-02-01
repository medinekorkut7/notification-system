<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationAttempt;
use App\Jobs\DeadLetterNotificationJob;
use App\Enums\NotificationAttemptStatus;
use App\Enums\NotificationStatus;
use App\Services\CircuitBreaker;
use App\Services\ErrorClassifier;
use App\Services\NotificationProvider;
use App\Services\RetryPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class SendNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 5;

    /**
     * Cached config value to avoid repeated lookups.
     */
    private int $rateLimit;

    public function __construct(public string $notificationId)
    {
    }

    public function handle(): void
    {
        // Cache config value once
        $this->rateLimit = (int) config('notifications.rate_limits.per_channel_per_second', 100);

        // Early exit check
        if ($this->isProcessingPaused()) {
            $this->release(10);
            return;
        }

        $notification = Notification::query()->find($this->notificationId);
        if (!$notification) {
            return;
        }

        if (!$this->shouldProcess($notification)) {
            return;
        }

        $notification->status = NotificationStatus::Processing->value;
        $notification->processing_started_at = $notification->processing_started_at ?? now();
        $notification->save();

        Log::withContext(['correlation_id' => $notification->correlation_id]);

        $channel = $notification->channel;
        $breaker = app(CircuitBreaker::class);
        $retryPolicy = app(RetryPolicy::class);

        if (!$breaker->allow($channel)) {
            $openDelay = $retryPolicy->circuitBreakerOpenDelay();
            $notification->status = NotificationStatus::Retrying->value;
            $notification->last_retry_at = now();
            $notification->next_retry_at = now()->addSeconds($openDelay);
            $notification->save();
            $this->release($openDelay);
            return;
        }

        Redis::throttle("notifications:channel:{$channel}")
            ->allow($this->rateLimit)
            ->every(1)
            ->then(function () use ($notification) {
                $this->sendNotification($notification);
            }, function () {
                $this->release(1);
            });
    }

    private function isProcessingPaused(): bool
    {
        try {
            return (bool) Redis::exists('notifications:processing:paused');
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function shouldProcess(Notification $notification): bool
    {
        if (in_array($notification->status, [
            NotificationStatus::Sent->value,
            NotificationStatus::Failed->value,
            NotificationStatus::Cancelled->value,
        ], true)) {
            return false;
        }

        if ($this->isProcessingStillFresh($notification)) {
            return false;
        }

        if ($notification->scheduled_at && $notification->scheduled_at->isFuture()) {
            $this->release(30);
            return false;
        }

        if ($this->isExpired($notification)) {
            $this->markExpired($notification);
            return false;
        }

        if ($this->shouldWaitForRetry($notification)) {
            return false;
        }

        return true;
    }

    private function isProcessingStillFresh(Notification $notification): bool
    {
        if ($notification->status !== NotificationStatus::Processing->value || !$notification->processing_started_at) {
            return false;
        }

        $processingTimeout = (int) config('notifications.retry.processing_timeout_seconds', 300);
        return $notification->processing_started_at->diffInSeconds(now()) < $processingTimeout;
    }

    private function isExpired(Notification $notification): bool
    {
        $ttlHours = (int) config('notifications.retry.delivery_ttl_hours', 24);
        return $notification->created_at && $notification->created_at->lt(now()->subHours($ttlHours));
    }

    private function markExpired(Notification $notification): void
    {
        $notification->status = NotificationStatus::Failed->value;
        $notification->last_error = 'Notification expired before delivery.';
        $notification->error_type = 'expired';
        $notification->error_code = 'expired';
        $notification->save();
    }

    private function shouldWaitForRetry(Notification $notification): bool
    {
        if ($notification->status !== NotificationStatus::Retrying->value || !$notification->next_retry_at) {
            return false;
        }

        if (!$notification->next_retry_at->isFuture()) {
            return false;
        }

        $delay = $notification->next_retry_at->diffInSeconds(now());
        $this->release(max(1, $delay));
        return true;
    }

    private function sendNotification(Notification $notification): void
    {
        $start = microtime(true);
        $attemptNumber = $notification->attempts + 1;

        $payload = [
            'to' => $notification->recipient,
            'channel' => $notification->channel,
            'content' => $notification->content,
            'idempotency_key' => $notification->idempotency_key ?? $notification->id,
            'traceparent' => ($notification->trace_id && $notification->span_id)
                ? "00-{$notification->trace_id}-{$notification->span_id}-01"
                : null,
        ];

        $attempt = NotificationAttempt::create([
            'notification_id' => $notification->id,
            'attempt_number' => $attemptNumber,
            'status' => NotificationAttemptStatus::Sending->value,
            'request_payload' => $payload,
        ]);

        try {
            $provider = app(NotificationProvider::class);
            $classifier = app(ErrorClassifier::class);
            $retryPolicy = app(RetryPolicy::class);
            $breaker = app(CircuitBreaker::class);
            $correlationId = $notification->correlation_id ?? Str::uuid()->toString();
            $result = $provider->send($payload, $correlationId);

            $breaker->recordSuccess($notification->channel);

            DB::transaction(function () use ($notification, $attempt, $result, $attemptNumber, $start) {
                $notification->status = NotificationStatus::Sent->value;
                $notification->sent_at = now();
                $notification->provider_message_id = data_get($result, 'body.messageId');
                $notification->provider_response = $result['body'] ?? null;
                $notification->last_error = null;
                $notification->error_type = null;
                $notification->error_code = null;
                $notification->last_retry_at = null;
                $notification->next_retry_at = null;
                $notification->attempts = $attemptNumber;
                $notification->save();

                $attempt->status = NotificationAttemptStatus::Sent->value;
                $attempt->response_payload = $result['body'] ?? null;
                $attempt->duration_ms = (int) ((microtime(true) - $start) * 1000);
                $attempt->save();
            });
        } catch (\Throwable $exception) {
            $httpStatus = $exception->getCode() ?: null;
            [$errorType, $errorCode] = $classifier->classify(is_numeric($httpStatus) ? (int) $httpStatus : null, $exception);
            $breaker = app(CircuitBreaker::class);

            $notification->attempts = $attemptNumber;
            $notification->last_error = $exception->getMessage();
            $notification->error_type = $errorType;
            $notification->error_code = $errorCode;

            if ($errorType !== 'permanent') {
                $breaker->recordFailure($notification->channel);
            }

            if ($errorType === 'permanent' || $attemptNumber >= $notification->max_attempts) {
                DB::transaction(function () use ($notification, $attempt, $exception, $errorType, $errorCode, $httpStatus, $start) {
                    $notification->status = NotificationStatus::Failed->value;
                    $notification->save();

                    $attempt->status = NotificationAttemptStatus::Failed->value;
                    $attempt->error_message = $exception->getMessage();
                    $attempt->error_type = $errorType;
                    $attempt->error_code = $errorCode;
                    $attempt->http_status = is_numeric($httpStatus) ? (int) $httpStatus : null;
                    $attempt->duration_ms = (int) ((microtime(true) - $start) * 1000);
                    $attempt->save();
                });

                Log::error('Notification delivery failed permanently', [
                    'notification_id' => $notification->id,
                    'error' => $exception->getMessage(),
                    'error_type' => $errorType,
                    'error_code' => $errorCode,
                ]);

                dispatch((new DeadLetterNotificationJob($notification->id))
                    ->onQueue(config('notifications.queue_names.dead', 'notifications-dead')));

                return;
            }

            $delay = $retryPolicy->computeDelaySeconds($attemptNumber);
            DB::transaction(function () use ($notification, $attempt, $exception, $errorType, $errorCode, $httpStatus, $start, $delay) {
                $notification->status = NotificationStatus::Retrying->value;
                $notification->last_retry_at = now();
                $notification->next_retry_at = now()->addSeconds($delay);
                $notification->save();

                $attempt->status = NotificationAttemptStatus::Failed->value;
                $attempt->error_message = $exception->getMessage();
                $attempt->error_type = $errorType;
                $attempt->error_code = $errorCode;
                $attempt->http_status = is_numeric($httpStatus) ? (int) $httpStatus : null;
                $attempt->duration_ms = (int) ((microtime(true) - $start) * 1000);
                $attempt->save();
            });

            $this->release($delay);
        }
    }
}
