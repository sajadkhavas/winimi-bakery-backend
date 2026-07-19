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
            ->assertJsonPath('data.contractVersion', '2026-07-19-phase-14')
            ->assertJsonPath('data.roadmapVersion', '2026-07-19-phase-14')
            ->assertJsonPath('data.legacyApiEnabled', true);
    }

    public function test_contract_endpoint_reports_completed_contracts_and_locked_launch_strategy(): void
    {
        $response = $this->getJson('/api/system/contracts');

        $response
            ->assertOk()
            ->assertJsonPath('data.contracts.system.status', 'implemented')
            ->assertJsonPath('data.contracts.catalog.status', 'implemented')
            ->assertJsonPath('data.contracts.catalog.source', 'bakery-catalog')
            ->assertJsonPath('data.contracts.authentication.status', 'implemented')
            ->assertJsonPath('data.contracts.authentication.source', 'customer-session-otp')
            ->assertJsonPath('data.contracts.orders.status', 'implemented')
            ->assertJsonPath('data.contracts.orders.source', 'transactional-order-reservations')
            ->assertJsonPath('data.contracts.payments.status', 'implemented')
            ->assertJsonPath('data.contracts.payments.source', 'provider-ready-payment-attempts')
            ->assertJsonPath(
                'data.contracts.payments.activation',
                'disabled-until-external-credentials',
            )
            ->assertJsonPath('data.launch.strategy', 'complete-internal-work-before-external-activation')
            ->assertJsonPath('data.launch.internal_gates.backend_complete.target_phase', 16)
            ->assertJsonPath('data.launch.internal_gates.frontend_integrated.target_phase', 17)
            ->assertJsonPath('data.launch.internal_gates.end_to_end_verified.target_phase', 18)
            ->assertJsonPath('data.launch.internal_gates.production_deployed.target_phase', 19)
            ->assertJsonPath('data.launch.external_only.payment_gateway_credentials.status', 'pending-external')
            ->assertJsonPath('data.launch.external_only.enamad_badge_code.status', 'pending-external')
            ->assertJsonPath('data.launch.external_only.sms_provider_credentials.status', 'pending-external')
            ->assertJsonCount(3, 'data.launch.external_only');
    }

    public function test_unknown_api_routes_render_json(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
