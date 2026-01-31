<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'channel',
        'content',
        'default_variables',
    ];

    protected $casts = [
        'default_variables' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
}
