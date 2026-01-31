<?php

namespace App\Jobs;

use App\Models\DeadLetterNotification;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeadLetterNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $notificationId)
    {
    }

    public function handle(): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (!$notification) {
            return;
        }

        DeadLetterNotification::create([
            'notification_id' => $notification->id,
            'channel' => $notification->channel,
            'recipient' => $notification->recipient,
            'attempts' => $notification->attempts,
            'error_type' => $notification->error_type,
            'error_code' => $notification->error_code,
            'error_message' => $notification->last_error,
            'payload' => [
                'to' => $notification->recipient,
                'channel' => $notification->channel,
                'content' => $notification->content,
                'idempotency_key' => $notification->idempotency_key,
            ],
            'last_response' => $notification->provider_response,
        ]);
    }
}
