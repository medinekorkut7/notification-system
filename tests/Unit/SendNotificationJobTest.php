<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Jobs\DeadLetterNotificationJob;
use App\Models\Notification;
use App\Services\CircuitBreaker;
use App\Services\NotificationProvider;
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
}
