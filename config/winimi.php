<?php

$frontendOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('FRONTEND_URLS', env('FRONTEND_URL', 'http://localhost:5173'))),
)));

return [
    'brand' => [
        'name' => env('WINIMI_BRAND_NAME', 'وینیمی بیکری'),
        'name_en' => env('WINIMI_BRAND_NAME_EN', 'Winimi Bakery'),
    ],

    'api' => [
        'version' => '1',
        'contract_version' => '2026-07-19-phase-12',
        'request_id_header' => 'X-Request-ID',
    ],

    'frontend_origins' => $frontendOrigins,

    'otp' => [
        'provider' => env('SMS_PROVIDER', 'disabled'),
        'length' => (int) env('OTP_LENGTH', 6),
        'expires_seconds' => (int) env('OTP_EXPIRES_SECONDS', 120),
        'retry_after_seconds' => (int) env('OTP_RETRY_AFTER_SECONDS', 60),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'expose_test_code' => (bool) env('OTP_EXPOSE_TEST_CODE', false),
        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'template' => env('KAVENEGAR_TEMPLATE'),
            'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
        ],
    ],

    'legacy' => [
        'enabled' => (bool) env('LEGACY_TOOLMASTER_API_ENABLED', true),
        'contract_url' => '/api/system/contracts',
    ],

    'contracts' => [
        'system' => [
            'status' => 'implemented',
            'endpoints' => [
                'GET /api/system/health',
                'GET /api/system/ready',
                'GET /api/system/meta',
                'GET /api/system/contracts',
            ],
        ],
        'catalog' => [
            'status' => 'implemented',
            'target_phase' => 11,
            'source' => 'bakery-catalog',
            'endpoints' => [
                'GET /api/catalog/products',
                'GET /api/catalog/products/{slug}',
                'GET /api/catalog/categories',
            ],
        ],
        'authentication' => [
            'status' => 'implemented',
            'target_phase' => 12,
            'source' => 'customer-session-otp',
            'endpoints' => [
                'POST /api/auth/otp/request',
                'POST /api/auth/otp/verify',
                'GET /api/auth/me',
                'POST /api/auth/logout',
                'PATCH /api/account/profile',
            ],
        ],
        'orders' => [
            'status' => 'contract-only',
            'target_phase' => 13,
            'endpoints' => [
                'POST /api/checkout',
                'GET /api/account/orders',
                'GET /api/account/orders/{orderId}',
            ],
        ],
        'payments' => [
            'status' => 'contract-only',
            'target_phase' => 14,
            'endpoints' => [
                'POST /api/orders/{orderId}/payments',
                'POST /api/payments/zarinpal/verify',
            ],
        ],
    ],
];
