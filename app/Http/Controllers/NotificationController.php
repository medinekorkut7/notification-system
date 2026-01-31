<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListNotificationsRequest;
use App\Http\Requests\StoreNotificationsRequest;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\NotificationTemplate;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function store(StoreNotificationsRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $notificationsData = $payload['notifications'] ?? [];
        $batchData = $payload['batch'] ?? null;
        $correlationId = $request->attributes->get('correlation_id');
        $traceId = $request->attributes->get('trace_id');
        $spanId = $request->attributes->get('span_id');

        $existingBatch = $this->findExistingBatch($batchData);

        if ($existingBatch) {
            return response()->json([
                'message' => 'Batch idempotency key already exists.',
                'batch_id' => $existingBatch->id,
                'duplicates' => $existingBatch->notifications()->count(),
            ], 409);
        }

        $batch = null;
        $created = 0;
        $duplicates = 0;
        $results = [];

        DB::transaction(function () use (
            $notificationsData,
            $batchData,
            $correlationId,
            $traceId,
            $spanId,
            &$batch,
            &$created,
            &$duplicates,
            &$results
        ) {
            $batch = $this->createBatchIfNeeded(
                $batchData,
                $notificationsData,
                $correlationId,
                $traceId,
                $spanId
            );

            $renderer = app(TemplateRenderer::class);

            foreach ($notificationsData as $notificationData) {
                $idempotencyKey = $notificationData['idempotency_key'] ?? null;
                $this->ensureNotificationIdempotent($idempotencyKey);

                $priority = $notificationData['priority'] ?? 'normal';
                $scheduledAt = $this->parseScheduledAt($notificationData);
                $status = $this->resolveStatus($scheduledAt);

                $content = $this->resolveContent($notificationData, $renderer);
                $this->assertContentWithinLimits($notificationData['channel'], $content);

                $notification = Notification::create([
                    'batch_id' => $batch?->id,
                    'channel' => $notificationData['channel'],
                    'priority' => $priority,
                    'recipient' => $notificationData['recipient'],
                    'content' => $content ?? '',
                    'status' => $status,
                    'idempotency_key' => $idempotencyKey,
                    'correlation_id' => $notificationData['correlation_id'] ?? $correlationId,
                    'trace_id' => $traceId,
                    'span_id' => $spanId,
                    'scheduled_at' => $scheduledAt,
                ]);

                $created++;
                $results[] = $this->formatNotification($notification);

                if ($status === 'pending') {
                    dispatch((new SendNotificationJob($notification->id))
                        ->onQueue(config('notifications.queue_names.' . $priority, 'notifications-normal')));
                }
            }
        });

        return response()->json([
            'batch_id' => $batch?->id,
            'trace_id' => $batch?->trace_id,
            'span_id' => $batch?->span_id,
            'metadata' => $batch?->metadata,
            'created' => $created,
            'duplicates' => $duplicates,
            'notifications' => $results,
        ], 201);
    }

    public function show(string $notificationId): JsonResponse
    {
        $notification = Notification::query()->findOrFail($notificationId);

        return response()->json($this->formatNotification($notification));
    }

    public function showBatch(string $batchId): JsonResponse
    {
        $batch = NotificationBatch::query()->findOrFail($batchId);

        return response()->json([
            'batch_id' => $batch->id,
            'status' => $batch->status,
            'total_count' => $batch->total_count,
            'trace_id' => $batch->trace_id,
            'span_id' => $batch->span_id,
            'metadata' => $batch->metadata,
            'notifications' => $batch->notifications()->get()->map(fn (Notification $n) => $this->formatNotification($n)),
        ]);
    }

    public function cancel(string $notificationId): JsonResponse
    {
        $notification = Notification::query()->findOrFail($notificationId);

        if (!in_array($notification->status, ['pending', 'scheduled'], true)) {
            return response()->json([
                'message' => 'Notification cannot be cancelled.',
                'status' => $notification->status,
            ], 409);
        }

        $notification->status = 'cancelled';
        $notification->cancelled_at = now();
        $notification->save();

        return response()->json([
            'message' => 'Notification cancelled.',
            'notification' => $this->formatNotification($notification),
        ]);
    }

    public function cancelBatch(string $batchId): JsonResponse
    {
        $batch = NotificationBatch::query()->findOrFail($batchId);

        $cancelled = $batch->notifications()
            ->whereIn('status', ['pending', 'scheduled', 'retrying'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        $batch->status = 'cancelled';
        $batch->save();

        return response()->json([
            'message' => 'Batch cancelled.',
            'batch_id' => $batch->id,
            'cancelled_count' => $cancelled,
        ]);
    }

    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $query = Notification::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->string('batch_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        $perPage = (int) ($request->input('per_page', 25));
        $notifications = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $notifications->getCollection()->map(fn (Notification $n) => $this->formatNotification($n)),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    private function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'batch_id' => $notification->batch_id,
            'recipient' => $notification->recipient,
            'channel' => $notification->channel,
            'priority' => $notification->priority,
            'content' => $notification->content,
            'status' => $notification->status,
            'attempts' => $notification->attempts,
            'scheduled_at' => optional($notification->scheduled_at)->toIso8601String(),
            'sent_at' => optional($notification->sent_at)->toIso8601String(),
            'cancelled_at' => optional($notification->cancelled_at)->toIso8601String(),
            'provider_message_id' => $notification->provider_message_id,
            'last_error' => $notification->last_error,
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }

    private function findExistingBatch(?array $batchData): ?NotificationBatch
    {
        $idempotencyKey = $batchData['idempotency_key'] ?? null;
        if (!$idempotencyKey) {
            return null;
        }

        return NotificationBatch::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function createBatchIfNeeded(
        ?array $batchData,
        array $notificationsData,
        ?string $correlationId,
        ?string $traceId,
        ?string $spanId
    ): ?NotificationBatch {
        if (!$batchData && count($notificationsData) <= 1) {
            return null;
        }

        $metadata = $batchData['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $metadata = array_merge([
            'trace_id' => $traceId,
            'span_id' => $spanId,
        ], $metadata);

        return NotificationBatch::create([
            'idempotency_key' => $batchData['idempotency_key'] ?? null,
            'correlation_id' => $batchData['correlation_id'] ?? $correlationId,
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'status' => 'pending',
            'total_count' => count($notificationsData),
            'metadata' => $metadata,
        ]);
    }

    private function ensureNotificationIdempotent(?string $idempotencyKey): void
    {
        if (!$idempotencyKey) {
            return;
        }

        $existing = Notification::query()->where('idempotency_key', $idempotencyKey)->first();
        if (!$existing) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Notification idempotency key already exists.',
            'idempotency_key' => $idempotencyKey,
            'notification_id' => $existing->id,
        ], 409));
    }

    private function parseScheduledAt(array $notificationData): ?Carbon
    {
        return isset($notificationData['scheduled_at'])
            ? Carbon::parse($notificationData['scheduled_at'])
            : null;
    }

    private function resolveStatus(?Carbon $scheduledAt): string
    {
        return $scheduledAt && $scheduledAt->isFuture() ? 'scheduled' : 'pending';
    }

    private function resolveContent(array $notificationData, TemplateRenderer $renderer): ?string
    {
        $content = $notificationData['content'] ?? null;
        if ($content || empty($notificationData['template_id'])) {
            return $content;
        }

        $template = NotificationTemplate::query()->findOrFail($notificationData['template_id']);
        $variables = array_merge(
            $template->default_variables ?? [],
            $notificationData['variables'] ?? []
        );

        return $renderer->render($template->content, $variables);
    }

    private function assertContentWithinLimits(string $channel, ?string $content): void
    {
        $limits = config('notifications.content_limits');
        if (!isset($limits[$channel])) {
            return;
        }

        if (mb_strlen($content ?? '') <= $limits[$channel]) {
            return;
        }

        throw ValidationException::withMessages([
            'notifications' => ["Rendered content exceeds {$limits[$channel]} characters for {$channel}."],
        ]);
    }
}
