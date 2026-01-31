<?php

namespace App\Observers;

use App\Models\Notification;
use App\Models\NotificationBatch;

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

        $batch = NotificationBatch::query()->find($notification->batch_id);
        if (!$batch) {
            return;
        }

        $counts = $batch->notifications()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        if (($counts['pending'] ?? 0) > 0 || ($counts['processing'] ?? 0) > 0 || ($counts['scheduled'] ?? 0) > 0 || ($counts['retrying'] ?? 0) > 0) {
            $batch->status = 'pending';
        } elseif (($counts['failed'] ?? 0) > 0) {
            $batch->status = 'failed';
        } elseif (($counts['cancelled'] ?? 0) > 0 && $counts->sum() === ($counts['cancelled'] ?? 0)) {
            $batch->status = 'cancelled';
        } else {
            $batch->status = 'completed';
        }

        $batch->save();
    }
}
