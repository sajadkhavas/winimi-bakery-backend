<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemApiTest extends TestCase
{
    public function test_health_endpoint_returns_standard_metadata(): void
    {
        $response = $this->getJson('/api/system/health', [
            'X-Request-ID' => 'test-request-id',
        ]);

        $response
            ->assertOk()
            ->assertHeader('X-Request-ID', 'test-request-id')
            ->assertHeader('X-API-Version', '1')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'winimi-bakery-backend')
            ->assertJsonPath('meta.requestId', 'test-request-id');
    }

    public function test_meta_endpoint_exposes_the_current_contract_identity(): void
    {
        $this->getJson('/api/system/meta')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.brand.nameEn', 'Winimi Bakery')
            ->assertJsonPath('data.contractVersion', '2026-07-19')
            ->assertJsonPath('data.legacyApiEnabled', true);
    }

    public function test_contract_endpoint_does_not_claim_unimplemented_features_are_active(): void
    {
        $response = $this->getJson('/api/system/contracts');

        $response
            ->assertOk()
            ->assertJsonPath('data.contracts.system.status', 'implemented')
            ->assertJsonPath('data.contracts.catalog.status', 'legacy-adapter')
            ->assertJsonPath('data.contracts.authentication.status', 'contract-only')
            ->assertJsonPath('data.contracts.orders.status', 'contract-only')
            ->assertJsonPath('data.contracts.payments.status', 'contract-only');
    }

    public function test_unknown_api_routes_render_json(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
