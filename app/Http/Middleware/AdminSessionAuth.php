<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSessionAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('admin_user_id')) {
            return redirect('/admin/login');
        }

        return $next($request);
    }
}
