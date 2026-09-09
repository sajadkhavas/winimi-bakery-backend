<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_is_fail_closed_without_vapid_configuration(): void
    {
        $this->getJson('/api/push/capabilities')
            ->assertOk()
            ->assertJsonPath('data.supported', false)
            ->assertJsonPath('data.publicKey', null)
            ->assertJsonPath('data.marketingDefault', false);
    }

    public function test_customer_can_manage_an_encrypted_subscription_and_preferences(): void
    {
        config([
            'winimi.push.enabled' => true,
            'winimi.push.vapid_public_key' => 'public-key',
            'winimi.push.vapid_private_key' => 'private-key',
        ]);
        $customer = Customer::factory()->create(['marketing_consent' => false]);
        $endpoint = 'https://push.example.test/subscriptions/device-1';

        $this->actingAs($customer, 'customer')->postJson('/api/account/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'browser-public-key', 'auth' => 'browser-auth-secret'],
            'marketingEnabled' => false,
        ])->assertCreated()->assertJsonPath('data.subscribed', true);

        $raw = (array) \DB::table('web_push_subscriptions')->first();
        $this->assertNotSame($endpoint, $raw['endpoint']);
        $this->assertNotSame('browser-public-key', $raw['public_key']);
        $this->assertNotSame('browser-auth-secret', $raw['auth_token']);

        $this->actingAs($customer, 'customer')->patchJson('/api/account/push/preferences', [
            'transactionalEnabled' => true,
            'marketingEnabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.push.subscribed', true)
            ->assertJsonPath('data.push.marketingEnabled', true);

        $this->assertTrue($customer->fresh()->marketing_consent);

        $this->actingAs($customer, 'customer')->deleteJson('/api/account/push/subscriptions', [
            'endpoint' => $endpoint,
        ])->assertOk()->assertJsonPath('data.subscribed', false);

        $this->assertNotNull($customer->webPushSubscriptions()->firstOrFail()->revoked_at);
    }

    public function test_subscription_endpoint_rejects_use_while_push_is_disabled(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->postJson('/api/account/push/subscriptions', [
            'endpoint' => 'https://push.example.test/device',
            'keys' => ['p256dh' => 'key', 'auth' => 'secret'],
        ])->assertServiceUnavailable();
    }
}
