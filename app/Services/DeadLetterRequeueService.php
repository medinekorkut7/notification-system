<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\DeadLetterNotification;
use App\Models\Notification;
use App\Enums\NotificationStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeadLetterRequeueService
{
    private const VALID_PRIORITIES = ['high', 'normal', 'low'];

    public function isValidPriority(string $priority): bool
    {
        return in_array($priority, self::VALID_PRIORITIES, true);
    }

    /**
     * Requeue a single dead letter notification.
     *
     * @return array{ok: bool, notification_id?: string, message?: string}
     */
    public function requeueSingle(
        DeadLetterNotification $item,
        int $delaySeconds = 0,
        string $priority = 'normal'
    ): array {
        $prepared = $this->prepareRequeueData($item, $priority, now());

        if ($prepared === null) {
            return ['ok' => false, 'message' => 'Dead-letter item is missing recipient or channel.'];
        }

        $notification = Notification::query()->create($prepared['notification']);

        $queue = config('notifications.queue_names.' . $priority, 'notifications-normal');
        $job = (new SendNotificationJob($notification->id))->onQueue($queue);

        if ($delaySeconds > 0) {
            $job->delay($delaySeconds);
        }

        dispatch($job);

        return ['ok' => true, 'notification_id' => $notification->id];
    }

    /**
     * Requeue multiple dead letter notifications with batch optimization.
     *
     * @param Collection<int, DeadLetterNotification> $items
     * @return array{requeued: int, skipped: int}
     */
    public function requeueBatch(
        Collection $items,
        int $delaySeconds = 0,
        string $priority = 'normal'
    ): array {
        $notificationsToInsert = [];
        $jobsToDispatch = [];
        $skipped = 0;
        $now = now();

        foreach ($items as $item) {
            $prepared = $this->prepareRequeueData($item, $priority, $now);
            if ($prepared === null) {
                $skipped++;
                continue;
            }
            $notificationsToInsert[] = $prepared['notification'];
            $jobsToDispatch[] = $prepared['job'];
        }

        $requeued = count($notificationsToInsert);

        if ($requeued > 0) {
            DB::transaction(function () use ($notificationsToInsert, $jobsToDispatch, $delaySeconds, $priority) {
                // Batch insert notifications (chunked to avoid query size limits)
                foreach (array_chunk($notificationsToInsert, 100) as $chunk) {
                    Notification::insert($chunk);
                }

                // Dispatch jobs
                $queue = config('notifications.queue_names.' . $priority, 'notifications-normal');
                foreach ($jobsToDispatch as $job) {
                    $queueJob = (new SendNotificationJob($job['id']))->onQueue($queue);
                    if ($delaySeconds > 0) {
                        $queueJob->delay($delaySeconds);
                    }
                    dispatch($queueJob);
                }
            });
        }

        return [
            'requeued' => $requeued,
            'skipped' => $skipped,
        ];
    }

    /**
     * Query dead letter notifications for requeue.
     *
     * @return Collection<int, DeadLetterNotification>
     */
    public function queryForRequeue(int $limit, string $channel = ''): Collection
    {
        $query = DeadLetterNotification::query()->orderBy('created_at');

        if ($channel !== '') {
            $query->where('channel', $channel);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Prepare notification data for insertion.
     *
     * @return array{notification: array, job: array}|null
     */
    private function prepareRequeueData(DeadLetterNotification $item, string $priority, $now): ?array
    {
        $payload = is_array($item->payload) ? $item->payload : [];

        $recipient = $payload['to'] ?? $item->recipient;
        $channel = $payload['channel'] ?? $item->channel;
        $content = $payload['content'] ?? '';

        if (!$recipient || !$channel) {
            return null;
        }

        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $idempotencyKey = $idempotencyKey . '-requeue-' . Str::uuid();
        }

        $notificationId = (string) Str::uuid();

        return [
            'notification' => [
                'id' => $notificationId,
                'batch_id' => null,
                'channel' => $channel,
                'priority' => $priority,
                'recipient' => $recipient,
                'content' => $content,
                'status' => NotificationStatus::Pending->value,
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => (string) Str::uuid(),
                'attempts' => 0,
                'max_attempts' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'job' => [
                'id' => $notificationId,
                'priority' => $priority,
            ],
        ];
    }
}
