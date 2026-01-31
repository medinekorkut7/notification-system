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
    public function index(): JsonResponse
    {
        $metrics = $this->gatherMetrics();

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
        $metrics = $this->gatherMetrics();

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
    private function gatherMetrics(): array
    {
        $queues = [
            'high' => Queue::size(config('notifications.queue_names.high')),
            'normal' => Queue::size(config('notifications.queue_names.normal')),
            'low' => Queue::size(config('notifications.queue_names.low')),
            'dead' => Queue::size(config('notifications.queue_names.dead')),
        ];

        $statusCounts = Notification::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $deadCount = DeadLetterNotification::query()->count();

        $channels = config('notifications.channels');
        $breakerStatus = [];
        foreach ($channels as $channel) {
            $breakerStatus[$channel] = Redis::exists("notifications:circuit:open:{$channel}") ? 'open' : 'closed';
        }

        $driver = DB::getDriverName();
        $latencyQuery = Notification::query()->whereNotNull('sent_at');

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
