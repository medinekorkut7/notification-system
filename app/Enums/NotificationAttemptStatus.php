<?php

namespace App\Enums;

enum NotificationAttemptStatus: string
{
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
