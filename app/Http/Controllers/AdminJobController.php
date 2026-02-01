<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use App\Models\DeadLetterNotification;
use App\Services\AdminAuditLogger;
use App\Services\DeadLetterRequeueService;
use Symfony\Component\Process\Process;

class AdminJobController extends Controller
{
    private const PAUSE_KEY = 'notifications:processing:paused';
    private const WORKER_GROUP = 'notifications-workers';
    private const STRESS_KEY = 'notifications:stress:last';

    public function __construct(
        private readonly DeadLetterRequeueService $requeueService
    ) {}

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

        if (!$this->requeueService->isValidPriority($priority)) {
            return response()->json([
                'message' => 'Invalid priority.',
            ], 422);
        }

        $items = $this->requeueService->queryForRequeue($limit, $channel);
        $result = $this->requeueService->requeueBatch($items, $delay, $priority);

        app(AdminAuditLogger::class)->log($request, 'admin.dead_letter_requeue_bulk', [
            'limit' => $limit,
            'channel' => $channel,
            'delay_seconds' => $delay,
            'priority' => $priority,
            'requeued' => $result['requeued'],
            'skipped' => $result['skipped'],
        ]);

        return response()->json([
            'message' => 'Dead-letter requeue completed.',
            'requested' => $limit,
            'requeued' => $result['requeued'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function requeueDeadLetter(Request $request, string $deadLetterId): JsonResponse
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

        // Use Symfony Process to avoid shell injection vulnerabilities
        // Process executes commands directly without shell interpretation
        $process = new Process($command);
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return [
                'exit_code' => 1,
                'lines' => ['Process execution failed: ' . $e->getMessage()],
            ];
        }

        $output = $process->getOutput() . $process->getErrorOutput();
        $lines = array_filter(explode("\n", trim($output)), fn ($line) => $line !== '');

        return [
            'exit_code' => $process->getExitCode() ?? 0,
            'lines' => array_values($lines),
        ];
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
