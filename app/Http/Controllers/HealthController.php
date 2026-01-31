<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $dbOk = true;
        $redisOk = true;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $dbOk = false;
        }

        try {
            Redis::ping();
        } catch (\Throwable $exception) {
            $redisOk = false;
        }

        $status = ($dbOk && $redisOk) ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'dependencies' => [
                'database' => $dbOk ? 'ok' : 'error',
                'redis' => $redisOk ? 'ok' : 'error',
            ],
        ], $status === 'ok' ? 200 : 503);
    }
}
