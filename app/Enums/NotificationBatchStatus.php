<?php

namespace App\Enums;

enum NotificationBatchStatus: string
{
    case Pending = 'pending';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
