<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) env('ADMIN_BASIC_USER', '');
        $password = (string) env('ADMIN_BASIC_PASSWORD', '');

        if ($username === '' || $password === '') {
            return response()->json(['message' => 'Admin auth is not configured.'], 503);
        }

        $providedUser = (string) $request->getUser();
        $providedPass = (string) $request->getPassword();

        if (!hash_equals($username, $providedUser) || !hash_equals($password, $providedPass)) {
            return response()->make('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Admin Dashboard"',
            ]);
        }

        return $next($request);
    }
}
