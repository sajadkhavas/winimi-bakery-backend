<?php

namespace App\Console\Commands;

use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use App\Models\BakeryProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStoreLaunchCatalog extends Command
{
    protected $signature = 'catalog:sync-store-launch
        {--apply : تغییرات تأییدشده را در پایگاه داده اعمال کن}';

    protected $description = 'همگام‌سازی کنترل‌شده داده‌های تأییدشده شروع فروش، بدون فعال‌سازی عمومی محصولات';

    /**
     * @var array<int, array<string, mixed>>
     */
    private const MINI_COOKIES = [
        9 => [
            'slug' => 'mini-cookie-vanilla-chocolate-chip',
            'sku' => 'VIN-MV-008',
            'flavour' => 'وانیلی با تکه‌های شکلات',
        ],
        10 => [
            'slug' => 'mini-cookie-chocolate-chip',
            'sku' => 'VIN-MC-009',
            'flavour' => 'شکلاتی با تکه‌های شکلات',
        ],
        11 => [
            'slug' => 'mini-cookie-red-velvet',
            'sku' => 'VIN-MR-010',
            'flavour' => 'ردولوت',
        ],
    ];

    public function handle(): int
    {
        $validationError = $this->validateExpectedState();

        if ($validationError !== null) {
            $this->error($validationError);
            $this->warn('هیچ تغییری اعمال نشد.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($this->option('apply') ? 'حالت اجرا' : 'حالت پیش‌نمایش (Dry Run)');
        $this->table(
            ['مورد', 'مقدار هدف'],
            [
                ['اسلاگ دسته کوکی‌های خانگی', 'cookies'],
                ['نوع فروش مینی‌کوکی', 'فقط بسته یک‌کیلوگرمی'],
                ['تعداد تقریبی در هر کیلو', '۱۰۰ تا ۱۲۰ عدد'],
                ['وزن تقریبی هر عدد', '۸ تا ۱۰ گرم'],
                ['قیمت هر بسته یک‌کیلوگرمی', '۱٬۲۰۰٬۰۰۰ تومان'],
                ['موجودی اولیه هر طعم', '۵ بسته یک‌کیلوگرمی'],
                ['ماندگاری', 'تا ۷ روز در دمای محیط'],
                ['وضعیت انتشار', 'دسته، محصول و تنوع همگی غیرفعال'],
                ['تأیید محتوا و رسانه', 'غیرفعال تا تکمیل مواد، حساسیت‌زا و تصاویر واقعی'],
            ],
        );

        if (! $this->option('apply')) {
            $this->warn('برای اعمال دقیق همین تغییرات، فرمان را با گزینه --apply اجرا کنید.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            BakeryCategory::query()
                ->whereKey(2)
                ->update([
                    'slug' => 'cookies',
                    'is_active' => false,
                ]);

            foreach (self::MINI_COOKIES as $productId => $definition) {
                $product = BakeryProduct::query()->findOrFail($productId);
                $flavour = $definition['flavour'];

                $product->update([
                    'short_description' => "مینی‌کوکی {$flavour} در بسته یک‌کیلوگرمی؛ مناسب پذیرایی و سفارش‌های تعداد بالا.",
                    'description' => "این محصول فقط به‌صورت بسته یک‌کیلوگرمی عرضه می‌شود. هر کیلو تقریباً ۱۰۰ تا ۱۲۰ عدد مینی‌کوکی {$flavour} با وزن تقریبی ۸ تا ۱۰ گرم برای هر عدد دارد. امکان سفارش نیم‌کیلو یا سفارش بر اساس تعداد در شروع فروش فعال نیست.",
                    'shelf_life' => 'تا ۷ روز در دمای محیط',
                    'storage_instructions' => 'در ظرف کاملاً دربسته و دور از گرما و رطوبت، در دمای محیط نگهداری شود.',
                    'preparation_time_days' => 1,
                    'requires_cooling' => false,
                    'content_verified' => false,
                    'media_verified' => false,
                    'is_active' => false,
                    'meta_title' => "خرید مینی‌کوکی {$flavour} یک کیلویی | وینیمی بیکری",
                    'meta_description' => "مینی‌کوکی {$flavour} یک کیلویی، حدود ۱۰۰ تا ۱۲۰ عدد، با امکان ارسال سراسری. عرضه فقط به‌صورت بسته یک‌کیلوگرمی.",
                ]);

                BakeryProductVariant::query()
                    ->where('product_id', $productId)
                    ->where('sku', $definition['sku'])
                    ->update([
                        'name' => '۱ کیلو — حدود ۱۰۰ تا ۱۲۰ عدد',
                        'weight_grams' => 1000,
                        'regular_price_toman' => 1_200_000,
                        'sale_price_toman' => null,
                        'stock_quantity' => 5,
                        'low_stock_threshold' => 1,
                        'is_default' => true,
                        'is_active' => false,
                        'sort_order' => 0,
                    ]);
            }
        });

        $this->info('تغییرات تأییدشده با موفقیت اعمال شد.');
        $this->warn('هیچ دسته، محصول یا تنوعی فعال و عمومی نشده است.');

        return self::SUCCESS;
    }

    private function validateExpectedState(): ?string
    {
        $category = BakeryCategory::query()->find(2);

        if ($category === null || $category->name !== 'کوکی‌های خانگی') {
            return 'دسته مورد انتظار با شناسه ۲ پیدا نشد یا نام آن تغییر کرده است.';
        }

        if (! in_array($category->slug, ['kokyhay-khangy', 'cookies'], true)) {
            return "اسلاگ فعلی دسته غیرمنتظره است: {$category->slug}";
        }

        foreach (self::MINI_COOKIES as $productId => $definition) {
            $product = BakeryProduct::query()->find($productId);

            if ($product === null || $product->slug !== $definition['slug']) {
                return "محصول مورد انتظار با شناسه {$productId} یا اسلاگ {$definition['slug']} تطابق ندارد.";
            }

            $variants = BakeryProductVariant::query()
                ->where('product_id', $productId)
                ->get();

            if ($variants->count() !== 1 || $variants->first()->sku !== $definition['sku']) {
                return "ساختار تنوع محصول {$productId} با وضعیت مورد انتظار تطابق ندارد.";
            }
        }

        return null;
    }
}
