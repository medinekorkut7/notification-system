<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\DeadLetterNotification;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AdminDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_metrics_and_failure_stats(): void
    {
        Queue::shouldReceive('size')->andReturn(0);
        Redis::shouldReceive('exists')->andReturn(0);

        $admin = AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'hashed',
            'role' => 'admin',
            'is_active' => true,
        ]);

        Notification::create([
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient' => '+905551234567',
            'content' => 'Sent',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now()->subMinute(),
        ]);

        Notification::create([
            'channel' => 'email',
            'priority' => 'high',
            'recipient' => 'user@example.com',
            'content' => 'Failed',
            'status' => 'failed',
            'error_type' => 'permanent',
        ]);

        DeadLetterNotification::create([
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 2,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => ['to' => '+905551234567'],
        ]);

        $response = $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin');

        $response->assertStatus(200)
            ->assertViewIs('admin.dashboard')
            ->assertViewHas('metrics', function (array $metrics): bool {
                return $metrics['total'] === 2
                    && $metrics['dead_letter_count'] === 1
                    && isset($metrics['queues'])
                    && isset($metrics['circuit_breaker']);
            })
            ->assertViewHas('failureStats', function (array $stats): bool {
                return $stats['max_code'] === 1
                    && $stats['max_channel'] === 1
                    && $stats['max_type'] === 1;
            })
            ->assertViewHas('isAdmin', true);
    }
}
