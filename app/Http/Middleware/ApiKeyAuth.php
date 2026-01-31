<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Api-Key');
        $validKeys = array_filter(array_map('trim', explode(',', env('API_KEYS', ''))));

        if (empty($validKeys) || !$apiKey || !in_array($apiKey, $validKeys, true)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
