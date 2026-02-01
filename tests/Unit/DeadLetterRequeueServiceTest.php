<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Models\DeadLetterNotification;
use App\Models\Notification;
use App\Services\DeadLetterRequeueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeadLetterRequeueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_valid_priority(): void
    {
        $service = new DeadLetterRequeueService();

        $this->assertTrue($service->isValidPriority('high'));
        $this->assertFalse($service->isValidPriority('bad'));
    }

    public function test_requeue_single_returns_error_when_data_missing(): void
    {
        $service = new DeadLetterRequeueService();

        $dead = DeadLetterNotification::create([
            'channel' => null,
            'recipient' => null,
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => [],
        ]);

        $result = $service->requeueSingle($dead);

        $this->assertFalse($result['ok']);
        $this->assertSame('Dead-letter item is missing recipient or channel.', $result['message']);
        $this->assertSame(0, Notification::count());
    }

    public function test_requeue_single_creates_notification_and_dispatches(): void
    {
        Queue::fake();

        $service = new DeadLetterRequeueService();

        $dead = DeadLetterNotification::create([
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => [
                'to' => '+905551234567',
                'channel' => 'sms',
                'content' => 'Retry',
                'idempotency_key' => 'idem-123',
            ],
        ]);

        $result = $service->requeueSingle($dead, 10, 'high');

        $this->assertTrue($result['ok']);
        $notification = Notification::query()->first();
        $this->assertNotNull($notification);
        $this->assertSame('high', $notification->priority);
        $this->assertStringContainsString('idem-123-requeue-', (string) $notification->idempotency_key);

        Queue::assertPushedOn('notifications-high', SendNotificationJob::class);
    }

    public function test_requeue_batch_skips_invalid_entries(): void
    {
        Queue::fake();

        $service = new DeadLetterRequeueService();

        $valid = DeadLetterNotification::create([
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => [
                'to' => '+905551234567',
                'channel' => 'sms',
                'content' => 'Retry',
            ],
        ]);

        $invalid = DeadLetterNotification::create([
            'channel' => null,
            'recipient' => null,
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => [],
        ]);

        $result = $service->requeueBatch(collect([$valid, $invalid]), 0, 'normal');

        $this->assertSame(1, $result['requeued']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Notification::count());
        Queue::assertPushedOn('notifications-normal', SendNotificationJob::class);
    }
}
