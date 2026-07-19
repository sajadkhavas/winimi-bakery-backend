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
        'contract_version' => '2026-07-19',
        'request_id_header' => 'X-Request-ID',
    ],

    'frontend_origins' => $frontendOrigins,

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
            'status' => 'legacy-adapter',
            'target_phase' => 11,
            'endpoints' => [
                'GET /api/catalog/products',
                'GET /api/catalog/products/{slug}',
                'GET /api/catalog/categories',
            ],
        ],
        'authentication' => [
            'status' => 'contract-only',
            'target_phase' => 12,
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
