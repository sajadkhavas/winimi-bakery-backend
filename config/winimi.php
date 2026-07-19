<?php

$frontendOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('FRONTEND_URLS', env('FRONTEND_URL', 'http://localhost:5173'))),
)));

$boolean = static fn (string $key, bool $default = false): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
);

return [
    'brand' => [
        'name' => env('WINIMI_BRAND_NAME', 'وینیمی بیکری'),
        'name_en' => env('WINIMI_BRAND_NAME_EN', 'Winimi Bakery'),
    ],

    'api' => [
        'version' => '1',
        'contract_version' => '2026-07-19-phase-13.5',
        'request_id_header' => 'X-Request-ID',
    ],

    'frontend_origins' => $frontendOrigins,

    'otp' => [
        'provider' => env('SMS_PROVIDER', 'disabled'),
        'length' => (int) env('OTP_LENGTH', 6),
        'expires_seconds' => (int) env('OTP_EXPIRES_SECONDS', 120),
        'retry_after_seconds' => (int) env('OTP_RETRY_AFTER_SECONDS', 60),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'expose_test_code' => $boolean('OTP_EXPOSE_TEST_CODE'),
        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'template' => env('KAVENEGAR_TEMPLATE'),
            'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
        ],
    ],

    'checkout' => [
        'enabled' => $boolean('CHECKOUT_ENABLED'),
        'reservation_minutes' => (int) env('INVENTORY_RESERVATION_MINUTES', 20),
        'max_quantity_per_line' => (int) env('CHECKOUT_MAX_QUANTITY_PER_LINE', 20),
        'max_total_units' => (int) env('CHECKOUT_MAX_TOTAL_UNITS', 50),
        'packaging_fee_toman' => (int) env('CHECKOUT_PACKAGING_FEE_TOMAN', 0),
        'delivery_methods' => [
            'standard' => [
                'enabled' => $boolean('DELIVERY_STANDARD_ENABLED'),
                'fee_toman' => (int) env('DELIVERY_STANDARD_FEE_TOMAN', 0),
            ],
            'chilled' => [
                'enabled' => $boolean('DELIVERY_CHILLED_ENABLED'),
                'fee_toman' => (int) env('DELIVERY_CHILLED_FEE_TOMAN', 0),
            ],
            'pickup' => [
                'enabled' => $boolean('DELIVERY_PICKUP_ENABLED'),
                'fee_toman' => (int) env('DELIVERY_PICKUP_FEE_TOMAN', 0),
            ],
        ],
    ],

    'legacy' => [
        'enabled' => $boolean('LEGACY_TOOLMASTER_API_ENABLED', true),
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
            'status' => 'implemented',
            'target_phase' => 13,
            'source' => 'transactional-order-reservations',
            'endpoints' => [
                'POST /api/checkout',
                'GET /api/account/orders',
                'GET /api/account/orders/{orderId}',
                'POST /api/account/orders/{orderId}/cancel',
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

    'launch' => [
        'strategy' => 'complete-internal-work-before-external-activation',
        'roadmap_version' => '2026-07-19-phase-13.5',
        'internal_gates' => [
            'backend_complete' => [
                'status' => 'in-progress',
                'target_phase' => 16,
            ],
            'frontend_integrated' => [
                'status' => 'not-started',
                'target_phase' => 17,
            ],
            'end_to_end_verified' => [
                'status' => 'not-started',
                'target_phase' => 18,
            ],
            'production_deployed' => [
                'status' => 'not-started',
                'target_phase' => 19,
            ],
        ],
        'external_only' => [
            'payment_gateway_credentials' => [
                'status' => 'pending-external',
                'target_phase' => 20,
                'server_keys' => ['ZARINPAL_MERCHANT_ID'],
            ],
            'enamad_badge_code' => [
                'status' => 'pending-external',
                'target_phase' => 20,
                'setting' => 'ENAMAD_BADGE_CODE',
            ],
            'sms_provider_credentials' => [
                'status' => 'pending-external',
                'target_phase' => 20,
                'server_keys' => ['KAVENEGAR_API_KEY', 'KAVENEGAR_TEMPLATE'],
            ],
        ],
    ],
];