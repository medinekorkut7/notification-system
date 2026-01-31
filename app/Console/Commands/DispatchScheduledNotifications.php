<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DispatchScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:dispatch-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch scheduled notifications that are due';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->isProcessingPaused()) {
            $this->info('Processing paused. Scheduled notifications not dispatched.');
            return;
        }

        $dueNotifications = Notification::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->limit(500)
            ->get();

        foreach ($dueNotifications as $notification) {
            $notification->status = 'pending';
            $notification->save();

            dispatch((new SendNotificationJob($notification->id))
                ->onQueue(config('notifications.queue_names.' . $notification->priority, 'notifications-normal')));
        }

        $this->info("Dispatched {$dueNotifications->count()} scheduled notifications.");
    }

    private function isProcessingPaused(): bool
    {
        try {
            return (bool) Redis::exists('notifications:processing:paused');
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
