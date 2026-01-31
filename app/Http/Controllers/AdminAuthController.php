<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\AdminAuditLogger;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = AdminUser::query()
            ->where('email', $credentials['email'])
            ->first();

        if (!$user || !$user->is_active || !Hash::check($credentials['password'], $user->password)) {
            app(AdminAuditLogger::class)->log($request, 'admin.login_failed', [
                'email' => $credentials['email'],
                'reason' => $user ? ($user->is_active ? 'invalid_password' : 'inactive') : 'not_found',
            ]);
            return back()
                ->withErrors(['email' => 'Invalid credentials or account disabled.'])
                ->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('admin_user_id', $user->id);
        $request->session()->put('admin_user_name', $user->name);

        $user->last_login_at = now();
        $user->save();

        app(AdminAuditLogger::class)->log($request, 'admin.login', [
            'admin_user_id' => $user->id,
            'email' => $user->email,
        ]);

        return redirect('/admin');
    }

    public function logout(Request $request)
    {
        app(AdminAuditLogger::class)->log($request, 'admin.logout');
        $request->session()->forget(['admin_user_id', 'admin_user_name']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
