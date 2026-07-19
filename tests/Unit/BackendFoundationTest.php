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

    public function test_only_completed_commerce_contracts_are_reported_as_implemented(): void
    {
        $contracts = config('winimi.contracts');

        $this->assertSame('implemented', $contracts['system']['status']);
        $this->assertSame('implemented', $contracts['catalog']['status']);
        $this->assertSame('bakery-catalog', $contracts['catalog']['source']);
        $this->assertNotSame('implemented', $contracts['authentication']['status']);
        $this->assertNotSame('implemented', $contracts['orders']['status']);
        $this->assertNotSame('implemented', $contracts['payments']['status']);
    }
}
