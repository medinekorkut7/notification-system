<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListNotificationsRequest;
use App\Http\Requests\StoreNotificationsRequest;
use App\Jobs\SendNotificationJob;
use App\Enums\NotificationBatchStatus;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Services\NotificationTemplateCache;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function store(StoreNotificationsRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $notificationsData = $payload['notifications'] ?? [];
        $batchData = $payload['batch'] ?? null;
        $context = $this->buildRequestContext($request);

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

        // Batch check for existing idempotency keys (single query instead of N)
        $existingKeys = $this->getExistingIdempotencyKeys($notificationsData);

        DB::transaction(function () use (
            $notificationsData,
            $batchData,
            $context,
            $existingKeys,
            &$batch,
            &$created,
            &$duplicates,
            &$results
        ) {
            $batch = $this->createBatchIfNeeded(
                $batchData,
                $notificationsData,
                $context['correlation_id'],
                $context['trace_id'],
                $context['span_id']
            );

            $prepared = $this->prepareNotificationsForInsert(
                $notificationsData,
                $batch?->id,
                $existingKeys,
                $context
            );

            $created += $prepared['created'];
            $duplicates += $prepared['duplicates'];
            $results = $prepared['results'];

            $this->insertNotifications($prepared['insert']);
            $this->dispatchNotificationJobs($prepared['jobs']);
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
        $batch = NotificationBatch::query()
            ->with('notifications')
            ->findOrFail($batchId);

        return response()->json([
            'batch_id' => $batch->id,
            'status' => $batch->status,
            'total_count' => $batch->total_count,
            'trace_id' => $batch->trace_id,
            'span_id' => $batch->span_id,
            'metadata' => $batch->metadata,
            'notifications' => $batch->notifications->map(fn (Notification $n) => $this->formatNotification($n)),
        ]);
    }

    public function cancel(string $notificationId): JsonResponse
    {
        $notification = Notification::query()->findOrFail($notificationId);

        if (!in_array($notification->status, [
            NotificationStatus::Pending->value,
            NotificationStatus::Scheduled->value,
        ], true)) {
            return response()->json([
                'message' => 'Notification cannot be cancelled.',
                'status' => $notification->status,
            ], 409);
        }

        $notification->status = NotificationStatus::Cancelled->value;
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
            ->whereIn('status', [
                NotificationStatus::Pending->value,
                NotificationStatus::Scheduled->value,
                NotificationStatus::Retrying->value,
            ])
            ->update([
                'status' => NotificationStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);

        $batch->status = NotificationBatchStatus::Cancelled->value;
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
            'status' => NotificationBatchStatus::Pending->value,
            'total_count' => count($notificationsData),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Check duplicates in batch - single query instead of N queries.
     *
     * @param array $notificationsData
     * @return array<string, bool> Map of idempotency_key => exists
     */
    private function getExistingIdempotencyKeys(array $notificationsData): array
    {
        $keys = array_filter(
            array_column($notificationsData, 'idempotency_key'),
            fn ($key) => $key !== null
        );

        if (empty($keys)) {
            return [];
        }

        return Notification::query()
            ->whereIn('idempotency_key', $keys)
            ->pluck('idempotency_key')
            ->flip()
            ->map(fn () => true)
            ->toArray();
    }

    /**
     * @return array{correlation_id: string|null, trace_id: string|null, span_id: string|null}
     */
    private function buildRequestContext(Request $request): array
    {
        return [
            'correlation_id' => $request->attributes->get('correlation_id'),
            'trace_id' => $request->attributes->get('trace_id'),
            'span_id' => $request->attributes->get('span_id'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $notificationsData
     * @param array<string, bool> $existingKeys
     * @param array{correlation_id: string|null, trace_id: string|null, span_id: string|null} $context
     * @return array{
     *   insert: array<int, array<string, mixed>>,
     *   jobs: array<int, array{id: string, priority: string}>,
     *   results: array<int, array<string, mixed>>,
     *   created: int,
     *   duplicates: int
     * }
     */
    private function prepareNotificationsForInsert(
        array $notificationsData,
        ?string $batchId,
        array $existingKeys,
        array $context
    ): array {
        $renderer = app(TemplateRenderer::class);
        $notificationsToInsert = [];
        $jobsToDispatch = [];
        $results = [];
        $created = 0;
        $duplicates = 0;
        $now = now();

        foreach ($notificationsData as $notificationData) {
            $idempotencyKey = $notificationData['idempotency_key'] ?? null;
            if ($this->isDuplicateNotification($idempotencyKey, $existingKeys, $duplicates)) {
                continue;
            }

            $priority = $notificationData['priority'] ?? 'normal';
            $scheduledAt = $this->parseScheduledAt($notificationData);
            $status = $this->resolveStatus($scheduledAt);

            $content = $this->resolveContent($notificationData, $renderer);
            $this->assertContentWithinLimits($notificationData['channel'], $content);

            $notificationId = (string) Str::uuid();
            $notificationsToInsert[] = [
                'id' => $notificationId,
                'batch_id' => $batchId,
                'channel' => $notificationData['channel'],
                'priority' => $priority,
                'recipient' => $notificationData['recipient'],
                'content' => $content ?? '',
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => $notificationData['correlation_id'] ?? $context['correlation_id'],
                'trace_id' => $context['trace_id'],
                'span_id' => $context['span_id'],
                'scheduled_at' => $scheduledAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $results[] = [
                'id' => $notificationId,
                'batch_id' => $batchId,
                'recipient' => $notificationData['recipient'],
                'channel' => $notificationData['channel'],
                'priority' => $priority,
                'content' => $content ?? '',
                'status' => $status,
                'attempts' => 0,
                'scheduled_at' => $scheduledAt?->toIso8601String(),
                'sent_at' => null,
                'cancelled_at' => null,
                'provider_message_id' => null,
                'last_error' => null,
                'created_at' => $now->toIso8601String(),
            ];

            if ($status === NotificationStatus::Pending->value) {
                $jobsToDispatch[] = [
                    'id' => $notificationId,
                    'priority' => $priority,
                ];
            }

            $created++;
        }

        return [
            'insert' => $notificationsToInsert,
            'jobs' => $jobsToDispatch,
            'results' => $results,
            'created' => $created,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $notificationsToInsert
     */
    private function insertNotifications(array $notificationsToInsert): void
    {
        foreach (array_chunk($notificationsToInsert, 100) as $chunk) {
            Notification::insert($chunk);
        }
    }

    /**
     * @param array<int, array{id: string, priority: string}> $jobsToDispatch
     */
    private function dispatchNotificationJobs(array $jobsToDispatch): void
    {
        foreach ($jobsToDispatch as $job) {
            dispatch((new SendNotificationJob($job['id']))
                ->onQueue(config('notifications.queue_names.' . $job['priority'], 'notifications-normal')));
        }
    }

    private function isDuplicateNotification(?string $idempotencyKey, array $existingKeys, int &$duplicates): bool
    {
        if (!$idempotencyKey) {
            return false;
        }

        if (!isset($existingKeys[$idempotencyKey])) {
            return false;
        }

        $duplicates++;
        return true;
    }

    private function parseScheduledAt(array $notificationData): ?Carbon
    {
        return isset($notificationData['scheduled_at'])
            ? Carbon::parse($notificationData['scheduled_at'])
            : null;
    }

    private function resolveStatus(?Carbon $scheduledAt): string
    {
        return $scheduledAt && $scheduledAt->isFuture()
            ? NotificationStatus::Scheduled->value
            : NotificationStatus::Pending->value;
    }

    private function resolveContent(array $notificationData, TemplateRenderer $renderer): ?string
    {
        $content = $notificationData['content'] ?? null;
        if ($content || empty($notificationData['template_id'])) {
            return $content;
        }

        $template = app(NotificationTemplateCache::class)->getOrFail($notificationData['template_id']);
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
