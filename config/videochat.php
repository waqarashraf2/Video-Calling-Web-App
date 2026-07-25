<?php

return [
    'session_ttl_minutes' => (int) env('VIDEOCHAT_SESSION_TTL_MINUTES', 45),
    'heartbeat_ttl_seconds' => min(20, max(10, (int) env('VIDEOCHAT_HEARTBEAT_TTL_SECONDS', 15))),
    'room_ttl_minutes' => (int) env('VIDEOCHAT_ROOM_TTL_MINUTES', 90),
    'matchmaking_timeout_seconds' => (int) env('VIDEOCHAT_MATCHMAKING_TIMEOUT_SECONDS', 45),
    'rematch_cooldown_seconds' => (int) env('VIDEOCHAT_REMATCH_COOLDOWN_SECONDS', 120),
    'report_retention_days' => (int) env('VIDEOCHAT_REPORT_RETENTION_DAYS', 180),
    'block_retention_days' => (int) env('VIDEOCHAT_BLOCK_RETENTION_DAYS', 30),
    'fingerprint_rotation_key' => env('VIDEOCHAT_FINGERPRINT_KEY', env('APP_KEY')),
    'fingerprint_rotation_days' => (int) env('VIDEOCHAT_FINGERPRINT_ROTATION_DAYS', 7),
    'signal_max_bytes' => (int) env('VIDEOCHAT_SIGNAL_MAX_BYTES', 12000),
    'queue_store' => env('VIDEOCHAT_QUEUE_STORE', env('CACHE_STORE', 'database')),
    'rate_limits' => [
        'join' => env('VIDEOCHAT_JOIN_LIMIT', '30,1'),
        'skip' => env('VIDEOCHAT_SKIP_LIMIT', '20,1'),
        'report' => env('VIDEOCHAT_REPORT_LIMIT', '6,10'),
        'signal' => env('VIDEOCHAT_SIGNAL_LIMIT', '120,1'),
        'heartbeat' => env('VIDEOCHAT_HEARTBEAT_LIMIT', '60,1'),
    ],
];
