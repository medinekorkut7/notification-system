<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class ClientRateLimit
{
    /**
     * Lua script for atomic increment with expiry.
     * This prevents race conditions where the key could exist without an expiry.
     */
    private const LUA_INCR_WITH_EXPIRE = <<<'LUA'
        local count = redis.call('INCR', KEYS[1])
        if count == 1 then
            redis.call('EXPIRE', KEYS[1], ARGV[1])
        end
        -- Ensure expiry exists even if key was created without one (recovery from crash)
        if redis.call('TTL', KEYS[1]) == -1 then
            redis.call('EXPIRE', KEYS[1], ARGV[1])
        end
        return count
    LUA;

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->attributes->get('api_key', 'anonymous');
        $limit = (int) config('notifications.rate_limits.per_client_per_minute', 600);
        $key = "notifications:rate:client:{$apiKey}";
        $ttl = 60;

        // Use Lua script for atomic increment-and-expire to prevent race conditions
        $count = Redis::eval(self::LUA_INCR_WITH_EXPIRE, 1, $key, $ttl);

        if ($count > $limit) {
            return response()->json([
                'message' => 'Rate limit exceeded',
                'limit' => $limit,
            ], 429);
        }

        return $next($request);
    }
}
