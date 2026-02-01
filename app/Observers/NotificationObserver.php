<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Enums\NotificationBatchStatus;
use App\Enums\NotificationStatus;

class NotificationObserver
{
    public function saved(Notification $notification): void
    {
        if ($notification->wasChanged('status')) {
            event(new \App\Events\NotificationStatusUpdated($notification->id, $notification->status));
        }

        if (!$notification->batch_id) {
            return;
        }

        // Dispatch debounced job for batch status update
        // The job implements ShouldBeUnique, so multiple saves within uniqueFor seconds
        // will result in only one job execution
        dispatch(new \App\Jobs\UpdateBatchStatusJob($notification->batch_id))
            ->delay(now()->addSeconds(2));
    }
}
