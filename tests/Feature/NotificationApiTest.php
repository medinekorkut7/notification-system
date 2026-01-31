<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Jobs\DeadLetterNotificationJob;
use App\Models\Notification;
use App\Models\DeadLetterNotification;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use App\Events\NotificationStatusUpdated;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_notification_batch(): void
    {
        Queue::fake();

        $payload = [
            'batch' => [
                'idempotency_key' => 'batch-123',
                'metadata' => ['source' => 'test'],
            ],
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Hello world',
                    'priority' => 'high',
                ],
                [
                    'recipient' => 'user@example.com',
                    'channel' => 'email',
                    'content' => 'Welcome!',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/notifications', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['created' => 2])
            ->assertJsonStructure([
                'batch_id',
                'created',
                'duplicates',
                'notifications' => [
                    ['id', 'channel', 'priority', 'status'],
                ],
            ]);

        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_it_dispatches_jobs_to_priority_queues(): void
    {
        Queue::fake();

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'High priority',
                    'priority' => 'high',
                ],
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Low priority',
                    'priority' => 'low',
                ],
            ],
        ];

        $this->postJson('/api/v1/notifications', $payload)->assertStatus(201);

        Queue::assertPushedOn('notifications-high', SendNotificationJob::class);
        Queue::assertPushedOn('notifications-low', SendNotificationJob::class);
    }

    public function test_it_rejects_batches_over_1000(): void
    {
        $payload = [
            'notifications' => array_fill(0, 1001, [
                'recipient' => '+905551234567',
                'channel' => 'sms',
                'content' => 'Test',
                'priority' => 'normal',
            ]),
        ];

        $response = $this->postJson('/api/v1/notifications', $payload, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notifications']);
    }

    public function test_it_rejects_sms_content_over_limit(): void
    {
        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => str_repeat('a', 161),
                ],
            ],
        ];

        $this->postJson('/api/v1/notifications', $payload, [
            'Accept' => 'application/json',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['notifications.0.content']);
    }

    public function test_it_is_idempotent_per_notification_key(): void
    {
        Queue::fake();

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Idempotent',
                    'idempotency_key' => 'notif-1',
                ],
            ],
        ];

        $first = $this->postJson('/api/v1/notifications', $payload);
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/notifications', $payload);
        $second->assertStatus(201)
            ->assertJsonFragment(['created' => 0])
            ->assertJsonFragment(['duplicates' => 1]);
    }

    public function test_it_can_query_by_notification_and_batch_id(): void
    {
        Queue::fake();

        $payload = [
            'batch' => [
                'idempotency_key' => 'batch-verify-1.3',
            ],
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Step 1.3',
                    'priority' => 'high',
                ],
                [
                    'recipient' => 'user@example.com',
                    'channel' => 'email',
                    'content' => 'Step 1.3',
                ],
            ],
        ];

        $createResponse = $this->postJson('/api/v1/notifications', $payload);
        $createResponse->assertStatus(201);

        $batchId = $createResponse->json('batch_id');
        $firstNotificationId = $createResponse->json('notifications.0.id');

        $this->getJson("/api/v1/notifications/{$firstNotificationId}")
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $firstNotificationId,
                'batch_id' => $batchId,
            ]);

        $this->getJson("/api/v1/batches/{$batchId}")
            ->assertStatus(200)
            ->assertJsonFragment([
                'batch_id' => $batchId,
                'total_count' => 2,
            ]);
    }

    public function test_it_can_cancel_a_scheduled_notification(): void
    {
        Queue::fake();

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Step 1.4',
                    'priority' => 'high',
                    'scheduled_at' => now()->addDay()->toIso8601String(),
                ],
            ],
        ];

        $createResponse = $this->postJson('/api/v1/notifications', $payload);
        $createResponse->assertStatus(201);

        $notificationId = $createResponse->json('notifications.0.id');

        $this->postJson("/api/v1/notifications/{$notificationId}/cancel")
            ->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Notification cancelled.',
                'status' => 'cancelled',
            ]);
    }

    public function test_it_lists_notifications_with_filters_and_pagination(): void
    {
        Queue::fake();

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'List test',
                    'priority' => 'high',
                ],
                [
                    'recipient' => 'user@example.com',
                    'channel' => 'email',
                    'content' => 'List test',
                ],
            ],
        ];

        $createResponse = $this->postJson('/api/v1/notifications', $payload);
        $createResponse->assertStatus(201);

        $smsId = $createResponse->json('notifications.0.id');

        $listResponse = $this->getJson('/api/v1/notifications?channel=sms&per_page=1');
        $listResponse->assertStatus(200)
            ->assertJsonFragment(['id' => $smsId])
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_it_renders_templates_with_variables(): void
    {
        Queue::fake();

        $template = NotificationTemplate::create([
            'name' => 'welcome',
            'channel' => 'sms',
            'content' => 'Hello {{name}}',
            'default_variables' => ['name' => 'Guest'],
        ]);

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'template_id' => $template->id,
                    'variables' => ['name' => 'Ada'],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/notifications', $payload);
        $response->assertStatus(201)
            ->assertJsonFragment(['content' => 'Hello Ada']);
    }

    public function test_templates_can_be_updated_and_deleted(): void
    {
        $template = NotificationTemplate::create([
            'name' => 'promo',
            'channel' => 'email',
            'content' => 'Hello {{name}}',
            'default_variables' => ['name' => 'Guest'],
        ]);

        $this->patchJson("/api/v1/templates/{$template->id}", [
            'content' => 'Updated {{name}}',
        ])->assertStatus(200)
            ->assertJsonFragment(['content' => 'Updated {{name}}']);

        $this->deleteJson("/api/v1/templates/{$template->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['message' => 'Template deleted.']);
    }

    public function test_traceparent_is_returned_and_persisted(): void
    {
        Queue::fake();

        $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Trace test',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/notifications', $payload, [
            'traceparent' => $traceparent,
        ]);

        $response->assertStatus(201);
        $this->assertSame($traceparent, $response->headers->get('traceparent'));

        $notificationId = $response->json('notifications.0.id');
        $notification = Notification::query()->find($notificationId);

        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $notification->trace_id);
        $this->assertSame('00f067aa0ba902b7', $notification->span_id);
    }

    public function test_metrics_endpoint_returns_expected_shape(): void
    {
        Redis::shouldReceive('ping')->andReturn('PONG');
        Redis::shouldReceive('exists')->andReturn(0);
        Queue::shouldReceive('size')->andReturn(0);

        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Metrics test',
            'status' => 'sent',
        ]);
        $notification->created_at = now()->subSeconds(10);
        $notification->sent_at = now();
        $notification->save();

        $response = $this->getJson('/api/v1/metrics');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'queues' => ['high', 'normal', 'low', 'dead'],
                'status_counts',
                'dead_letter_count',
                'circuit_breaker',
                'avg_latency_seconds',
            ]);
    }

    public function test_prometheus_metrics_endpoint_returns_text_format(): void
    {
        Redis::shouldReceive('exists')->andReturn(0);
        Queue::shouldReceive('size')->andReturn(0);

        $response = $this->get('/api/v1/metrics/prometheus');

        $response->assertStatus(200);
        $this->assertStringContainsString('notification_queue_depth', $response->getContent());
        $this->assertStringContainsString('text/plain; version=0.0.4', $response->headers->get('Content-Type'));
    }

    public function test_api_key_is_required(): void
    {
        $this->defaultHeaders = [];

        $response = $this->getJson('/api/v1/metrics');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_client_rate_limit_blocks_requests(): void
    {
        config(['notifications.rate_limits.per_client_per_minute' => 1]);
        Redis::shouldReceive('incr')->once()->andReturn(2);
        Redis::shouldReceive('expire')->never();

        $response = $this->getJson('/api/v1/metrics');

        $response->assertStatus(429)
            ->assertJson([
                'message' => 'Rate limit exceeded',
                'limit' => 1,
            ]);
    }

    public function test_health_endpoint_returns_ok_and_correlation_id(): void
    {
        Redis::shouldReceive('ping')->andReturn('PONG');

        $response = $this->get('/api/v1/health', [
            'X-Correlation-Id' => 'test-correlation-id',
        ]);

        $response->assertStatus(200);
        $this->assertSame('test-correlation-id', $response->headers->get('X-Correlation-Id'));
    }

    public function test_correlation_id_is_added_to_log_context(): void
    {
        Redis::shouldReceive('ping')->andReturn('PONG');
        Log::spy();

        $this->get('/api/v1/health', [
            'X-Correlation-Id' => 'log-correlation-id',
        ])->assertStatus(200);

        Log::shouldHaveReceived('withContext')
            ->with(['correlation_id' => 'log-correlation-id'])
            ->once();
    }

    public function test_status_updates_are_broadcasted(): void
    {
        $captured = false;
        Event::listen(NotificationStatusUpdated::class, function ($event) use (&$captured) {
            $captured = $event->status === 'sent';
        });

        $notification = Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Broadcast test',
            'status' => 'pending',
        ]);

        $notification->status = 'sent';
        $notification->save();

        $this->assertTrue($captured);
    }

    public function test_dead_letter_endpoints_list_and_show(): void
    {
        $dead = DeadLetterNotification::create([
            'notification_id' => null,
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 3,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => ['to' => '+905551234567'],
        ]);

        $this->getJson('/api/v1/dead-letter?per_page=5')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $dead->id]);

        $this->getJson("/api/v1/dead-letter/{$dead->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $dead->id]);
    }

    public function test_template_preview_renders_content(): void
    {
        $template = NotificationTemplate::create([
            'name' => 'Welcome',
            'channel' => 'email',
            'content' => 'Hello {{name}}',
            'default_variables' => ['name' => 'Friend'],
        ]);

        $response = $this->postJson('/api/v1/templates/preview', [
            'template_id' => $template->id,
            'variables' => ['name' => 'Ada'],
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'template_id' => $template->id,
                'content' => 'Hello Ada',
            ]);
    }
}
