<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_category_landings', function (Blueprint $table): void {
            $table->json('guides')->nullable()->after('faq');
        });

        $guides = [
            'cookies' => [
                [
                    'href' => '/blog/cookie-storage-guide',
                    'title' => 'راهنمای نگهداری و ماندگاری کوکی',
                    'description' => 'قبل و بعد از سفارش، اطلاعات نگهداری تأییدشده همان محصول را درست بخوانید.',
                ],
                [
                    'href' => '/blog/cookies-per-guest-guide',
                    'title' => 'برای پذیرایی چند کوکی در نظر بگیریم؟',
                    'description' => 'تعداد مهمان، اندازه محصول و نقش کوکی در میز را برای انتخاب بهتر کنار هم بگذارید.',
                ],
            ],
            'mini-cookies' => [
                [
                    'href' => '/blog/cookies-per-guest-guide',
                    'title' => 'راهنمای تعداد کوکی برای پذیرایی',
                    'description' => 'برای سفارش چندنفره به‌جای عدد ثابت، تعداد مهمان و اندازه محصول فعال را مبنا قرار دهید.',
                ],
            ],
            'cakes' => [
                [
                    'href' => '/blog/cheesecake-cold-storage',
                    'title' => 'راهنمای نگهداری چیزکیک و محصولات یخچالی',
                    'description' => 'برای انتخاب‌های نیازمند سرمایش، دستور نگهداری تأییدشده محصول را پیش از سفارش بررسی کنید.',
                ],
            ],
        ];

        foreach ($guides as $slug => $items) {
            DB::table('bakery_category_landings')
                ->where('public_slug', $slug)
                ->update([
                    'guides' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('bakery_category_landings', function (Blueprint $table): void {
            $table->dropColumn('guides');
        });
    }
};
