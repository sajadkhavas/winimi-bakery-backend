<?php

$errors = [];

$files = [
    'service' => 'app/Services/Auth/GoogleOAuthService.php',
    'controller' => 'app/Http/Controllers/GoogleAuthController.php',
    'apiController' => 'app/Http/Controllers/Api/OtpAuthController.php',
    'account' => 'app/Http/Controllers/Api/AccountController.php',
    'identity' => 'app/Models/CustomerOAuthIdentity.php',
    'resource' => 'app/Http/Resources/CustomerResource.php',
    'migration' => 'database/migrations/2026_09_06_140000_add_google_auth_to_customers.php',
    'config' => 'config/auth_features.php',
    'webRoutes' => 'routes/web.php',
    'apiRoutes' => 'routes/api.php',
    'env' => '.env.example',
    'tests' => 'tests/Feature/F29GoogleAuthTest.php',
];

$sources = [];
foreach ($files as $name => $path) {
    if (! is_file($path)) {
        $errors[] = "Missing F29 file: {$path}";

        continue;
    }
    $sources[$name] = (string) file_get_contents($path);
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

$require('service', 'hash_equals($expectedState, $receivedState)', 'state comparison');
$require('service', "'scope' => 'openid email profile'", 'minimal OpenID scopes');
$require('service', "'grant_type' => 'authorization_code'", 'authorization code exchange');
$require('service', "'account_link_required'", 'no automatic existing-email link');
$require('service', "'provider_user_id' => \$profile['sub']", 'Google sub persistence');
$require('service', "'email_verified' => true", 'verified-email persistence');
$forbid('service', 'stateless()', 'stateless OAuth bypass');
$forbid('identity', "'token'", 'provider token persistence');
$forbid('identity', "'refresh_token'", 'provider refresh-token persistence');

$require('migration', "->string('mobile', 11)->nullable()->change()", 'nullable mobile transition');
$require('migration', "->unique(['provider', 'provider_user_id'])", 'provider identity uniqueness');
$require('migration', "->unique(['customer_id', 'provider'])", 'one provider identity per customer');
$require('account', "'mobile_verified_at' => null", 'unverified mobile completion');
$require('resource', "'requiresMobileCompletion'", 'mobile completion contract');
$require('resource', "'googleLinked'", 'Google link state contract');

$require('apiController', "config('auth_features.otp_enabled')", 'OTP feature flag');
$require('apiController', "'otp_disabled'", 'fail-closed OTP response code');
$require('apiRoutes', "Route::get('capabilities'", 'auth capability endpoint');
$require('apiRoutes', "Route::patch('mobile'", 'mobile completion endpoint');
$require('webRoutes', "'/auth/google/redirect'", 'Google redirect route');
$require('webRoutes', "'/auth/google/callback'", 'Google callback route');
$require('webRoutes', "'/auth/google/link'", 'explicit Google link route');

$require('env', 'GOOGLE_AUTH_ENABLED=false', 'fail-closed Google default');
$require('env', 'GOOGLE_CLIENT_SECRET=', 'server-only Google client secret slot');
$require('env', 'OTP_ENABLED=false', 'fail-closed OTP default');

foreach ([
    'test_google_redirect_uses_authorization_code_flow_and_session_state',
    'test_new_verified_google_identity_creates_customer_without_verified_mobile_and_logs_in',
    'test_tampered_state_is_rejected_before_any_google_http_request',
    'test_matching_existing_email_is_not_automatically_linked',
    'test_authenticated_customer_can_explicitly_link_google_identity',
    'test_google_identity_claimed_by_another_customer_cannot_be_linked',
    'test_google_customer_completes_iranian_mobile_without_marking_it_verified',
    'test_unverified_google_email_is_rejected',
] as $testName) {
    $require('tests', $testName, "security regression {$testName}");
}

if ($errors !== []) {
    fwrite(STDERR, "F29 auth audit failed:\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

echo "F29 auth audit passed: stateful Google identity, explicit linking, mobile completion and OTP feature flag are locked.\n";
