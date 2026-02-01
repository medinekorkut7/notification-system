<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\DeadLetterNotification;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeadLetterNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_channel(): void
    {
        $sms = DeadLetterNotification::create([
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => ['to' => '+905551234567', 'channel' => 'sms'],
        ]);

        $email = DeadLetterNotification::create([
            'channel' => 'email',
            'recipient' => 'user@example.com',
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => ['to' => 'user@example.com', 'channel' => 'email'],
        ]);

        $this->getJson('/api/v1/dead-letter?channel=sms&per_page=10')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $sms->id])
            ->assertJsonMissing(['id' => $email->id]);
    }

    public function test_show_returns_dead_letter(): void
    {
        $dead = DeadLetterNotification::create([
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => ['to' => '+905551234567', 'channel' => 'sms'],
        ]);

        $this->getJson("/api/v1/dead-letter/{$dead->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $dead->id]);
    }

    public function test_requeue_rejects_invalid_priority(): void
    {
        $dead = DeadLetterNotification::create([
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => ['to' => '+905551234567', 'channel' => 'sms'],
        ]);

        $this->postJson("/api/v1/dead-letter/{$dead->id}/requeue", [
            'priority' => 'bad',
        ])->assertStatus(422)
            ->assertJson(['message' => 'Invalid priority.']);
    }

    public function test_requeue_creates_notification_and_dispatches_job(): void
    {
        Queue::fake();

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
            ],
        ]);

        $response = $this->postJson("/api/v1/dead-letter/{$dead->id}/requeue", [
            'priority' => 'high',
        ]);

        $response->assertStatus(201);

        $notification = Notification::query()->first();
        $this->assertNotNull($notification);
        $this->assertSame('high', $notification->priority);

        Queue::assertPushedOn('notifications-high', SendNotificationJob::class);

        $response->assertJsonFragment(['notification_id' => $notification->id]);
    }

    public function test_requeue_all_rejects_invalid_priority(): void
    {
        $this->postJson('/api/v1/dead-letter/requeue', [
            'priority' => 'bad',
        ])->assertStatus(422)
            ->assertJson(['message' => 'Invalid priority.']);
    }

    public function test_requeue_all_skips_invalid_items(): void
    {
        Queue::fake();

        DeadLetterNotification::create([
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

        DeadLetterNotification::create([
            'channel' => null,
            'recipient' => null,
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => [],
        ]);

        $this->postJson('/api/v1/dead-letter/requeue', [
            'limit' => 10,
            'priority' => 'normal',
        ])->assertStatus(200)
            ->assertJson([
                'requeued' => 1,
                'skipped' => 1,
            ]);

        $this->assertSame(1, Notification::count());
        Queue::assertPushedOn('notifications-normal', SendNotificationJob::class);
    }
}
