<?php

return [
    'paths' => ['api/*', 'broadcasting/auth'],
    'allowed_methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'X-CSRF-TOKEN', 'X-Requested-With', 'X-Socket-ID'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
