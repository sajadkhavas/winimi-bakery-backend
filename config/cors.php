<?php
// ── FIX 2: config/cors.php ─────────────────────────────────────────────────────
// فایل Laravel CORS config — جایگزین config/cors.php فعلیت کن

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // ── FIX 2: آدرس‌های مجاز فرانت ────────────────────────────────────────────
    // در production فقط دامنه اصلیت رو بذار:
    'allowed_origins' => [
        'http://localhost:8080',   // dev: Vite dev server
        'http://localhost:5173',   // dev: Vite default
        'http://localhost:3000',   // dev: CRA / Next
        'https://toolmaster.com',  // prod: دامنه اصلی
        'https://www.toolmaster.com', // prod: با www
    ],

    // یا برای development فقط:
    // 'allowed_origins' => ['*'],  // ← فقط برای dev! هیچوقت production نذار

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // اگه از cookie / session استفاده میکنی true بذار:
    'supports_credentials' => false,
];
