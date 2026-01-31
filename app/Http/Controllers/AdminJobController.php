<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use App\Models\DeadLetterNotification;
use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Str;
use App\Services\AdminAuditLogger;

class AdminJobController extends Controller
{
    private const PAUSE_KEY = 'notifications:processing:paused';
    private const WORKER_GROUP = 'notifications-workers';
    private const STRESS_KEY = 'notifications:stress:last';

    public function status(): JsonResponse
    {
        return response()->json([
            'paused' => $this->isPaused(),
        ]);
    }

    public function pause(): JsonResponse
    {
        Redis::set(self::PAUSE_KEY, '1');

        app(AdminAuditLogger::class)->log(request(), 'admin.processing_paused');

        return response()->json([
            'paused' => true,
            'message' => 'Processing paused.',
        ]);
    }

    public function resume(): JsonResponse
    {
        Redis::del(self::PAUSE_KEY);

        app(AdminAuditLogger::class)->log(request(), 'admin.processing_resumed');

        return response()->json([
            'paused' => false,
            'message' => 'Processing resumed.',
        ]);
    }

    public function restart(): JsonResponse
    {
        try {
            Artisan::call('queue:restart');
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Failed to signal queue restart.',
            ], 500);
        }

        app(AdminAuditLogger::class)->log(request(), 'admin.queue_restart');

        return response()->json([
            'message' => 'Queue restart signal sent.',
        ]);
    }

    public function stress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:5000'],
            'batch' => ['required', 'integer', 'min:1', 'max:1000'],
            'channel' => ['required', 'in:sms,email,push'],
            'priority' => ['required', 'in:high,normal,low'],
        ]);

        app(AdminAuditLogger::class)->log($request, 'admin.stress_start', $data);

        $this->storeStressStatus([
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'payload' => $data,
        ]);

        try {
            Artisan::call('notifications:stress', [
                '--count' => $data['count'],
                '--batch' => $data['batch'],
                '--channel' => $data['channel'],
                '--priority' => $data['priority'],
            ]);
        } catch (\Throwable $exception) {
            $this->storeStressStatus([
                'status' => 'failed',
                'finished_at' => now()->toIso8601String(),
                'payload' => $data,
                'error' => $exception->getMessage(),
            ]);
            app(AdminAuditLogger::class)->log($request, 'admin.stress_failed', [
                'error' => $exception->getMessage(),
            ] + $data);
            return response()->json([
                'message' => 'Failed to start stress test.',
            ], 500);
        }

        $this->storeStressStatus([
            'status' => 'completed',
            'finished_at' => now()->toIso8601String(),
            'payload' => $data,
            'output' => trim((string) Artisan::output()),
        ]);

        app(AdminAuditLogger::class)->log($request, 'admin.stress_completed', $data);

        return response()->json([
            'message' => 'Stress test started.',
        ]);
    }

    public function stressStatus(): JsonResponse
    {
        $payload = $this->getStressStatus();

        return response()->json($payload ?? [
            'status' => 'idle',
        ]);
    }

    public function providerSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_webhook_url' => ['required', 'url'],
            'provider_fallback_webhook_url' => ['nullable', 'url'],
        ]);

        $settings = app(\App\Services\NotificationSettings::class);
        $settings->set('provider_webhook_url', $data['provider_webhook_url']);
        $settings->set('provider_fallback_webhook_url', $data['provider_fallback_webhook_url'] ?? null);

        app(AdminAuditLogger::class)->log($request, 'admin.provider_settings_updated', [
            'provider_webhook_url' => $data['provider_webhook_url'],
            'provider_fallback_webhook_url' => $data['provider_fallback_webhook_url'] ?? null,
        ]);

        return response()->json([
            'message' => 'Provider settings saved.',
        ]);
    }

    public function requeueDeadLetters(Request $request): JsonResponse
    {
        $limit = max(1, (int) $request->input('limit', 100));
        $channel = (string) $request->input('channel', '');
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

        app(AdminAuditLogger::class)->log($request, 'admin.dead_letter_requeue_bulk', [
            'limit' => $limit,
            'channel' => $channel,
            'delay_seconds' => $delay,
            'priority' => $priority,
            'requeued' => $requeued,
            'skipped' => $skipped,
        ]);

        return response()->json([
            'message' => 'Dead-letter requeue completed.',
            'requested' => $limit,
            'requeued' => $requeued,
            'skipped' => $skipped,
        ]);
    }

    public function requeueDeadLetter(Request $request, string $deadLetterId): JsonResponse
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

        app(AdminAuditLogger::class)->log($request, 'admin.dead_letter_requeue_single', [
            'dead_letter_id' => $deadLetterId,
            'delay_seconds' => $delay,
            'priority' => $priority,
            'notification_id' => $result['notification_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Dead-letter notification requeued.',
            'notification_id' => $result['notification_id'],
        ], 201);
    }

    public function workersStatus(): JsonResponse
    {
        return response()->json($this->runSupervisor(['status', self::WORKER_GROUP . ':*']));
    }

    public function workersStart(): JsonResponse
    {
        app(AdminAuditLogger::class)->log(request(), 'admin.workers_start');
        return response()->json($this->runSupervisor(['start', self::WORKER_GROUP . ':*']));
    }

    public function workersStop(): JsonResponse
    {
        app(AdminAuditLogger::class)->log(request(), 'admin.workers_stop');
        return response()->json($this->runSupervisor(['stop', self::WORKER_GROUP . ':*']));
    }

    private function isPaused(): bool
    {
        return (bool) Redis::exists(self::PAUSE_KEY);
    }

    /**
     * @param array<int, string> $args
     * @return array<string, mixed>
     */
    private function runSupervisor(array $args): array
    {
        $bin = (string) config('notifications.supervisor.bin', '/usr/bin/supervisorctl');
        $configPath = (string) config('notifications.supervisor.config', '/etc/supervisor/supervisord.conf');
        $server = config('notifications.supervisor.server');

        if ($bin === '' || !is_executable($bin)) {
            return [
                'exit_code' => 127,
                'lines' => ["supervisorctl not found or not executable at: {$bin}"],
            ];
        }

        $command = [$bin];

        if ($configPath !== '') {
            if (!file_exists($configPath)) {
                return [
                    'exit_code' => 127,
                    'lines' => ["Supervisor config not found at: {$configPath}"],
                ];
            }
            $command[] = '-c';
            $command[] = $configPath;
        }

        if (!empty($server)) {
            $command[] = '-s';
            $command[] = (string) $server;
        }

        $command = array_merge($command, $args);
        $escaped = array_map('escapeshellarg', $command);
        $fullCommand = implode(' ', $escaped);

        $output = [];
        $exitCode = 0;
        @exec($fullCommand . ' 2>&1', $output, $exitCode);

        return [
            'exit_code' => $exitCode,
            'lines' => $output,
        ];
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

    /**
     * @param array<string, mixed> $payload
     */
    private function storeStressStatus(array $payload): void
    {
        Redis::set(self::STRESS_KEY, json_encode($payload));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getStressStatus(): ?array
    {
        $raw = Redis::get(self::STRESS_KEY);
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
