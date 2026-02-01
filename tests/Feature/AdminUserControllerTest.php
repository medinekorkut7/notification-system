<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_admin_session_and_shows_users(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'hashed',
            'role' => 'admin',
            'is_active' => true,
        ]);

        AdminUser::create([
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
            'password' => 'hashed',
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $response = $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/users');

        $response->assertStatus(200)
            ->assertViewIs('admin.users.index')
            ->assertViewHas('isAdmin', true);
    }

    public function test_store_creates_admin_user_and_audit_log(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'hashed',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->withSession(['admin_user_id' => $admin->id])
            ->post('/admin/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'secret123',
                'role' => 'viewer',
                'is_active' => true,
            ]);

        $response->assertRedirect('/admin/users')
            ->assertSessionHas('status', 'Admin user created.');

        $this->assertDatabaseHas('admin_users', [
            'email' => 'new@example.com',
            'role' => 'viewer',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin.user_created',
        ]);
    }

    public function test_update_changes_user_fields_and_logs_audit(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'hashed',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $target = AdminUser::create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => 'hashed',
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $response = $this->withSession(['admin_user_id' => $admin->id])
            ->put("/admin/users/{$target->id}", [
                'name' => 'Updated User',
                'email' => 'updated@example.com',
                'role' => 'admin',
            ]);

        $response->assertRedirect('/admin/users')
            ->assertSessionHas('status', 'Admin user updated.');

        $this->assertDatabaseHas('admin_users', [
            'id' => $target->id,
            'email' => 'updated@example.com',
            'role' => 'admin',
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin.user_updated',
        ]);
    }

    public function test_destroy_deletes_user_and_logs_audit(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'hashed',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $target = AdminUser::create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => 'hashed',
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $response = $this->withSession(['admin_user_id' => $admin->id])
            ->delete("/admin/users/{$target->id}");

        $response->assertRedirect('/admin/users')
            ->assertSessionHas('status', 'Admin user removed.');

        $this->assertSoftDeleted('admin_users', [
            'id' => $target->id,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin.user_deleted',
        ]);
    }
}
