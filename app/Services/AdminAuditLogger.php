<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminAuditLogger
{
    public function log(Request $request, string $action, array $metadata = []): void
    {
        $adminUserId = $request->session()->get('admin_user_id');

        AdminAuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
