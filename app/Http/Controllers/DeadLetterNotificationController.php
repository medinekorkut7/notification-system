<?php

namespace App\Http\Controllers;

use App\Models\DeadLetterNotification;
use App\Services\DeadLetterRequeueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeadLetterNotificationController extends Controller
{
    public function __construct(
        private readonly DeadLetterRequeueService $requeueService
    ) {}

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

        if (!$this->requeueService->isValidPriority($priority)) {
            return response()->json([
                'message' => 'Invalid priority.',
            ], 422);
        }

        $result = $this->requeueService->requeueSingle($item, $delay, $priority);

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

        if (!$this->requeueService->isValidPriority($priority)) {
            return response()->json([
                'message' => 'Invalid priority.',
            ], 422);
        }

        $items = $this->requeueService->queryForRequeue($limit, $channel);
        $result = $this->requeueService->requeueBatch($items, $delay, $priority);

        return response()->json([
            'message' => 'Dead-letter requeue completed.',
            'requested' => $limit,
            'requeued' => $result['requeued'],
            'skipped' => $result['skipped'],
        ]);
    }
}
