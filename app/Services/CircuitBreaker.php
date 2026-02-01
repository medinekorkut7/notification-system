<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    /**
     * Lua script for atomic failure recording with threshold check.
     * Increments counter, sets expiry, and opens circuit if threshold reached.
     */
    private const LUA_RECORD_FAILURE = <<<'LUA'
        local count = redis.call('INCR', KEYS[1])
        redis.call('EXPIRE', KEYS[1], ARGV[1])
        if tonumber(count) >= tonumber(ARGV[2]) then
            redis.call('SETEX', KEYS[2], ARGV[3], '1')
            redis.call('DEL', KEYS[1])
        end
        return count
    LUA;

    /**
     * Lua script for atomic probe acquisition with expiry.
     * Returns 1 if probe acquired, 0 otherwise.
     */
    private const LUA_ALLOW_PROBE = <<<'LUA'
        local acquired = redis.call('SETNX', KEYS[1], ARGV[1])
        if acquired == 1 then
            redis.call('EXPIRE', KEYS[1], ARGV[2])
        end
        return acquired
    LUA;

    public function allow(string $channel): bool
    {
        if (!Redis::exists($this->openKey($channel))) {
            return true;
        }

        return $this->allowProbe($channel);
    }

    public function recordSuccess(string $channel): void
    {
        // Pipeline multiple DEL operations into single round-trip
        Redis::pipeline(function ($pipe) use ($channel) {
            $pipe->del($this->failuresKey($channel));
            $pipe->del($this->openKey($channel));
            $pipe->del($this->probeKey($channel));
        });
    }

    public function recordFailure(string $channel): void
    {
        $threshold = (int) config('notifications.circuit_breaker.failure_threshold', 5);
        $window = (int) config('notifications.circuit_breaker.window_seconds', 60);
        $openSeconds = (int) config('notifications.circuit_breaker.open_seconds', 30);

        // Atomic failure recording using Lua script
        Redis::eval(
            self::LUA_RECORD_FAILURE,
            2,
            $this->failuresKey($channel),
            $this->openKey($channel),
            $window,
            $threshold,
            $openSeconds
        );
    }

    private function allowProbe(string $channel): bool
    {
        $probeSeconds = (int) config('notifications.circuit_breaker.probe_seconds', 5);

        // Atomic probe acquisition using Lua script
        $acquired = Redis::eval(
            self::LUA_ALLOW_PROBE,
            1,
            $this->probeKey($channel),
            (string) time(),
            max(1, $probeSeconds)
        );

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
