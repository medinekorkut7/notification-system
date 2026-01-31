<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StressNotificationsCommand extends Command
{
    protected $signature = 'notifications:stress
        {--count=1000 : Total number of notifications to create}
        {--batch=200 : Batch size per request}
        {--channel=sms : sms|email|push}
        {--priority=normal : high|normal|low}
        {--mode=direct : direct|api}
        {--api-url=http://localhost:8000/api/v1/notifications : API URL when mode=api}
        {--api-key= : X-Api-Key when mode=api}
        {--content=Stress test message : Notification content}
        {--sleep-ms=0 : Sleep between batches in milliseconds}';

    protected $description = 'Generate a burst of notifications to stress-test the system';

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        $batchSize = min(1000, max(1, (int) $this->option('batch')));
        $channel = (string) $this->option('channel');
        $priority = (string) $this->option('priority');
        $mode = (string) $this->option('mode');
        $content = (string) $this->option('content');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info("Starting stress test: {$count} notifications, batch={$batchSize}, mode={$mode}");

        $remaining = $count;
        $batchIndex = 1;

        while ($remaining > 0) {
            $currentBatch = min($batchSize, $remaining);

            if ($mode === 'api') {
                $this->sendViaApi($currentBatch, $batchIndex, $channel, $priority, $content);
            } else {
                $this->sendDirect($currentBatch, $batchIndex, $channel, $priority, $content);
            }

            $remaining -= $currentBatch;
            $batchIndex++;

            if ($sleepMs > 0 && $remaining > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->info('Stress test completed.');
        return self::SUCCESS;
    }

    private function sendDirect(int $count, int $batchIndex, string $channel, string $priority, string $content): void
    {
        $batch = NotificationBatch::create([
            'idempotency_key' => "stress-batch-{$batchIndex}-" . Str::uuid(),
            'correlation_id' => (string) Str::uuid(),
            'status' => 'pending',
            'total_count' => $count,
            'metadata' => ['source' => 'stress-test'],
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $notification = Notification::create([
                'batch_id' => $batch->id,
                'channel' => $channel,
                'priority' => $priority,
                'recipient' => $this->fakeRecipient($channel, $batchIndex, $i),
                'content' => $content,
                'status' => 'pending',
                'idempotency_key' => "stress-{$batchIndex}-{$i}-" . Str::uuid(),
                'correlation_id' => (string) Str::uuid(),
                'attempts' => 0,
                'max_attempts' => 5,
            ]);

            dispatch((new SendNotificationJob($notification->id))
                ->onQueue(config('notifications.queue_names.' . $priority, 'notifications-normal')));
        }

        $this->line("Batch {$batchIndex}: queued {$count} notifications.");
    }

    private function sendViaApi(int $count, int $batchIndex, string $channel, string $priority, string $content): void
    {
        $apiUrl = (string) $this->option('api-url');
        $apiKey = (string) $this->option('api-key');

        $payload = [
            'batch' => [
                'idempotency_key' => "stress-batch-{$batchIndex}",
            ],
            'notifications' => [],
        ];

        for ($i = 1; $i <= $count; $i++) {
            $payload['notifications'][] = [
                'recipient' => $this->fakeRecipient($channel, $batchIndex, $i),
                'channel' => $channel,
                'content' => $content,
                'priority' => $priority,
                'idempotency_key' => "stress-{$batchIndex}-{$i}",
            ];
        }

        $response = Http::withHeaders([
            'X-Api-Key' => $apiKey,
            'X-Correlation-Id' => (string) Str::uuid(),
        ])->post($apiUrl, $payload);

        if (!$response->successful()) {
            $this->error("Batch {$batchIndex}: API error {$response->status()}");
            $this->line($response->body());
            return;
        }

        $this->line("Batch {$batchIndex}: API accepted {$count} notifications.");
    }

    private function fakeRecipient(string $channel, int $batchIndex, int $i): string
    {
        return match ($channel) {
            'email' => "user{$batchIndex}{$i}@example.com",
            'push' => "device-{$batchIndex}-{$i}",
            default => '+905551234567',
        };
    }
}
