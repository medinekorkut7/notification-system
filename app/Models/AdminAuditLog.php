<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'id',
        'admin_user_id',
        'action',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
