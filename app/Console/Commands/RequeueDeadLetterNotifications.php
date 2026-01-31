<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\DeadLetterNotification;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RequeueDeadLetterNotifications extends Command
{
    protected $signature = 'deadletter:requeue
        {--limit=100 : Max number of items to requeue}
        {--channel= : Filter by channel}
        {--priority=normal : high|normal|low}
        {--delay=0 : Delay in seconds before processing}
        {--dry-run : Show what would be requeued without enqueueing}';

    protected $description = 'Requeue dead-letter notifications for retry';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $channel = $this->option('channel');
        $priority = (string) $this->option('priority');
        $delay = max(0, (int) $this->option('delay'));
        $dryRun = (bool) $this->option('dry-run');

        if (!in_array($priority, ['high', 'normal', 'low'], true)) {
            $this->error('Invalid priority. Use high|normal|low.');
            return self::FAILURE;
        }

        $query = DeadLetterNotification::query()->orderBy('created_at');
        if ($channel) {
            $query->where('channel', $channel);
        }

        $items = $query->limit($limit)->get();
        if ($items->isEmpty()) {
            $this->info('No dead-letter notifications found.');
            return self::SUCCESS;
        }

        $requeued = 0;
        foreach ($items as $item) {
            $payload = is_array($item->payload) ? $item->payload : [];
            $recipient = $payload['to'] ?? $item->recipient;
            $itemChannel = $payload['channel'] ?? $item->channel;
            $content = $payload['content'] ?? '';

            if (!$recipient || !$itemChannel) {
                $this->warn("Skipping {$item->id}: missing recipient/channel.");
                continue;
            }

            $idempotencyKey = $payload['idempotency_key'] ?? null;
            if ($idempotencyKey) {
                $idempotencyKey = $idempotencyKey . '-requeue-' . Str::uuid();
            }

            if ($dryRun) {
                $this->line("Would requeue {$item->id} to {$itemChannel} {$recipient}");
                $requeued++;
                continue;
            }

            $notification = Notification::query()->create([
                'batch_id' => null,
                'channel' => $itemChannel,
                'priority' => $priority,
                'recipient' => $recipient,
                'content' => $content,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => (string) Str::uuid(),
                'attempts' => 0,
                'max_attempts' => 5,
            ]);

            $queue = config('notifications.queue_names.' . $priority, 'notifications-normal');
            $job = (new SendNotificationJob($notification->id))->onQueue($queue);
            if ($delay > 0) {
                $job->delay($delay);
            }
            dispatch($job);

            $requeued++;
        }

        $this->info("Requeued {$requeued} dead-letter notifications.");
        return self::SUCCESS;
    }
}
