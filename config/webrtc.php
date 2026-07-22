<?php

return [
    'ice_servers' => [
        [
            'urls' => array_values(array_filter(array_map('trim', explode(',', env('WEBRTC_STUN_URLS', 'stun:stun.l.google.com:19302'))))),
        ],
        [
            'urls' => array_values(array_filter(array_map('trim', explode(',', env('WEBRTC_TURN_URLS', ''))))),
            'username' => env('WEBRTC_TURN_USERNAME'),
            'credential' => env('WEBRTC_TURN_CREDENTIAL'),
        ],
    ],
    'connection_timeout_seconds' => (int) env('WEBRTC_CONNECTION_TIMEOUT_SECONDS', 25),
];
