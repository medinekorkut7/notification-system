<?php

namespace App\Http\Controllers\Traits;

use App\Models\AdminUser;
use Illuminate\Http\Request;

trait ChecksAdminRole
{
    protected function isAdmin(Request $request): bool
    {
        $adminUserId = $request->session()->get('admin_user_id');
        if (!$adminUserId) {
            return false;
        }

        // Cache admin user lookup in request to avoid repeated queries
        $cacheKey = 'admin_user_' . $adminUserId;
        $admin = $request->attributes->get($cacheKey);

        if ($admin === null) {
            $admin = AdminUser::query()->find($adminUserId) ?? false;
            $request->attributes->set($cacheKey, $admin);
        }

        return $admin && $admin->role === 'admin';
    }

    protected function getAdminUser(Request $request): ?AdminUser
    {
        $adminUserId = $request->session()->get('admin_user_id');
        if (!$adminUserId) {
            return null;
        }

        $cacheKey = 'admin_user_' . $adminUserId;
        $admin = $request->attributes->get($cacheKey);

        if ($admin === null) {
            $admin = AdminUser::query()->find($adminUserId) ?? false;
            $request->attributes->set($cacheKey, $admin);
        }

        return $admin ?: null;
    }
}
