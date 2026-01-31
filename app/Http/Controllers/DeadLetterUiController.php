<?php

namespace App\Http\Controllers;

use App\Models\DeadLetterNotification;
use App\Models\AdminUser;
use Illuminate\Http\Request;

class DeadLetterUiController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 12);
        $items = DeadLetterNotification::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return view('dead-letter.index', [
            'items' => $items,
            'isAdmin' => $this->isAdmin($request),
        ]);
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
