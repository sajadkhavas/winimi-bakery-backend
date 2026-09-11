<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Support\ProductionContentFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionContentFingerprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_content_changes_are_detected_without_exposing_raw_values(): void
    {
        $before = ProductionContentFingerprint::capture();

        $setting = StoreSetting::query()->create([
            'group' => 'brand',
            'key' => 'brand.operator-note',
            'type' => 'string',
            'value' => 'محتوای تست خصوصی',
            'label' => 'یادداشت تست',
            'is_public' => false,
        ]);

        $afterCreate = ProductionContentFingerprint::capture();

        $this->assertNotSame($before['aggregate'], $afterCreate['aggregate']);
        $this->assertSame(1, $afterCreate['tables']['store_settings']['count']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $afterCreate['aggregate']);
        $this->assertStringNotContainsString(
            'محتوای تست خصوصی',
            json_encode($afterCreate, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );

        $setting->update(['value' => 'محتوای تست خصوصی ویرایش‌شده']);
        $afterUpdate = ProductionContentFingerprint::capture();

        $this->assertNotSame($afterCreate['aggregate'], $afterUpdate['aggregate']);
    }

    public function test_hash_only_command_prints_only_a_sha256_digest(): void
    {
        $this->artisan('production:content-fingerprint', ['--hash-only' => true])
            ->expectsOutputToContain('')
            ->assertSuccessful();

        $this->assertSame(0, $this->artisan('production:content-fingerprint', ['--hash-only' => true])->run());
    }
}
