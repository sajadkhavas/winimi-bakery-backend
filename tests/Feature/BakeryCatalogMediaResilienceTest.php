<?php

namespace Tests\Feature;

use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use App\Models\BakeryProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class BakeryCatalogMediaResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_stale_product_media_row_cannot_take_down_public_catalog(): void
    {
        $category = BakeryCategory::query()->create([
            'name' => 'دسته رسانه',
            'slug' => 'media-resilience',
            'is_active' => true,
        ]);

        $product = BakeryProduct::query()->create([
            'category_id' => $category->getKey(),
            'name' => 'محصول با رسانه خراب',
            'slug' => 'stale-media-product',
            'product_code' => 'WIN-MEDIA-STALE',
            'content_verified' => true,
            'media_verified' => true,
            'is_active' => true,
        ]);

        BakeryProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'name' => 'استاندارد',
            'sku' => 'WIN-MEDIA-STALE-1',
            'regular_price_toman' => 100000,
            'stock_quantity' => 2,
            'low_stock_threshold' => 1,
            'inventory_verified' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        Media::query()->create([
            'model_type' => $product->getMorphClass(),
            'model_id' => $product->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'catalog-main',
            'name' => 'stale-product-image',
            'file_name' => 'stale-product-image.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'missing-product-media-disk',
            'conversions_disk' => 'missing-product-media-disk',
            'size' => 1500,
            'manipulations' => [],
            'custom_properties' => ['alt' => 'تصویر تست'],
            'generated_conversions' => [
                'thumb' => true,
                'card' => true,
                'detail' => true,
            ],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $this->getJson('/api/catalog/products/stale-media-product')
            ->assertOk()
            ->assertJsonPath('data.slug', 'stale-media-product')
            ->assertJsonPath('data.mediaVerified', true)
            ->assertJsonCount(0, 'data.images');

        $this->getJson('/api/catalog/products?category=media-resilience')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(0, 'data.0.images');
    }
}
