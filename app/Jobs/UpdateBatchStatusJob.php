<?php

namespace App\Jobs;

use App\Enums\NotificationBatchStatus;
use App\Enums\NotificationStatus;
use App\Models\NotificationBatch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

// Job that handles batch status recalculation
// Implements ShouldBeUnique to prevent duplicate calculations within 5 seconds
// Debounces multiple notification saves into a single batch status update

class UpdateBatchStatusJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 5;

    public function __construct(public string $batchId)
    {
    }

    /**
     * The unique ID of the job (prevents duplicate jobs for same batch).
     */
    public function uniqueId(): string
    {
        return $this->batchId;
    }

    public function handle(): void
    {
        $batch = NotificationBatch::query()->find($this->batchId);

        if (!$batch) {
            return;
        }

        $counts = $batch->notifications()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $newStatus = $this->determineStatus($counts);

        if ($batch->status !== $newStatus) {
            $batch->status = $newStatus;
            $batch->save();
        }
    }

    /**
     * @param \Illuminate\Support\Collection<string, int> $counts
     */
    private function determineStatus($counts): string
    {
        $pending = $counts[NotificationStatus::Pending->value] ?? 0;
        $processing = $counts[NotificationStatus::Processing->value] ?? 0;
        $scheduled = $counts[NotificationStatus::Scheduled->value] ?? 0;
        $retrying = $counts[NotificationStatus::Retrying->value] ?? 0;
        $failed = $counts[NotificationStatus::Failed->value] ?? 0;
        $cancelled = $counts[NotificationStatus::Cancelled->value] ?? 0;

        if ($pending > 0 || $processing > 0 || $scheduled > 0 || $retrying > 0) {
            return NotificationBatchStatus::Pending->value;
        }

        if ($failed > 0) {
            return NotificationBatchStatus::Failed->value;
        }

        if ($cancelled > 0 && $counts->sum() === $cancelled) {
            return NotificationBatchStatus::Cancelled->value;
        }

        return NotificationBatchStatus::Completed->value;
    }
}
