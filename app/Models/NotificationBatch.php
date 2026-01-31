<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'idempotency_key',
        'correlation_id',
        'trace_id',
        'span_id',
        'status',
        'total_count',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }
}
