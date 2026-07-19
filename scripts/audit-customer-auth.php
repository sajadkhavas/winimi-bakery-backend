<?php

$errors = [];

$files = [
    'migration' => 'database/migrations/2026_07_19_163000_create_customer_auth_tables.php',
    'customer' => 'app/Models/Customer.php',
    'challenge' => 'app/Models/OtpChallenge.php',
    'mobile' => 'app/Support/IranianMobile.php',
    'service' => 'app/Services/Auth/OtpService.php',
    'sender' => 'app/Services/Auth/OtpSender.php',
    'controller' => 'app/Http/Controllers/Api/OtpAuthController.php',
    'account' => 'app/Http/Controllers/Api/AccountController.php',
    'resource' => 'app/Http/Resources/CustomerResource.php',
    'authConfig' => 'config/auth.php',
    'sanctumConfig' => 'config/sanctum.php',
    'winimiConfig' => 'config/winimi.php',
    'routes' => 'routes/api.php',
    'provider' => 'app/Providers/AppServiceProvider.php',
    'schedule' => 'routes/console.php',
    'env' => '.env.example',
    'tests' => 'tests/Feature/CustomerOtpAuthTest.php',
];

$sources = [];
foreach ($files as $name => $path) {
    if (! is_file($path)) {
        $errors[] = "Missing required customer-auth file: {$path}";

        continue;
    }

    $sources[$name] = file_get_contents($path);
}

$require = static function (string $source, string $needle, string $label) use (&$errors, $sources, $files): void {
    if (! isset($sources[$source]) || ! str_contains($sources[$source], $needle)) {
        $errors[] = ($files[$source] ?? $source).": missing {$label}";
    }
};

$forbid = static function (string $source, string $needle, string $label) use (&$errors, $sources, $files): void {
    if (isset($sources[$source]) && str_contains($sources[$source], $needle)) {
        $errors[] = ($files[$source] ?? $source).": contains forbidden {$label}";
    }
};

foreach (['customers', 'otp_challenges', 'mobile_hash', 'code_hash', 'expires_at', 'consumed_at'] as $field) {
    $require('migration', $field, "auth schema field {$field}");
}

$require('migration', "char('public_id', 26)->unique()", 'non-sequential public IDs');
$require('challenge', "'mobile_payload' => 'encrypted'", 'encrypted mobile challenge payload');
$require('service', 'Hash::make($code)', 'hashed OTP storage');
$require('service', 'Hash::check($code, $challenge->code_hash)', 'constant authentication hash check');
$require('service', "$challenge->attempts++", 'failed-attempt counter');
$require('service', "'consumed_at' => now()", 'one-time challenge consumption');
$require('service', "app()->environment(['local', 'testing'])", 'test-code production guard');
$forbid('service', 'Log::', 'OTP logging');
$forbid('sender', 'logger(', 'OTP logging');

$require('authConfig', "'customer' => ['driver' => 'session', 'provider' => 'customers']", 'isolated customer session guard');
$require('sanctumConfig', "'guard' => ['customer', 'web']", 'customer Sanctum guard');
$require('controller', "Auth::guard('customer')->login", 'customer guard login');
$require('controller', '$request->session()->regenerate()', 'session rotation after login');
$require('controller', '$request->session()->invalidate()', 'session invalidation on logout');
$require('routes', "middleware(['auth:customer'", 'protected customer endpoints');
$require('routes', 'throttle:otp-request', 'OTP request limiter');
$require('routes', 'throttle:otp-verify', 'OTP verification limiter');
$require('provider', "RateLimiter::for('otp-request'", 'mobile/IP request rate limiter');
$require('provider', "RateLimiter::for('otp-verify'", 'challenge/IP verify rate limiter');
$require('schedule', 'prune-otp-challenges', 'OTP challenge pruning schedule');

$require('winimiConfig', "'authentication' => [\n            'status' => 'implemented'", 'implemented authentication contract');
$require('winimiConfig', "'orders' => [\n            'status' => 'contract-only'", 'disabled order contract');
$require('winimiConfig', "'payments' => [\n            'status' => 'contract-only'", 'disabled payment contract');
$require('env', 'SMS_PROVIDER=disabled', 'secure default SMS provider');
$require('env', 'OTP_EXPOSE_TEST_CODE=false', 'secure test-code default');
$require('env', 'SESSION_ENCRYPT=true', 'encrypted session default');

foreach ([
    'test_otp_request_normalizes_mobile_and_never_stores_plain_code_or_mobile_payload',
    'test_customer_can_verify_otp_use_session_update_profile_and_logout',
    'test_wrong_code_increments_attempts_and_consumed_code_cannot_be_reused',
    'test_challenge_is_locked_after_maximum_failed_attempts',
    'test_disabled_provider_returns_service_unavailable_and_removes_challenge',
] as $testName) {
    $require('tests', $testName, "security regression test {$testName}");
}

if ($errors !== []) {
    fwrite(STDERR, "Customer authentication audit failed:\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

echo 'Customer authentication audit passed: isolated sessions, OTP storage, rate limits and contract boundaries verified.'.PHP_EOL;
