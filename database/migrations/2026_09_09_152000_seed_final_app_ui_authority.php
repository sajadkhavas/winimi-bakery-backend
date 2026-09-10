<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_settings')) {
            return;
        }

        $now = now();
        foreach ([
            ['app_ui.mobile_home', 'خانه', 'ناوبری موبایل: خانه'],
            ['app_ui.mobile_about', 'درباره ما', 'ناوبری موبایل: درباره ما'],
            ['app_ui.mobile_shop', 'فروشگاه', 'ناوبری موبایل: فروشگاه'],
            ['app_ui.mobile_cart', 'سبد', 'ناوبری موبایل: سبد'],
            ['app_ui.mobile_account', 'حساب', 'ناوبری موبایل: حساب'],
            ['app_ui.install_title', 'وینیمی را مثل برنامه نصب کن', 'عنوان پیشنهاد نصب وب‌اپ'],
            ['app_ui.install_description', 'دسترسی سریع، اجرای مستقل و تجربه بهتر روی گوشی.', 'توضیح پیشنهاد نصب وب‌اپ'],
            ['app_ui.install_action', 'نصب برنامه', 'دکمه نصب وب‌اپ'],
            ['app_ui.push_title', 'اعلان‌های وینیمی', 'عنوان فعال‌سازی اعلان'],
            ['app_ui.push_description', 'با اجازه شما، خبر موجودی و پیشنهادها را حتی بیرون از سایت دریافت کنید.', 'توضیح فعال‌سازی اعلان'],
            ['app_ui.push_action', 'فعال‌کردن اعلان', 'دکمه فعال‌سازی اعلان'],
        ] as [$key, $value, $label]) {
            DB::table('store_settings')->insertOrIgnore([
                'group' => 'app_ui',
                'key' => $key,
                'type' => 'string',
                'value' => $value,
                'label' => $label,
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_settings')) {
            DB::table('store_settings')->where('group', 'app_ui')->delete();
        }
    }
};
