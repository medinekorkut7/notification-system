<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\DeadLetterNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    /**
     * Cache TTL in seconds for metrics.
     */
    private const METRICS_CACHE_TTL = 30;

    public function index(): JsonResponse
    {
        $metrics = $this->getCachedMetrics();

        return response()->json([
            'queues' => $metrics['queues'],
            'status_counts' => $metrics['status_counts'],
            'dead_letter_count' => $metrics['dead_letter_count'],
            'circuit_breaker' => $metrics['circuit_breaker'],
            'avg_latency_seconds' => $metrics['avg_latency_seconds'],
        ]);
    }

    public function prometheus(): Response
    {
        $metrics = $this->getCachedMetrics();

        $lines = [];
        $lines[] = '# HELP notification_queue_depth Number of jobs waiting in each queue.';
        $lines[] = '# TYPE notification_queue_depth gauge';
        foreach ($metrics['queues'] as $queue => $depth) {
            $lines[] = sprintf('notification_queue_depth{queue="%s"} %d', $queue, $depth);
        }

        $lines[] = '# HELP notification_status_total Count of notifications by status.';
        $lines[] = '# TYPE notification_status_total gauge';
        foreach ($metrics['status_counts'] as $status => $total) {
            $lines[] = sprintf('notification_status_total{status="%s"} %d', $status, $total);
        }

        $lines[] = '# HELP notification_dead_letter_total Total dead-letter notifications.';
        $lines[] = '# TYPE notification_dead_letter_total gauge';
        $lines[] = sprintf('notification_dead_letter_total %d', $metrics['dead_letter_count']);

        $lines[] = '# HELP notification_circuit_breaker_state Circuit breaker state by channel (1=open, 0=closed).';
        $lines[] = '# TYPE notification_circuit_breaker_state gauge';
        foreach ($metrics['circuit_breaker'] as $channel => $state) {
            $lines[] = sprintf(
                'notification_circuit_breaker_state{channel="%s"} %d',
                $channel,
                $state === 'open' ? 1 : 0
            );
        }

        $lines[] = '# HELP notification_avg_latency_seconds Average time between created and sent.';
        $lines[] = '# TYPE notification_avg_latency_seconds gauge';
        $lines[] = sprintf(
            'notification_avg_latency_seconds %s',
            $metrics['avg_latency_seconds'] === null ? 'NaN' : (string) $metrics['avg_latency_seconds']
        );

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getCachedMetrics(): array
    {
        return cache()->remember('metrics:all', self::METRICS_CACHE_TTL, fn() => $this->gatherMetrics());
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherMetrics(): array
    {
        // Cache queue names config
        $queueNames = [
            'high' => config('notifications.queue_names.high'),
            'normal' => config('notifications.queue_names.normal'),
            'low' => config('notifications.queue_names.low'),
            'dead' => config('notifications.queue_names.dead'),
        ];

        $queues = [
            'high' => Queue::size($queueNames['high']),
            'normal' => Queue::size($queueNames['normal']),
            'low' => Queue::size($queueNames['low']),
            'dead' => Queue::size($queueNames['dead']),
        ];

        $statusCounts = Notification::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $deadCount = DeadLetterNotification::query()->count();

        // Use Redis pipeline for circuit breaker status checks
        $channels = config('notifications.channels');
        $breakerKeys = array_map(fn($ch) => "notifications:circuit:open:{$ch}", $channels);
        
        $breakerStatus = [];
        if (!empty($breakerKeys)) {
            $results = Redis::pipeline(function ($pipe) use ($breakerKeys) {
                foreach ($breakerKeys as $key) {
                    $pipe->exists($key);
                }
            });
            
            foreach ($channels as $i => $channel) {
                $breakerStatus[$channel] = ($results[$i] ?? false) ? 'open' : 'closed';
            }
        }

        // Limit latency calculation to last 24 hours for performance
        $driver = DB::getDriverName();
        $latencyQuery = Notification::query()
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subHours(24));

        if ($driver === 'sqlite') {
            $avgLatency = $latencyQuery
                ->selectRaw('AVG(strftime("%s", sent_at) - strftime("%s", created_at)) as avg_latency_seconds')
                ->value('avg_latency_seconds');
        } else {
            $avgLatency = $latencyQuery
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_latency_seconds')
                ->value('avg_latency_seconds');
        }

        return [
            'queues' => $queues,
            'status_counts' => $statusCounts,
            'dead_letter_count' => $deadCount,
            'circuit_breaker' => $breakerStatus,
            'avg_latency_seconds' => $avgLatency ? (float) $avgLatency : null,
        ];
    }
}
