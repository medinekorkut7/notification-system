<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DeadLetterNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'notification_id',
        'channel',
        'recipient',
        'attempts',
        'error_type',
        'error_code',
        'error_message',
        'payload',
        'last_response',
    ];

    protected $casts = [
        'payload' => 'array',
        'last_response' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
}
