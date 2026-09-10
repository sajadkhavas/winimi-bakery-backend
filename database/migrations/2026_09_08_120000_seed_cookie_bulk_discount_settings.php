<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            [
                'key' => 'pricing.cookie_bulk_discount.enabled',
                'type' => 'boolean',
                'value' => '1',
                'label' => 'فعال‌بودن تخفیف سفارش عمده کوکی',
            ],
            [
                'key' => 'pricing.cookie_bulk_discount.min_quantity',
                'type' => 'integer',
                'value' => '100',
                'label' => 'حداقل تعداد کوکی برای تخفیف عمده',
            ],
            [
                'key' => 'pricing.cookie_bulk_discount.percent',
                'type' => 'integer',
                'value' => '10',
                'label' => 'درصد تخفیف سفارش عمده کوکی',
            ],
            [
                'key' => 'pricing.cookie_bulk_discount.category_slugs',
                'type' => 'json',
                'value' => json_encode(
                    ['kokyhay-khangy', 'myny-koky'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'label' => 'اسلاگ دسته‌های مشمول تخفیف عمده کوکی',
            ],
        ];

        foreach ($settings as $setting) {
            $metadata = [
                'group' => 'pricing',
                'type' => $setting['type'],
                'label' => $setting['label'],
                'is_public' => true,
                'updated_at' => $now,
            ];

            $existing = DB::table('store_settings')->where('key', $setting['key'])->first();

            if ($existing) {
                DB::table('store_settings')->where('key', $setting['key'])->update($metadata);

                continue;
            }

            DB::table('store_settings')->insert($metadata + [
                'key' => $setting['key'],
                'value' => $setting['value'],
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Pricing settings become operator-owned once created. Rollback must preserve
        // current commercial values rather than deleting or resetting them.
    }
};
