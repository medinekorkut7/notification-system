<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Scheduled = 'scheduled';
    case Retrying = 'retrying';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
