<?php

namespace Tests\Feature;

use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BakeryCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_contract_is_implemented(): void
    {
        $this->getJson('/api/system/contracts')
            ->assertOk()
            ->assertJsonPath('data.contracts.catalog.status', 'implemented')
            ->assertJsonPath('data.contracts.catalog.source', 'bakery-catalog');
    }

    public function test_categories_only_expose_active_records_with_active_product_counts(): void
    {
        $category = BakeryCategory::create([
            'name' => 'کوکی‌ها',
            'slug' => 'cookies',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        BakeryCategory::create([
            'name' => 'غیرفعال',
            'slug' => 'inactive-category',
            'is_active' => false,
        ]);

        $product = $this->createProduct($category, [
            'name' => 'کوکی شکلاتی',
            'slug' => 'chocolate-cookie',
            'product_code' => 'WIN-COOKIE-001',
        ]);
        $this->createVariant($product, [
            'name' => 'بسته ۶ عددی',
            'sku' => 'WIN-COOKIE-001-6',
        ]);

        $this->getJson('/api/catalog/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $category->public_id)
            ->assertJsonPath('data.0.slug', 'cookies')
            ->assertJsonPath('data.0.productCount', 1);
    }

    public function test_product_listing_calculates_variant_price_stock_and_verification_boundaries(): void
    {
        $category = BakeryCategory::create([
            'name' => 'کوکی‌ها',
            'slug' => 'cookies',
            'is_active' => true,
        ]);

        $product = $this->createProduct($category, [
            'name' => 'کوکی شکلاتی گردویی',
            'slug' => 'walnut-chocolate-cookie',
            'product_code' => 'WIN-COOKIE-002',
            'description' => 'توضیح داخلی که تا تأیید نباید منتشر شود.',
            'ingredients' => ['آرد', 'گردو'],
            'allergens' => ['گلوتن', 'گردو'],
            'content_verified' => true,
            'requires_cooling' => false,
            'is_featured' => true,
        ]);

        $firstVariant = $this->createVariant($product, [
            'name' => 'بسته ۶ عددی',
            'sku' => 'WIN-COOKIE-002-6',
            'regular_price_toman' => 150000,
            'sale_price_toman' => 120000,
            'stock_quantity' => 3,
            'is_default' => true,
        ]);
        $this->createVariant($product, [
            'name' => 'بسته ۱۲ عددی',
            'sku' => 'WIN-COOKIE-002-12',
            'regular_price_toman' => 240000,
            'stock_quantity' => 2,
        ]);

        $this->getJson('/api/catalog/products?category=cookies&featured=1&inStock=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->public_id)
            ->assertJsonPath('data.0.priceToman', 120000)
            ->assertJsonPath('data.0.regularPriceToman', 150000)
            ->assertJsonPath('data.0.salePriceToman', 120000)
            ->assertJsonPath('data.0.stock', 5)
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.0.inventoryVerified', true)
            ->assertJsonPath('data.0.contentVerified', true)
            ->assertJsonPath(
                'data.0.longDescription',
                'توضیح داخلی که تا تأیید نباید منتشر شود.',
            )
            ->assertJsonPath('data.0.ingredients.0', 'آرد')
            ->assertJsonPath('data.0.allergens.0', 'گلوتن')
            ->assertJsonPath('data.0.variants.0.id', $firstVariant->public_id)
            ->assertJsonPath('data.0.variants.0.inventoryVerified', true)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_product_detail_exposes_verified_content_and_active_variants_only(): void
    {
        $category = BakeryCategory::create([
            'name' => 'کیک و دسر',
            'slug' => 'cakes',
            'is_active' => true,
        ]);

        $product = $this->createProduct($category, [
            'name' => 'چیزکیک',
            'slug' => 'cheesecake',
            'product_code' => 'WIN-CAKE-001',
            'description' => 'توضیح تأییدشده محصول',
            'ingredients' => ['پنیر خامه‌ای', 'بیسکویت'],
            'allergens' => ['لبنیات', 'گلوتن'],
            'shelf_life' => 'طبق برچسب بسته‌بندی',
            'storage_instructions' => 'در یخچال نگهداری شود.',
            'content_verified' => true,
            'requires_cooling' => true,
            'availability_mode' => BakeryProduct::AVAILABILITY_MADE_TO_ORDER,
            'preparation_min_days' => 1,
            'preparation_max_days' => 2,
            'shipping_scope' => BakeryProduct::SHIPPING_CONFIGURED_ZONES,
            'shipping_note' => 'ارسال فقط در محدوده‌های فعال تنظیم‌شده فروشگاه انجام می‌شود.',
        ]);

        $this->createVariant($product, [
            'name' => 'تک‌نفره',
            'sku' => 'WIN-CAKE-001-S',
            'regular_price_toman' => 180000,
            'stock_quantity' => 4,
            'package_quantity' => 1,
            'min_order_quantity' => 1,
            'max_order_quantity' => 4,
            'inventory_verified' => true,
        ]);
        $this->createVariant($product, [
            'name' => 'غیرفعال',
            'sku' => 'WIN-CAKE-001-X',
            'regular_price_toman' => 1,
            'stock_quantity' => 99,
            'is_active' => false,
        ]);

        $this->getJson('/api/catalog/products/cheesecake')
            ->assertOk()
            ->assertJsonPath('data.longDescription', 'توضیح تأییدشده محصول')
            ->assertJsonPath('data.ingredients.0', 'پنیر خامه‌ای')
            ->assertJsonPath('data.allergens.0', 'لبنیات')
            ->assertJsonPath('data.requiresCooling', true)
            ->assertJsonPath('data.shippingScope', 'tehran-karaj')
            ->assertJsonPath('data.shippingPolicy.scope', BakeryProduct::SHIPPING_CONFIGURED_ZONES)
            ->assertJsonPath(
                'data.shippingPolicy.note',
                'ارسال فقط در محدوده‌های فعال تنظیم‌شده فروشگاه انجام می‌شود.',
            )
            ->assertJsonPath(
                'data.availabilityMode',
                BakeryProduct::AVAILABILITY_MADE_TO_ORDER,
            )
            ->assertJsonPath('data.preparation.minDays', 1)
            ->assertJsonPath('data.preparation.maxDays', 2)
            ->assertJsonPath('data.inventoryVerified', true)
            ->assertJsonPath('data.stock', 4)
            ->assertJsonPath('data.variants.0.packageQuantity', 1)
            ->assertJsonPath('data.variants.0.minOrderQuantity', 1)
            ->assertJsonPath('data.variants.0.maxOrderQuantity', 4)
            ->assertJsonPath('data.variants.0.inventoryVerified', true)
            ->assertJsonCount(1, 'data.variants');
    }

    public function test_public_catalog_reviews_and_counts_require_launch_ready_products(): void
    {
        $category = BakeryCategory::create([
            'name' => 'انتشار',
            'slug' => 'publication',
            'is_active' => true,
        ]);

        $ready = $this->createProduct($category, [
            'name' => 'محصول آماده انتشار',
            'slug' => 'launch-ready-product',
            'product_code' => 'WIN-LAUNCH-READY',
        ]);

        $this->createVariant($ready, [
            'sku' => 'WIN-LAUNCH-READY-1',
        ]);

        $contentBlocked = $this->createProduct($category, [
            'name' => 'محتوای تأییدنشده',
            'slug' => 'content-blocked-product',
            'product_code' => 'WIN-CONTENT-BLOCKED',
            'content_verified' => false,
        ]);

        $this->createVariant($contentBlocked, [
            'sku' => 'WIN-CONTENT-BLOCKED-1',
        ]);

        $mediaBlocked = $this->createProduct($category, [
            'name' => 'رسانه تأییدنشده',
            'slug' => 'media-blocked-product',
            'product_code' => 'WIN-MEDIA-BLOCKED',
            'media_verified' => false,
        ]);

        $this->createVariant($mediaBlocked, [
            'sku' => 'WIN-MEDIA-BLOCKED-1',
        ]);

        $inventoryBlocked = $this->createProduct($category, [
            'name' => 'موجودی تأییدنشده',
            'slug' => 'inventory-blocked-product',
            'product_code' => 'WIN-INVENTORY-BLOCKED',
        ]);

        $this->createVariant($inventoryBlocked, [
            'sku' => 'WIN-INVENTORY-BLOCKED-1',
            'inventory_verified' => false,
        ]);

        $partiallyVerified = $this->createProduct($category, [
            'name' => 'موجودی ناقص',
            'slug' => 'partial-inventory-product',
            'product_code' => 'WIN-PARTIAL-INVENTORY',
        ]);

        $this->createVariant($partiallyVerified, [
            'name' => 'انتخاب تأییدشده',
            'sku' => 'WIN-PARTIAL-INVENTORY-1',
            'inventory_verified' => true,
        ]);

        $this->createVariant($partiallyVerified, [
            'name' => 'انتخاب تأییدنشده',
            'sku' => 'WIN-PARTIAL-INVENTORY-2',
            'inventory_verified' => false,
            'is_default' => false,
        ]);

        $this->getJson('/api/catalog/products?category=publication')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ready->public_id)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/api/catalog/categories')
            ->assertOk()
            ->assertJsonPath('data.0.productCount', 1);

        foreach ([
            'content-blocked-product',
            'media-blocked-product',
            'inventory-blocked-product',
            'partial-inventory-product',
        ] as $slug) {
            $this->getJson("/api/catalog/products/{$slug}")
                ->assertNotFound();

            $this->getJson("/api/catalog/products/{$slug}/reviews")
                ->assertNotFound();
        }
    }

    public function test_inactive_and_out_of_stock_products_are_filtered_correctly(): void
    {
        $category = BakeryCategory::create([
            'name' => 'باکس هدیه',
            'slug' => 'gift',
            'is_active' => true,
        ]);

        $outOfStock = $this->createProduct($category, [
            'name' => 'باکس بدون موجودی',
            'slug' => 'empty-box',
            'product_code' => 'WIN-GIFT-EMPTY',
        ]);
        $this->createVariant($outOfStock, [
            'name' => 'استاندارد',
            'sku' => 'WIN-GIFT-EMPTY-STD',
            'stock_quantity' => 0,
        ]);

        $inactive = $this->createProduct($category, [
            'name' => 'محصول غیرفعال',
            'slug' => 'inactive-product',
            'product_code' => 'WIN-GIFT-INACTIVE',
            'is_active' => false,
        ]);
        $this->createVariant($inactive, [
            'name' => 'استاندارد',
            'sku' => 'WIN-GIFT-INACTIVE-STD',
            'stock_quantity' => 10,
        ]);

        $this->getJson('/api/catalog/products?inStock=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/catalog/products/inactive-product')
            ->assertNotFound();
    }

    private function createProduct(BakeryCategory $category, array $attributes = []): BakeryProduct
    {
        return BakeryProduct::create([
            'category_id' => $category->id,
            'name' => 'محصول آزمایشی',
            'slug' => 'test-product-'.uniqid(),
            'product_code' => 'WIN-TEST-'.uniqid(),
            'short_description' => 'توضیح کوتاه محصول',
            'content_verified' => true,
            'media_verified' => true,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function createVariant(BakeryProduct $product, array $attributes = [])
    {
        return $product->variants()->create([
            'name' => 'انتخاب استاندارد',
            'sku' => 'WIN-SKU-'.uniqid(),
            'regular_price_toman' => 100000,
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'inventory_verified' => true,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
