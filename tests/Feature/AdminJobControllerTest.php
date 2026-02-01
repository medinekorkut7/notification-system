<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use App\Models\DeadLetterNotification;
use App\Services\DeadLetterRequeueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class AdminJobControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'hashed',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_status_reports_pause_state(): void
    {
        Redis::shouldReceive('exists')
            ->with('notifications:processing:paused')
            ->andReturn(1);

        $admin = $this->makeAdmin();

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/jobs/status')
            ->assertStatus(200)
            ->assertJson(['paused' => true]);
    }

    public function test_pause_and_resume_toggle_processing(): void
    {
        $admin = $this->makeAdmin();

        Redis::shouldReceive('set')
            ->with('notifications:processing:paused', '1')
            ->andReturn(true);

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/jobs/pause')
            ->assertStatus(200)
            ->assertJson(['paused' => true]);

        Redis::shouldReceive('del')
            ->with('notifications:processing:paused')
            ->andReturn(1);

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/jobs/resume')
            ->assertStatus(200)
            ->assertJson(['paused' => false]);

        $this->assertSame(1, AdminAuditLog::where('action', 'admin.processing_paused')->count());
        $this->assertSame(1, AdminAuditLog::where('action', 'admin.processing_resumed')->count());
    }

    public function test_restart_returns_success_on_queue_restart(): void
    {
        $admin = $this->makeAdmin();

        Artisan::shouldReceive('call')
            ->with('queue:restart')
            ->andReturn(0);

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/jobs/restart')
            ->assertStatus(200)
            ->assertJson(['message' => 'Queue restart signal sent.']);

        $this->assertSame(1, AdminAuditLog::where('action', 'admin.queue_restart')->count());
    }

    public function test_restart_returns_error_on_failure(): void
    {
        $admin = $this->makeAdmin();

        Artisan::shouldReceive('call')
            ->with('queue:restart')
            ->andThrow(new \RuntimeException('boom'));

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/jobs/restart')
            ->assertStatus(500)
            ->assertJson(['message' => 'Failed to signal queue restart.']);
    }

    public function test_stress_runs_and_logs(): void
    {
        $admin = $this->makeAdmin();

        Redis::shouldReceive('set')->twice()->andReturn(true);
        Artisan::shouldReceive('call')->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('ok');

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/jobs/stress', [
                'count' => 10,
                'batch' => 5,
                'channel' => 'sms',
                'priority' => 'high',
            ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Stress test started.']);

        $this->assertSame(1, AdminAuditLog::where('action', 'admin.stress_start')->count());
        $this->assertSame(1, AdminAuditLog::where('action', 'admin.stress_completed')->count());
    }

    public function test_stress_returns_error_on_exception(): void
    {
        $admin = $this->makeAdmin();

        Redis::shouldReceive('set')->atLeast()->once()->andReturn(true);
        Artisan::shouldReceive('call')->andThrow(new \RuntimeException('fail'));

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/jobs/stress', [
                'count' => 10,
                'batch' => 5,
                'channel' => 'sms',
                'priority' => 'high',
            ])
            ->assertStatus(500)
            ->assertJson(['message' => 'Failed to start stress test.']);

        $this->assertSame(1, AdminAuditLog::where('action', 'admin.stress_failed')->count());
    }

    public function test_stress_status_returns_payload(): void
    {
        $admin = $this->makeAdmin();

        Redis::shouldReceive('get')
            ->with('notifications:stress:last')
            ->andReturn(json_encode(['status' => 'running']));

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/jobs/stress/status')
            ->assertStatus(200)
            ->assertJson(['status' => 'running']);
    }

    public function test_provider_settings_persists_and_logs(): void
    {
        $admin = $this->makeAdmin();

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/provider/settings', [
                'provider_webhook_url' => 'https://example.com/hook',
                'provider_fallback_webhook_url' => 'https://example.com/fallback',
            ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Provider settings saved.']);

        $this->assertSame(
            'https://example.com/hook',
            DB::table('notification_settings')->where('name', 'provider_webhook_url')->value('value')
        );
        $this->assertSame(1, AdminAuditLog::where('action', 'admin.provider_settings_updated')->count());
    }

    public function test_requeue_dead_letters_uses_service_and_logs(): void
    {
        $admin = $this->makeAdmin();

        $service = Mockery::mock(DeadLetterRequeueService::class);
        $service->shouldReceive('isValidPriority')->with('normal')->andReturn(true);
        $service->shouldReceive('queryForRequeue')->with(5, 'sms')->andReturn(collect());
        $service->shouldReceive('requeueBatch')->andReturn(['requeued' => 0, 'skipped' => 0]);
        $this->app->instance(DeadLetterRequeueService::class, $service);

        $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/dead-letter/requeue', [
                'limit' => 5,
                'channel' => 'sms',
                'delay_seconds' => 2,
                'priority' => 'normal',
            ])
            ->assertStatus(200)
            ->assertJson([
                'requested' => 5,
                'requeued' => 0,
                'skipped' => 0,
            ]);

        $this->assertSame(1, AdminAuditLog::where('action', 'admin.dead_letter_requeue_bulk')->count());
    }

    public function test_requeue_dead_letter_rejects_invalid_priority(): void
    {
        $admin = $this->makeAdmin();

        $deadLetter = DeadLetterNotification::create([
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => ['to' => '+905551234567', 'channel' => 'sms'],
        ]);

        $service = Mockery::mock(DeadLetterRequeueService::class);
        $service->shouldReceive('isValidPriority')->with('bad')->andReturn(false);
        $this->app->instance(DeadLetterRequeueService::class, $service);

        $this->withSession(['admin_user_id' => $admin->id])
            ->post("/admin/dead-letter/{$deadLetter->id}/requeue", [
                'priority' => 'bad',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid priority.']);
    }

    public function test_requeue_dead_letter_success_response(): void
    {
        $admin = $this->makeAdmin();

        $deadLetter = DeadLetterNotification::create([
            'channel' => 'sms',
            'recipient' => '+905551234567',
            'attempts' => 1,
            'error_type' => 'permanent',
            'error_code' => 'http_400',
            'error_message' => 'Bad request',
            'payload' => ['to' => '+905551234567', 'channel' => 'sms'],
        ]);

        $service = Mockery::mock(DeadLetterRequeueService::class);
        $service->shouldReceive('isValidPriority')->with('normal')->andReturn(true);
        $service->shouldReceive('requeueSingle')
            ->andReturn(['ok' => true, 'notification_id' => 'notif-123']);
        $this->app->instance(DeadLetterRequeueService::class, $service);

        $this->withSession(['admin_user_id' => $admin->id])
            ->post("/admin/dead-letter/{$deadLetter->id}/requeue", [
                'priority' => 'normal',
            ])
            ->assertStatus(201)
            ->assertJson(['notification_id' => 'notif-123']);

        $this->assertSame(1, AdminAuditLog::where('action', 'admin.dead_letter_requeue_single')->count());
    }

    public function test_workers_status_returns_supervisor_error(): void
    {
        $admin = $this->makeAdmin();
        config(['notifications.supervisor.bin' => '/missing/supervisorctl']);

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/workers/status')
            ->assertStatus(200)
            ->assertJson(['exit_code' => 127]);
    }
}
