<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_login_renders_view(): void
    {
        $this->get('/admin/login')
            ->assertStatus(200)
            ->assertViewIs('admin.login');
    }

    public function test_login_success_sets_session_and_logs(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret',
        ]);

        $response->assertRedirect('/admin')
            ->assertSessionHas('admin_user_id', $admin->id);

        $admin->refresh();
        $this->assertNotNull($admin->last_login_at);
        $this->assertSame(1, AdminAuditLog::where('action', 'admin.login')->count());
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $admin = AdminUser::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret',
        ]);

        $response->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');
        $this->assertSame(1, AdminAuditLog::where('action', 'admin.login_failed')->count());
    }

    public function test_logout_clears_session_and_logs(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'hashed',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->withSession([
            'admin_user_id' => $admin->id,
            'admin_user_name' => $admin->name,
        ])->post('/admin/logout');

        $response->assertRedirect('/admin/login')
            ->assertSessionMissing('admin_user_id');
        $this->assertSame(1, AdminAuditLog::where('action', 'admin.logout')->count());
    }
}
