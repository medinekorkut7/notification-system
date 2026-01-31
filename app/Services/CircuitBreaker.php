<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    public function allow(string $channel): bool
    {
        if (!Redis::exists($this->openKey($channel))) {
            return true;
        }

        return $this->allowProbe($channel);
    }

    public function recordSuccess(string $channel): void
    {
        Redis::del($this->failuresKey($channel));
        Redis::del($this->openKey($channel));
        Redis::del($this->probeKey($channel));
    }

    public function recordFailure(string $channel): void
    {
        $threshold = (int) config('notifications.circuit_breaker.failure_threshold', 5);
        $window = (int) config('notifications.circuit_breaker.window_seconds', 60);
        $openSeconds = (int) config('notifications.circuit_breaker.open_seconds', 30);

        $key = $this->failuresKey($channel);
        $count = Redis::incr($key);
        Redis::expire($key, $window);

        if ($count >= $threshold) {
            Redis::setex($this->openKey($channel), $openSeconds, '1');
            Redis::del($key);
        }
    }

    private function allowProbe(string $channel): bool
    {
        $probeSeconds = (int) config('notifications.circuit_breaker.probe_seconds', 5);
        $probeKey = $this->probeKey($channel);

        $acquired = Redis::setnx($probeKey, (string) time());
        if ($acquired) {
            Redis::expire($probeKey, max(1, $probeSeconds));
        }

        return (bool) $acquired;
    }

    private function failuresKey(string $channel): string
    {
        return "notifications:circuit:failures:{$channel}";
    }

    private function openKey(string $channel): string
    {
        return "notifications:circuit:open:{$channel}";
    }

    private function probeKey(string $channel): string
    {
        return "notifications:circuit:probe:{$channel}";
    }
}
