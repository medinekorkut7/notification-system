<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Jobs\DeadLetterNotificationJob;
use App\Models\Notification;
use App\Services\CircuitBreaker;
use App\Services\NotificationProvider;
use App\Services\RetryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_uses_rate_limiting_and_marks_sent(): void
    {
        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Test',
            'status' => 'pending',
        ]);

        app()->instance(CircuitBreaker::class, new class {
            public function allow(string $channel): bool
            {
                return true;
            }

            public function recordSuccess(string $channel): void
            {
            }

            public function recordFailure(string $channel): void
            {
            }
        });

        app()->instance(NotificationProvider::class, new class {
            public function send(array $payload, ?string $correlationId = null): array
            {
                return [
                    'status' => 202,
                    'body' => [
                        'messageId' => 'test-message-id',
                        'status' => 'accepted',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ];
            }
        });

        $throttle = Mockery::mock();
        $throttle->shouldReceive('allow')->once()->with(100)->andReturnSelf();
        $throttle->shouldReceive('every')->once()->with(1)->andReturnSelf();
        $throttle->shouldReceive('then')->once()->andReturnUsing(function ($success, $failure) {
            $success();
        });

        Redis::shouldReceive('throttle')
            ->once()
            ->with('notifications:channel:sms')
            ->andReturn($throttle);

        (new SendNotificationJob($notification->id))->handle();

        $notification->refresh();
        $this->assertSame('sent', $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertSame('test-message-id', $notification->provider_message_id);
    }

    public function test_permanent_error_sends_to_dead_letter_queue(): void
    {
        Queue::fake();

        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Test',
            'status' => 'pending',
        ]);

        app()->instance(CircuitBreaker::class, new class {
            public function allow(string $channel): bool
            {
                return true;
            }

            public function recordSuccess(string $channel): void
            {
            }

            public function recordFailure(string $channel): void
            {
            }
        });

        app()->instance(NotificationProvider::class, new class {
            public function send(array $payload, ?string $correlationId = null): array
            {
                throw new \RuntimeException('Bad request', 400);
            }
        });

        $throttle = Mockery::mock();
        $throttle->shouldReceive('allow')->once()->with(100)->andReturnSelf();
        $throttle->shouldReceive('every')->once()->with(1)->andReturnSelf();
        $throttle->shouldReceive('then')->once()->andReturnUsing(function ($success, $failure) {
            $success();
        });

        Redis::shouldReceive('throttle')
            ->once()
            ->with('notifications:channel:sms')
            ->andReturn($throttle);

        (new SendNotificationJob($notification->id))->handle();

        Queue::assertPushedOn('notifications-dead', DeadLetterNotificationJob::class);
    }

    public function test_job_marks_expired_notifications_as_failed(): void
    {
        Redis::shouldReceive('exists')->andReturn(false);
        Redis::shouldReceive('throttle')->never();

        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Expired',
            'status' => 'pending',
        ]);
        $notification->created_at = now()->subHours(48);
        $notification->save();

        (new SendNotificationJob($notification->id))->handle();

        $notification->refresh();
        $this->assertSame('failed', $notification->status);
        $this->assertSame('expired', $notification->error_type);
        $this->assertSame('expired', $notification->error_code);
        $this->assertSame('Notification expired before delivery.', $notification->last_error);
    }

    public function test_job_skips_recent_processing_notifications(): void
    {
        Redis::shouldReceive('exists')->andReturn(false);
        Redis::shouldReceive('throttle')->never();

        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Processing',
            'status' => 'processing',
            'processing_started_at' => now(),
        ]);

        (new SendNotificationJob($notification->id))->handle();

        $notification->refresh();
        $this->assertSame('processing', $notification->status);
    }

    public function test_job_retries_when_circuit_breaker_is_open(): void
    {
        Redis::shouldReceive('exists')->andReturn(false);
        Redis::shouldReceive('throttle')->never();

        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Breaker',
            'status' => 'pending',
        ]);

        app()->instance(CircuitBreaker::class, new class {
            public function allow(string $channel): bool
            {
                return false;
            }

            public function recordSuccess(string $channel): void
            {
            }

            public function recordFailure(string $channel): void
            {
            }
        });

        app()->instance(RetryPolicy::class, new class {
            public function computeDelaySeconds(int $attemptNumber): int
            {
                return 5;
            }

            public function circuitBreakerOpenDelay(): int
            {
                return 15;
            }
        });

        $job = new class($notification->id) extends SendNotificationJob {
            public ?int $releasedDelay = null;

            public function release($delay = 0): void
            {
                $this->releasedDelay = (int) $delay;
            }
        };

        $job->handle();

        $notification->refresh();
        $this->assertSame('retrying', $notification->status);
        $this->assertNotNull($notification->next_retry_at);
        $this->assertSame(15, $job->releasedDelay);
    }

    public function test_job_releases_scheduled_notifications(): void
    {
        Redis::shouldReceive('exists')->andReturn(false);
        Redis::shouldReceive('throttle')->never();

        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Scheduled',
            'status' => 'pending',
            'scheduled_at' => now()->addMinutes(5),
        ]);

        $job = new class($notification->id) extends SendNotificationJob {
            public ?int $releasedDelay = null;

            public function release($delay = 0): void
            {
                $this->releasedDelay = (int) $delay;
            }
        };

        $job->handle();

        $notification->refresh();
        $this->assertSame('pending', $notification->status);
        $this->assertSame(30, $job->releasedDelay);
    }

    public function test_job_retries_transient_provider_errors(): void
    {
        Redis::shouldReceive('exists')->andReturn(false);

        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Retry',
            'status' => 'pending',
        ]);

        app()->instance(CircuitBreaker::class, new class {
            public function allow(string $channel): bool
            {
                return true;
            }

            public function recordSuccess(string $channel): void
            {
            }

            public function recordFailure(string $channel): void
            {
            }
        });

        app()->instance(NotificationProvider::class, new class {
            public function send(array $payload, ?string $correlationId = null): array
            {
                throw new \RuntimeException('Service unavailable', 500);
            }
        });

        app()->instance(RetryPolicy::class, new class {
            public function computeDelaySeconds(int $attemptNumber): int
            {
                return 7;
            }

            public function circuitBreakerOpenDelay(): int
            {
                return 30;
            }
        });

        $throttle = Mockery::mock();
        $throttle->shouldReceive('allow')->once()->with(100)->andReturnSelf();
        $throttle->shouldReceive('every')->once()->with(1)->andReturnSelf();
        $throttle->shouldReceive('then')->once()->andReturnUsing(function ($success, $failure) {
            $success();
        });

        Redis::shouldReceive('throttle')
            ->once()
            ->with('notifications:channel:sms')
            ->andReturn($throttle);

        $job = new class($notification->id) extends SendNotificationJob {
            public ?int $releasedDelay = null;

            public function release($delay = 0): void
            {
                $this->releasedDelay = (int) $delay;
            }
        };

        $job->handle();

        $notification->refresh();
        $this->assertSame('retrying', $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertSame('transient', $notification->error_type);
        $this->assertSame('http_500', $notification->error_code);
        $this->assertNotNull($notification->next_retry_at);
        $this->assertSame(7, $job->releasedDelay);
    }
}
