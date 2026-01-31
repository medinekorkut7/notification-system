<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $query = AdminAuditLog::query()->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('admin_user_id')) {
            $query->where('admin_user_id', $request->string('admin_user_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        $logs = $query->paginate($perPage);

        return view('admin.audit.index', [
            'logs' => $logs,
            'filters' => [
                'action' => $request->input('action', ''),
                'admin_user_id' => $request->input('admin_user_id', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'per_page' => $perPage,
            ],
        ]);
    }
}
