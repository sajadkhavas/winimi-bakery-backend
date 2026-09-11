<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Support\ProductionContentFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionContentFingerprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_content_changes_are_detected_without_exposing_raw_values(): void
    {
        $before = ProductionContentFingerprint::capture();
        $storeSettingCountBefore = $before['tables']['store_settings']['count'];

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
        $this->assertSame(
            $storeSettingCountBefore + 1,
            $afterCreate['tables']['store_settings']['count'],
        );
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
        $this->assertSame(0, Artisan::call('production:content-fingerprint', ['--hash-only' => true]));

        $output = trim(Artisan::output());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $output);
        $this->assertSame(64, strlen($output));
    }
}
