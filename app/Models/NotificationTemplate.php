<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasUuids;
    use SoftDeletes;

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
