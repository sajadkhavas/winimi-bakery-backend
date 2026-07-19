<?php

namespace Tests\Unit;

use Tests\TestCase;

class BackendFoundationTest extends TestCase
{
    public function test_frontend_origins_are_configured_as_an_array(): void
    {
        $origins = config('winimi.frontend_origins');

        $this->assertIsArray($origins);
        $this->assertNotEmpty($origins);
        $this->assertSame($origins, config('cors.allowed_origins'));
        $this->assertTrue((bool) config('cors.supports_credentials'));
    }

    public function test_new_commerce_contracts_are_not_reported_as_implemented_early(): void
    {
        $contracts = config('winimi.contracts');

        $this->assertSame('implemented', $contracts['system']['status']);
        $this->assertNotSame('implemented', $contracts['catalog']['status']);
        $this->assertNotSame('implemented', $contracts['authentication']['status']);
        $this->assertNotSame('implemented', $contracts['orders']['status']);
        $this->assertNotSame('implemented', $contracts['payments']['status']);
    }
}
