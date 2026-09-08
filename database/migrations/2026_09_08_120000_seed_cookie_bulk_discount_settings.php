<?php

use App\Models\StoreSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
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
            StoreSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'group' => 'pricing',
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                    'label' => $setting['label'],
                    'is_public' => true,
                ],
            );
        }
    }

    public function down(): void
    {
        StoreSetting::query()
            ->whereIn('key', [
                'pricing.cookie_bulk_discount.enabled',
                'pricing.cookie_bulk_discount.min_quantity',
                'pricing.cookie_bulk_discount.percent',
                'pricing.cookie_bulk_discount.category_slugs',
            ])
            ->delete();
    }
};
