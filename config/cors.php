<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => config('winimi.frontend_origins', [
        'http://localhost:5173',
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Idempotency-Key',
        'Origin',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'X-Request-ID',
    ],

    'exposed_headers' => [
        'X-API-Version',
        'X-Request-ID',
        'Deprecation',
        'Link',
    ],

    'max_age' => 600,

    'supports_credentials' => true,
];
