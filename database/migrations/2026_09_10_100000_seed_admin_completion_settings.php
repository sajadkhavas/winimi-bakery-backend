<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['pwa', 'pwa.name', 'string', 'وینیمی بیکری', 'نام کامل اپ'],
            ['pwa', 'pwa.short_name', 'string', 'وینیمی', 'نام کوتاه اپ'],
            ['pwa', 'pwa.description', 'string', 'فروشگاه آنلاین کوکی، کیک و دسر وینیمی', 'توضیح اپ'],
            ['pwa', 'pwa.theme_color', 'string', '#D0E596', 'رنگ اصلی اپ'],
            ['pwa', 'pwa.background_color', 'string', '#FFFDF7', 'رنگ پس‌زمینه اپ'],
            ['pwa', 'pwa.offline_title', 'string', 'اتصال اینترنت در دسترس نیست', 'عنوان حالت آفلاین'],
            ['pwa', 'pwa.offline_description', 'string', 'پس از اتصال دوباره، صفحه را تازه‌سازی کنید.', 'توضیح حالت آفلاین'],
            ['pwa', 'pwa.shortcuts', 'json', json_encode([
                ['name' => 'فروشگاه وینیمی', 'short_name' => 'فروشگاه', 'description' => 'مشاهده محصولات وینیمی', 'url' => '/products'],
                ['name' => 'سبد خرید', 'short_name' => 'سبد', 'description' => 'مشاهده سبد خرید', 'url' => '/cart'],
                ['name' => 'حساب کاربری', 'short_name' => 'حساب', 'description' => 'سفارش‌ها و تنظیمات حساب', 'url' => '/account'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'میانبرهای اپ'],
            ['integrations', 'integrations.google_tag_mode', 'string', 'none', 'روش اتصال Google Tag'],
            ['integrations', 'integrations.google_tag_id', 'string', '', 'شناسه GA4 یا GTM'],
            ['integrations', 'integrations.search_console_verification', 'string', '', 'کد تأیید Search Console'],
            ['consent', 'consent.analytics_enabled', 'boolean', '0', 'نمایش رضایت آمار'],
            ['consent', 'consent.title', 'string', 'تنظیمات حریم خصوصی', 'عنوان رضایت'],
            ['consent', 'consent.description', 'string', 'برای بهبود تجربه سایت، اندازه‌گیری بازدید فقط با انتخاب شما فعال می‌شود.', 'توضیح رضایت'],
            ['consent', 'consent.accept_label', 'string', 'اجازه اندازه‌گیری', 'متن پذیرش'],
            ['consent', 'consent.reject_label', 'string', 'فعلاً نه', 'متن رد'],
        ];

        foreach ($settings as [$group, $key, $type, $defaultValue, $label]) {
            $metadata = [
                'group' => $group,
                'type' => $type,
                'label' => $label,
                'is_public' => true,
                'updated_at' => $now,
            ];

            $existing = DB::table('store_settings')->where('key', $key)->first();

            if ($existing) {
                // Once an operator-managed setting exists, migrations may repair its
                // contract metadata but must never replace the operator's current value.
                DB::table('store_settings')->where('key', $key)->update($metadata);

                continue;
            }

            DB::table('store_settings')->insert($metadata + [
                'key' => $key,
                'value' => $defaultValue,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // These rows become operator-owned as soon as they exist. A migration rollback
        // must not delete values an operator may already have changed. Leaving the
        // public settings in place is backward-compatible and preserves business data.
    }
};
