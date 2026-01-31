<?php

namespace App\Http\Controllers;

use App\Models\DeadLetterNotification;
use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeadLetterNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $query = DeadLetterNotification::query()->orderByDesc('created_at');

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(string $deadLetterId): JsonResponse
    {
        $item = DeadLetterNotification::query()->findOrFail($deadLetterId);

        return response()->json($item);
    }

    public function requeue(Request $request, string $deadLetterId): JsonResponse
    {
        $item = DeadLetterNotification::query()->findOrFail($deadLetterId);
        $delay = max(0, (int) $request->input('delay_seconds', 0));
        $priority = (string) $request->input('priority', 'normal');

        if (!in_array($priority, ['high', 'normal', 'low'], true)) {
            return response()->json([
                'message' => 'Invalid priority.',
            ], 422);
        }

        $result = $this->requeueItem($item, $delay, $priority);
        if (!$result['ok']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'Dead-letter notification requeued.',
            'notification_id' => $result['notification_id'],
        ], 201);
    }

    public function requeueAll(Request $request): JsonResponse
    {
        $limit = max(1, (int) $request->input('limit', 100));
        $channel = $request->string('channel')->toString();
        $delay = max(0, (int) $request->input('delay_seconds', 0));
        $priority = (string) $request->input('priority', 'normal');

        if (!in_array($priority, ['high', 'normal', 'low'], true)) {
            return response()->json([
                'message' => 'Invalid priority.',
            ], 422);
        }

        $query = DeadLetterNotification::query()->orderBy('created_at');
        if ($channel !== '') {
            $query->where('channel', $channel);
        }

        $items = $query->limit($limit)->get();
        $requeued = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $result = $this->requeueItem($item, $delay, $priority);
            if ($result['ok']) {
                $requeued++;
            } else {
                $skipped++;
            }
        }

        return response()->json([
            'message' => 'Dead-letter requeue completed.',
            'requested' => $limit,
            'requeued' => $requeued,
            'skipped' => $skipped,
        ]);
    }

    /**
     * @return array{ok: bool, notification_id?: string, message?: string}
     */
    private function requeueItem(DeadLetterNotification $item, int $delaySeconds = 0, string $priority = 'normal'): array
    {
        $payload = is_array($item->payload) ? $item->payload : [];

        $recipient = $payload['to'] ?? $item->recipient;
        $channel = $payload['channel'] ?? $item->channel;
        $content = $payload['content'] ?? '';

        if (!$recipient || !$channel) {
            return ['ok' => false, 'message' => 'Dead-letter item is missing recipient or channel.'];
        }

        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $idempotencyKey = $idempotencyKey . '-requeue-' . Str::uuid();
        }

        $notification = Notification::query()->create([
            'batch_id' => null,
            'channel' => $channel,
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
        if ($delaySeconds > 0) {
            $job->delay($delaySeconds);
        }
        dispatch($job);

        return ['ok' => true, 'notification_id' => $notification->id];
    }
}
