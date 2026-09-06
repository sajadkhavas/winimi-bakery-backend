<?php

$boolean = static fn (string $key, bool $default = false): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
);

$appUrl = rtrim((string) env('APP_URL', 'http://localhost:8000'), '/');
$frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

return [
    'otp_enabled' => $boolean('OTP_ENABLED'),

    'google' => [
        'enabled' => $boolean('GOOGLE_AUTH_ENABLED'),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI', $appUrl.'/auth/google/callback'),
        'authorization_url' => env(
            'GOOGLE_AUTHORIZATION_URL',
            'https://accounts.google.com/o/oauth2/v2/auth',
        ),
        'token_url' => env(
            'GOOGLE_TOKEN_URL',
            'https://oauth2.googleapis.com/token',
        ),
        'userinfo_url' => env(
            'GOOGLE_USERINFO_URL',
            'https://openidconnect.googleapis.com/v1/userinfo',
        ),
        'frontend_url' => $frontendUrl,
        'timeout_seconds' => (int) env('GOOGLE_AUTH_TIMEOUT_SECONDS', 10),
    ],
];
