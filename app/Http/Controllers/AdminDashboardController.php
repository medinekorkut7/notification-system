<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\ChecksAdminRole;
use App\Models\DeadLetterNotification;
use App\Models\Notification;
use App\Services\NotificationTemplateCache;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AdminDashboardController extends Controller
{
    use ChecksAdminRole;
    private const COUNT_TOTAL = 'count(*) as total';

    public function index(Request $request)
    {
        $metrics = $this->gatherMetrics();
        $failureStats = $this->gatherFailureStats();

        return view('admin.dashboard', [
            'metrics' => $metrics,
            'failureStats' => $failureStats,
            'recentNotifications' => Notification::query()
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'templates' => app(NotificationTemplateCache::class)->recent(10),
            'deadLetters' => DeadLetterNotification::query()
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'adminUsers' => AdminUser::query()
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'providerWebhookUrl' => app(\App\Services\NotificationSettings::class)
                ->get('provider_webhook_url', config('notifications.provider.webhook_url')),
            'providerFallbackWebhookUrl' => app(\App\Services\NotificationSettings::class)
                ->get('provider_fallback_webhook_url', config('notifications.provider.fallback_webhook_url')),
            'isAdmin' => $this->isAdmin($request),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherMetrics(): array
    {
        $statusCounts = Notification::query()
            ->select('status', DB::raw(self::COUNT_TOTAL))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $queues = [
            'high' => $this->safeQueueSize(config('notifications.queue_names.high')),
            'normal' => $this->safeQueueSize(config('notifications.queue_names.normal')),
            'low' => $this->safeQueueSize(config('notifications.queue_names.low')),
            'dead' => $this->safeQueueSize(config('notifications.queue_names.dead')),
        ];

        $channels = config('notifications.channels');
        $breakerStatus = [];
        foreach ($channels as $channel) {
            $breakerStatus[$channel] = $this->safeBreakerStatus($channel);
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
            'total' => array_sum($statusCounts),
            'status_counts' => $statusCounts,
            'dead_letter_count' => DeadLetterNotification::query()->count(),
            'queues' => $queues,
            'circuit_breaker' => $breakerStatus,
            'avg_latency_seconds' => $avgLatency ? (float) $avgLatency : null,
        ];
    }

    private function safeQueueSize(string $queue): int
    {
        try {
            return Queue::size($queue);
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function safeBreakerStatus(string $channel): string
    {
        try {
            return Redis::exists("notifications:circuit:open:{$channel}") ? 'open' : 'closed';
        } catch (\Throwable $exception) {
            return 'unknown';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherFailureStats(): array
    {
        $codes = DeadLetterNotification::query()
            ->select('error_code', DB::raw(self::COUNT_TOTAL))
            ->whereNotNull('error_code')
            ->groupBy('error_code')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $channels = DeadLetterNotification::query()
            ->select('channel', DB::raw(self::COUNT_TOTAL))
            ->whereNotNull('channel')
            ->groupBy('channel')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $types = Notification::query()
            ->select('error_type', DB::raw(self::COUNT_TOTAL))
            ->where('status', 'failed')
            ->whereNotNull('error_type')
            ->groupBy('error_type')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'codes' => $codes,
            'channels' => $channels,
            'types' => $types,
            'max_code' => (int) ($codes->max('total') ?? 0),
            'max_channel' => (int) ($channels->max('total') ?? 0),
            'max_type' => (int) ($types->max('total') ?? 0),
        ];
    }
}
