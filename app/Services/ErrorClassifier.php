<?php

namespace App\Services;

class ErrorClassifier
{
    public function classify(?int $httpStatus, ?\Throwable $exception = null): array
    {
        if ($httpStatus !== null) {
            if (in_array($httpStatus, [408, 425, 429], true)) {
                return ['transient', "http_$httpStatus"];
            }

            if ($httpStatus >= 500) {
                return ['transient', "http_$httpStatus"];
            }

            if ($httpStatus >= 400) {
                return ['permanent', "http_$httpStatus"];
            }
        }

        if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
            return ['transient', 'connection_error'];
        }

        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
            return ['transient', 'request_exception'];
        }

        return ['unknown', 'unknown'];
    }
}
