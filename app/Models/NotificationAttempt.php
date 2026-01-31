<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'notification_id',
        'attempt_number',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'error_type',
        'error_code',
        'http_status',
        'duration_ms',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
