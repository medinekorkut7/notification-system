<?php

namespace Tests\Integration;

use App\Models\DeadLetterNotification;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class NotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!env('INTEGRATION_TESTS')) {
            $this->markTestSkipped('Integration tests are disabled. Set INTEGRATION_TESTS=1 to run.');
        }

        if (!$this->redisAvailable()) {
            $this->markTestSkipped('Redis is not available for integration tests.');
        }

        config(['queue.default' => 'redis']);

        try {
            app('redis')->connection()->flushdb();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Redis flush failed for integration tests.');
        }
    }

    public function test_end_to_end_delivery_flow(): void
    {
        Http::fake([
            '*' => Http::response([
                'messageId' => 'int-1',
                'status' => 'accepted',
                'timestamp' => now()->toIso8601String(),
            ], 202),
        ]);

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Integration test',
                    'priority' => 'high',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/notifications', $payload);
        $response->assertStatus(201);

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'notifications-high',
        ]);

        $notificationId = $response->json('notifications.0.id');
        $notification = Notification::query()->find($notificationId);

        $this->assertSame('sent', $notification->status);
        $this->assertSame('int-1', $notification->provider_message_id);
    }

    public function test_dead_letter_on_permanent_error(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'bad'], 400),
        ]);

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Permanent fail',
                    'priority' => 'high',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/notifications', $payload);
        $response->assertStatus(201);

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'notifications-high',
        ]);

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'notifications-dead',
        ]);

        $this->assertSame(1, DeadLetterNotification::query()->count());
    }

    public function test_circuit_breaker_opens_and_delays(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'error'], 500),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $payload = [
                'notifications' => [
                    [
                        'recipient' => '+905551234567',
                        'channel' => 'sms',
                        'content' => 'Breaker test',
                        'priority' => 'high',
                    ],
                ],
            ];

            $response = $this->postJson('/api/v1/notifications', $payload);
            $response->assertStatus(201);

            Artisan::call('queue:work', [
                '--once' => true,
                '--queue' => 'notifications-high',
            ]);
        }

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'content' => 'Breaker open',
                    'priority' => 'high',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/notifications', $payload);
        $response->assertStatus(201);

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'notifications-high',
        ]);

        $notificationId = $response->json('notifications.0.id');
        $notification = Notification::query()->find($notificationId);

        $this->assertSame('retrying', $notification->status);
        $this->assertNotNull($notification->next_retry_at);
    }

    public function test_template_rendering_and_trace_propagation(): void
    {
        $template = NotificationTemplate::create([
            'name' => 'integration-template',
            'channel' => 'sms',
            'content' => 'Hello {{name}}',
            'default_variables' => ['name' => 'Guest'],
        ]);

        $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

        Http::fake(function ($request) use ($traceparent) {
            $this->assertSame($traceparent, $request->header('traceparent')[0] ?? null);

            return Http::response([
                'messageId' => 'int-2',
                'status' => 'accepted',
                'timestamp' => now()->toIso8601String(),
            ], 202);
        });

        $payload = [
            'notifications' => [
                [
                    'recipient' => '+905551234567',
                    'channel' => 'sms',
                    'template_id' => $template->id,
                    'variables' => ['name' => 'Ada'],
                    'priority' => 'high',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/notifications', $payload, [
            'traceparent' => $traceparent,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => 'Hello Ada']);

        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'notifications-high',
        ]);
    }

    private function redisAvailable(): bool
    {
        $host = env('REDIS_HOST', 'redis');
        $port = (int) env('REDIS_PORT', 6379);

        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($socket === false) {
            return false;
        }

        fclose($socket);
        return true;
    }
}
