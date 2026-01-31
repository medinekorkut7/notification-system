<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TraceContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceparent = $request->header('traceparent');
        [$traceId, $spanId] = $this->parseTraceparent($traceparent);

        if (!$traceId) {
            $traceId = $this->generateTraceId();
        }

        if (!$spanId) {
            $spanId = $this->generateSpanId();
        }

        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('span_id', $spanId);
        Log::withContext(['trace_id' => $traceId, 'span_id' => $spanId]);

        $response = $next($request);
        $response->headers->set('traceparent', $this->formatTraceparent($traceId, $spanId));

        return $response;
    }

    private function parseTraceparent(?string $traceparent): array
    {
        if (!$traceparent) {
            return [null, null];
        }

        $parts = explode('-', $traceparent);
        if (count($parts) !== 4) {
            return [null, null];
        }

        return [$parts[1] ?? null, $parts[2] ?? null];
    }

    private function generateTraceId(): string
    {
        return Str::uuid()->getHex();
    }

    private function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function formatTraceparent(string $traceId, string $spanId): string
    {
        return "00-{$traceId}-{$spanId}-01";
    }
}
