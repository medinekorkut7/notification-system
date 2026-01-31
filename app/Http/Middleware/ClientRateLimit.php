<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class ClientRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->attributes->get('api_key', 'anonymous');
        $limit = (int) config('notifications.rate_limits.per_client_per_minute', 600);
        $key = "notifications:rate:client:{$apiKey}";

        $count = Redis::incr($key);
        if ($count === 1) {
            Redis::expire($key, 60);
        }

        if ($count > $limit) {
            return response()->json([
                'message' => 'Rate limit exceeded',
                'limit' => $limit,
            ], 429);
        }

        return $next($request);
    }
}
