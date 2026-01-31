<?php

namespace App\Services;

class RetryPolicy
{
    public function computeDelaySeconds(int $attemptNumber): int
    {
        $base = (int) config('notifications.retry.base_delay_seconds', 2);
        $max = (int) config('notifications.retry.max_delay_seconds', 300);
        $jitter = (int) config('notifications.retry.jitter_percent', 20);

        $delay = $base * (2 ** max(0, $attemptNumber - 1));
        $delay = min($delay, $max);

        $jitterRange = (int) round($delay * ($jitter / 100));
        if ($jitterRange > 0) {
            $delay += random_int(-$jitterRange, $jitterRange);
        }

        return max(1, $delay);
    }

    public function circuitBreakerOpenDelay(): int
    {
        return (int) config('notifications.circuit_breaker.open_seconds', 30);
    }
}
