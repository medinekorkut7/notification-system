<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'batch_id',
        'channel',
        'priority',
        'recipient',
        'content',
        'status',
        'idempotency_key',
        'correlation_id',
        'trace_id',
        'span_id',
        'attempts',
        'max_attempts',
        'scheduled_at',
        'processing_started_at',
        'sent_at',
        'cancelled_at',
        'provider_message_id',
        'provider_response',
        'last_error',
        'error_type',
        'error_code',
        'last_retry_at',
        'next_retry_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'provider_response' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function batch()
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    public function attempts()
    {
        return $this->hasMany(NotificationAttempt::class);
    }
}
