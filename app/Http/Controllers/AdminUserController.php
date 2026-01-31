<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Services\AdminAuditLogger;

class AdminUserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => AdminUser::query()->orderBy('created_at', 'desc')->paginate(20),
            'isAdmin' => $this->isAdmin(request()),
        ]);
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admin_users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'viewer'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = AdminUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        app(AdminAuditLogger::class)->log($request, 'admin.user_created', [
            'admin_user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ]);

        return redirect('/admin/users')->with('status', 'Admin user created.');
    }

    public function edit(AdminUser $adminUser)
    {
        return view('admin.users.edit', [
            'user' => $adminUser,
        ]);
    }

    public function update(Request $request, AdminUser $adminUser)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admin_users', 'email')->ignore($adminUser->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'viewer'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $adminUser->name = $data['name'];
        $adminUser->email = $data['email'];
        $adminUser->role = $data['role'];
        $adminUser->is_active = (bool) ($data['is_active'] ?? false);

        if (!empty($data['password'])) {
            $adminUser->password = Hash::make($data['password']);
        }

        $adminUser->save();

        app(AdminAuditLogger::class)->log($request, 'admin.user_updated', [
            'admin_user_id' => $adminUser->id,
            'email' => $adminUser->email,
            'role' => $adminUser->role,
            'is_active' => $adminUser->is_active,
        ]);

        return redirect('/admin/users')->with('status', 'Admin user updated.');
    }

    public function destroy(Request $request, AdminUser $adminUser)
    {
        $adminUser->delete();

        app(AdminAuditLogger::class)->log($request, 'admin.user_deleted', [
            'admin_user_id' => $adminUser->id,
            'email' => $adminUser->email,
        ]);

        return redirect('/admin/users')->with('status', 'Admin user removed.');
    }

    private function isAdmin(Request $request): bool
    {
        $adminUserId = $request->session()->get('admin_user_id');
        if (!$adminUserId) {
            return false;
        }

        $admin = AdminUser::query()->find($adminUserId);
        return $admin?->role === 'admin';
    }
}
