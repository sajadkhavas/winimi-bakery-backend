<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOAuthIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class F29GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth_features.google.enabled' => true,
            'auth_features.google.client_id' => 'test-client.apps.googleusercontent.com',
            'auth_features.google.client_secret' => 'test-secret',
            'auth_features.google.redirect_uri' => 'http://localhost:8000/auth/google/callback',
            'auth_features.google.authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'auth_features.google.token_url' => 'https://oauth2.googleapis.com/token',
            'auth_features.google.userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'auth_features.google.frontend_url' => 'http://localhost:5173',
            'auth_features.google.timeout_seconds' => 5,
            'auth_features.otp_enabled' => true,
            'session.driver' => 'array',
        ]);
    }

    public function test_capabilities_fail_closed_when_google_or_otp_are_disabled(): void
    {
        config([
            'auth_features.google.enabled' => false,
            'auth_features.otp_enabled' => false,
        ]);

        $this->getJson('/api/auth/capabilities')
            ->assertOk()
            ->assertJsonPath('data.google.enabled', false)
            ->assertJsonPath('data.otp.enabled', false)
            ->assertJsonPath('data.google.redirectPath', '/auth/google/redirect')
            ->assertJsonPath('data.google.linkPath', '/auth/google/link');

        $this->postJson('/api/auth/otp/request', ['mobile' => '09123456789'])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'otp_disabled');
    }

    public function test_google_redirect_uses_authorization_code_flow_and_session_state(): void
    {
        $response = $this->get('/auth/google/redirect')->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $query = $this->queryFromUrl($location);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertSame('test-client.apps.googleusercontent.com', $query['client_id'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertSame('openid email profile', $query['scope'] ?? null);
        $this->assertSame('http://localhost:8000/auth/google/callback', $query['redirect_uri'] ?? null);
        $this->assertNotEmpty($query['state'] ?? null);
        $response->assertSessionHas('winimi.google_oauth.state', $query['state']);
        $response->assertSessionHas('winimi.google_oauth.mode', 'login');
    }

    public function test_new_verified_google_identity_creates_customer_without_verified_mobile_and_logs_in(): void
    {
        $state = $this->beginGoogle('login');
        $this->fakeGoogleProfile('google-sub-new', 'new@example.test', 'کاربر گوگل');

        $response = $this->get('/auth/google/callback?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]));

        $response->assertRedirect('http://localhost:5173/account/login?google=success');

        $customer = Customer::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertNull($customer->mobile);
        $this->assertNull($customer->mobile_verified_at);
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertDatabaseHas('customer_oauth_identities', [
            'customer_id' => $customer->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-new',
            'email' => 'new@example.test',
            'email_verified' => true,
        ]);
    }

    public function test_tampered_state_is_rejected_before_any_google_http_request(): void
    {
        $this->beginGoogle('login');
        Http::fake();

        $response = $this->get('/auth/google/callback?state=tampered&code=authorization-code');

        $response->assertRedirect(
            'http://localhost:5173/account/login?google=error&code=invalid_state',
        );
        Http::assertNothingSent();
        $this->assertDatabaseCount('customers', 0);
        $this->assertGuest('customer');
    }

    public function test_matching_existing_email_is_not_automatically_linked(): void
    {
        Customer::query()->create([
            'mobile' => '09123456781',
            'email' => 'existing@example.test',
            'mobile_verified_at' => now(),
            'is_active' => true,
        ]);

        $state = $this->beginGoogle('login');
        $this->fakeGoogleProfile('google-sub-email-match', 'existing@example.test', 'Existing User');

        $this->get('/auth/google/callback?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]))->assertRedirect(
            'http://localhost:5173/account/login?google=error&code=account_link_required',
        );

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_oauth_identities', 0);
        $this->assertGuest('customer');
    }

    public function test_authenticated_customer_can_explicitly_link_google_identity(): void
    {
        $customer = Customer::query()->create([
            'mobile' => '09123456782',
            'email' => 'local@example.test',
            'mobile_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer');
        $state = $this->beginGoogle('link');
        $this->fakeGoogleProfile('google-sub-link', 'google@example.test', 'Google Name');

        $this->get('/auth/google/callback?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]))->assertRedirect('http://localhost:5173/account?google=linked');

        $this->assertDatabaseHas('customer_oauth_identities', [
            'customer_id' => $customer->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-link',
        ]);
        $this->assertSame('local@example.test', $customer->fresh()->email);
    }

    public function test_google_identity_claimed_by_another_customer_cannot_be_linked(): void
    {
        $owner = Customer::query()->create([
            'mobile' => '09123456783',
            'email' => 'owner@example.test',
            'mobile_verified_at' => now(),
            'is_active' => true,
        ]);
        $other = Customer::query()->create([
            'mobile' => '09123456784',
            'email' => 'other@example.test',
            'mobile_verified_at' => now(),
            'is_active' => true,
        ]);
        CustomerOAuthIdentity::query()->create([
            'customer_id' => $owner->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-owned',
            'email' => 'owner-google@example.test',
            'email_verified' => true,
        ]);

        $this->actingAs($other, 'customer');
        $state = $this->beginGoogle('link');
        $this->fakeGoogleProfile('google-sub-owned', 'owner-google@example.test', 'Owner');

        $this->get('/auth/google/callback?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]))->assertRedirect(
            'http://localhost:5173/account?google=error&code=identity_taken',
        );

        $this->assertDatabaseCount('customer_oauth_identities', 1);
    }

    public function test_google_customer_completes_iranian_mobile_without_marking_it_verified(): void
    {
        $customer = Customer::query()->create([
            'mobile' => null,
            'email' => 'mobile@example.test',
            'mobile_verified_at' => null,
            'is_active' => true,
        ]);
        $this->actingAs($customer, 'customer');

        $this->stateful()->patchJson('/api/account/mobile', [
            'mobile' => '+98 912 345 6789',
        ])->assertOk()
            ->assertJsonPath('data.user.mobile', '09123456789')
            ->assertJsonPath('data.user.mobileVerified', false)
            ->assertJsonPath('data.user.requiresMobileCompletion', false);

        $customer->refresh();
        $this->assertSame('09123456789', $customer->mobile);
        $this->assertNull($customer->mobile_verified_at);

        $this->stateful()->patchJson('/api/account/mobile', [
            'mobile' => '09121111111',
        ])->assertStatus(409)
            ->assertJsonPath('code', 'mobile_already_set');
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $state = $this->beginGoogle('login');
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'token_type' => 'Bearer',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-unverified',
                'email' => 'unverified@example.test',
                'email_verified' => false,
            ]),
        ]);

        $this->get('/auth/google/callback?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]))->assertRedirect(
            'http://localhost:5173/account/login?google=error&code=unverified_identity',
        );

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_oauth_identities', 0);
    }

    private function beginGoogle(string $mode): string
    {
        $response = $mode === 'link'
            ? $this->get('/auth/google/link')
            : $this->get('/auth/google/redirect');

        $response->assertRedirect();
        $query = $this->queryFromUrl((string) $response->headers->get('Location'));

        return (string) ($query['state'] ?? '');
    }

    private function fakeGoogleProfile(string $sub, string $email, string $name): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'token_type' => 'Bearer',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => $sub,
                'email' => $email,
                'email_verified' => true,
                'name' => $name,
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function queryFromUrl(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return array_map(static fn (mixed $value): string => (string) $value, $query);
    }

    private function stateful(): static
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'User-Agent' => 'Winimi-F29-Test-Client/1.0',
        ]);
    }
}
