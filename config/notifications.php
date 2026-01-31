<?php

return [
    'channels' => explode(',', env('NOTIFICATION_CHANNELS', 'sms,email,push')),
    'priorities' => explode(',', env('NOTIFICATION_PRIORITIES', 'high,normal,low')),
    'content_limits' => [
        'sms' => (int) env('NOTIFICATION_SMS_CHAR_LIMIT', 160),
        'email' => (int) env('NOTIFICATION_EMAIL_CHAR_LIMIT', 2000),
        'push' => (int) env('NOTIFICATION_PUSH_CHAR_LIMIT', 240),
    ],
    'rate_limits' => [
        'per_channel_per_second' => (int) env('NOTIFICATION_RATE_LIMIT_PER_SECOND', 100),
        'per_client_per_minute' => (int) env('NOTIFICATION_RATE_LIMIT_PER_CLIENT_MINUTE', 600),
    ],
    'retry' => [
        'base_delay_seconds' => (int) env('NOTIFICATION_RETRY_BASE_DELAY', 2),
        'max_delay_seconds' => (int) env('NOTIFICATION_RETRY_MAX_DELAY', 300),
        'jitter_percent' => (int) env('NOTIFICATION_RETRY_JITTER_PERCENT', 20),
        'processing_timeout_seconds' => (int) env('NOTIFICATION_PROCESSING_TIMEOUT', 300),
        'delivery_ttl_hours' => (int) env('NOTIFICATION_DELIVERY_TTL_HOURS', 24),
    ],
    'provider' => [
        'webhook_url' => env('NOTIFICATION_PROVIDER_WEBHOOK_URL'),
        'timeout_seconds' => (int) env('NOTIFICATION_PROVIDER_TIMEOUT', 5),
        'idempotency_header' => env('NOTIFICATION_PROVIDER_IDEMPOTENCY_HEADER', 'X-Idempotency-Key'),
        'fallback_webhook_url' => env('NOTIFICATION_PROVIDER_FALLBACK_WEBHOOK_URL'),
        'health_failure_threshold' => (int) env('NOTIFICATION_PROVIDER_HEALTH_FAILURE_THRESHOLD', 3),
        'health_window_seconds' => (int) env('NOTIFICATION_PROVIDER_HEALTH_WINDOW_SECONDS', 60),
        'health_open_seconds' => (int) env('NOTIFICATION_PROVIDER_HEALTH_OPEN_SECONDS', 60),
    ],
    'queue_names' => [
        'high' => env('NOTIFICATION_QUEUE_HIGH', 'notifications-high'),
        'normal' => env('NOTIFICATION_QUEUE_NORMAL', 'notifications-normal'),
        'low' => env('NOTIFICATION_QUEUE_LOW', 'notifications-low'),
        'dead' => env('NOTIFICATION_QUEUE_DEAD', 'notifications-dead'),
    ],
    'circuit_breaker' => [
        'failure_threshold' => (int) env('NOTIFICATION_CIRCUIT_FAILURE_THRESHOLD', 5),
        'window_seconds' => (int) env('NOTIFICATION_CIRCUIT_WINDOW', 60),
        'open_seconds' => (int) env('NOTIFICATION_CIRCUIT_OPEN_SECONDS', 30),
        'probe_seconds' => (int) env('NOTIFICATION_CIRCUIT_PROBE_SECONDS', 5),
    ],
    'supervisor' => [
        'bin' => env('NOTIFICATION_SUPERVISORCTL_PATH', '/usr/bin/supervisorctl'),
        'config' => env('NOTIFICATION_SUPERVISOR_CONFIG', '/etc/supervisor/supervisord.conf'),
        'server' => env('NOTIFICATION_SUPERVISOR_SERVER'),
    ],
];
