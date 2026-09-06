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
        $definitions = [
            ['category_guide.eyebrow', 'راهنمای مرتبط', 'برچسب راهنماهای مرتبط'],
            ['category_guide.title', 'قبل از انتخاب، این راهنماها را ببین', 'عنوان راهنماهای مرتبط'],
            ['category_guide.description', 'لینک‌ها بر اساس موضوع همین دسته انتخاب شده‌اند و جایگزین اطلاعات قیمت، موجودی، ترکیبات یا شرایط نگهداری تأییدشده هر محصول نیستند.', 'توضیح راهنماهای مرتبط'],
            ['category_guide.read_label', 'خواندن راهنما', 'برچسب لینک خواندن راهنما'],
        ];

        foreach ($definitions as [$key, $value, $label]) {
            DB::table('store_settings')->insertOrIgnore([
                'group' => 'category_guide',
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
            DB::table('store_settings')
                ->whereIn('key', [
                    'category_guide.eyebrow',
                    'category_guide.title',
                    'category_guide.description',
                    'category_guide.read_label',
                ])
                ->delete();
        }
    }
};
