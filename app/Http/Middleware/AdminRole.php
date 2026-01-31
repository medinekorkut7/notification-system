<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminUserId = $request->session()->get('admin_user_id');
        if (!$adminUserId) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized'], 401)
                : redirect('/admin/login');
        }

        $admin = AdminUser::query()->find($adminUserId);
        if (!$admin || $admin->role !== 'admin') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            return response()->view('admin.forbidden', [], 403);
        }

        return $next($request);
    }
}
