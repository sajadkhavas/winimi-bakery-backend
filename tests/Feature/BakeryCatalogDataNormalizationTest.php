<?php

namespace Tests\Feature;

use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BakeryCatalogDataNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_tag_fields_are_normalized_and_manual_slug_is_stable_on_update(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'slug' => 'stable-product-url',
            'ingredients' => 'آرد، شکر, کره',
            'allergens' => "گلوتن\nلبنیات",
            'content_verified' => true,
        ]);
        $this->createVariant($product);

        $this->assertSame(['آرد', 'شکر', 'کره'], $product->fresh()->ingredients);
        $this->assertSame(['گلوتن', 'لبنیات'], $product->fresh()->allergens);

        $product->update([
            'name' => 'نام ویرایش‌شده محصول',
            'short_description' => 'توضیح ویرایش‌شده',
        ]);

        $this->assertSame('stable-product-url', $product->fresh()->slug);

        $this->getJson('/api/catalog/products/stable-product-url')
            ->assertOk()
            ->assertJsonPath('data.ingredients.0', 'آرد')
            ->assertJsonPath('data.ingredients.1', 'شکر')
            ->assertJsonPath('data.ingredients.2', 'کره')
            ->assertJsonPath('data.allergens.0', 'گلوتن')
            ->assertJsonPath('data.allergens.1', 'لبنیات');
    }

    public function test_catalog_api_repairs_legacy_string_shaped_tag_values_at_the_boundary(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category, [
            'slug' => 'legacy-tag-payload',
            'content_verified' => true,
        ]);
        $this->createVariant($product);

        DB::table('bakery_products')
            ->where('id', $product->id)
            ->update([
                'ingredients' => json_encode('آرد، شکر، کره', JSON_UNESCAPED_UNICODE),
                'allergens' => json_encode('گلوتن, لبنیات', JSON_UNESCAPED_UNICODE),
            ]);

        $this->getJson('/api/catalog/products/legacy-tag-payload')
            ->assertOk()
            ->assertJsonPath('data.ingredients.0', 'آرد')
            ->assertJsonPath('data.ingredients.1', 'شکر')
            ->assertJsonPath('data.ingredients.2', 'کره')
            ->assertJsonPath('data.allergens.0', 'گلوتن')
            ->assertJsonPath('data.allergens.1', 'لبنیات');
    }

    private function createCategory(): BakeryCategory
    {
        return BakeryCategory::create([
            'name' => 'دسته آزمایشی',
            'slug' => 'normalization-category',
            'is_active' => true,
        ]);
    }

    private function createProduct(BakeryCategory $category, array $attributes = []): BakeryProduct
    {
        return BakeryProduct::create([
            'category_id' => $category->id,
            'name' => 'محصول آزمایشی',
            'slug' => 'normalization-product',
            'product_code' => 'WIN-NORMALIZE-'.uniqid(),
            'short_description' => 'توضیح کوتاه محصول',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function createVariant(BakeryProduct $product): void
    {
        $product->variants()->create([
            'name' => 'بسته استاندارد',
            'sku' => 'WIN-NORMALIZE-SKU-'.uniqid(),
            'regular_price_toman' => 100000,
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
