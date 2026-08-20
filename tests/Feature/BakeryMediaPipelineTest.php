<?php

namespace Tests\Feature;

use App\Http\Resources\BakeryProductResource;
use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BakeryMediaPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function configureMediaTesting(): void
    {
        Storage::fake('public');

        config([
            'filesystems.default' => 'public',
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);
    }

    private function category(string $slug): BakeryCategory
    {
        return BakeryCategory::query()->create([
            'name' => 'دسته رسانه',
            'slug' => $slug,
            'is_active' => false,
            'sort_order' => 0,
        ]);
    }

    private function product(
        BakeryCategory $category,
        string $slug,
        string $code,
        bool $verified = false,
    ): BakeryProduct {
        return BakeryProduct::query()->create([
            'category_id' => $category->getKey(),
            'name' => 'محصول تست رسانه',
            'slug' => $slug,
            'product_code' => $code,
            'media_verified' => $verified,
            'is_active' => false,
        ]);
    }

    public function test_media_generates_webp_variants_and_invalidates_verification(): void
    {
        $this->configureMediaTesting();

        $product = $this->product(
            $this->category('media-conversion-category'),
            'media-conversion-product',
            'MEDIA-CONVERSION-001',
            true,
        );

        $media = $product
            ->addMedia(
                UploadedFile::fake()->image(
                    'cookie.jpg',
                    1800,
                    1400,
                ),
            )
            ->toMediaCollection('catalog-main');

        $product->refresh();
        $media->refresh();

        $this->assertFalse(
            $product->media_verified
        );

        foreach ([
            'thumb',
            'card',
            'detail',
        ] as $conversion) {
            $this->assertTrue(
                $media->hasGeneratedConversion(
                    $conversion
                ),
            );

            $this->assertStringEndsWith(
                '.webp',
                $media->getPath($conversion),
            );
        }

        $this->assertSame(
            1800,
            $media->getCustomProperty('width'),
        );

        $this->assertSame(
            1400,
            $media->getCustomProperty('height'),
        );

        $this->assertSame(
            'image/jpeg',
            $media->getCustomProperty(
                'detected_mime'
            ),
        );
    }

    public function test_resource_exposes_backward_compatible_and_responsive_media_fields(): void
    {
        $this->configureMediaTesting();

        $product = $this->product(
            $this->category('media-api-category'),
            'media-api-product',
            'MEDIA-API-001',
        );

        $product
            ->addMedia(
                UploadedFile::fake()->image(
                    'cookie-api.jpg',
                    1600,
                    1200,
                ),
            )
            ->withCustomProperties([
                'alt' => 'تصویر واقعی تست رسانه',
            ])
            ->toMediaCollection('catalog-main');

        $product
            ->forceFill([
                'media_verified' => true,
            ])
            ->saveQuietly();

        $product->load([
            'category',
            'activeVariants',
            'media',
        ]);

        $payload = (
            new BakeryProductResource($product)
        )->toArray(
            Request::create('/')
        );

        $this->assertCount(
            1,
            $payload['images']
        );

        $image = $payload['images'][0];

        $this->assertSame(
            'تصویر واقعی تست رسانه',
            $image['alt'],
        );

        $this->assertTrue(
            $image['verified']
        );

        $this->assertNotEmpty(
            $image['url']
        );

        $this->assertNotEmpty(
            $image['originalUrl']
        );

        $this->assertNotEmpty(
            $image['thumbnailUrl']
        );

        $this->assertNotEmpty(
            $image['cardUrl']
        );

        $this->assertNotEmpty(
            $image['detailUrl']
        );

        $this->assertSame(
            1600,
            $image['width'],
        );

        $this->assertSame(
            1200,
            $image['height'],
        );

        $this->assertSame(
            'image/webp',
            $image['mimeType'],
        );
    }

    public function test_removing_verified_media_invalidates_verification(): void
    {
        $this->configureMediaTesting();

        $product = $this->product(
            $this->category('media-delete-category'),
            'media-delete-product',
            'MEDIA-DELETE-001',
        );

        $media = $product
            ->addMedia(
                UploadedFile::fake()->image(
                    'cookie-delete.jpg',
                    1200,
                    900,
                ),
            )
            ->toMediaCollection('catalog-main');

        $product
            ->forceFill([
                'media_verified' => true,
            ])
            ->saveQuietly();

        $media->delete();

        $this->assertFalse(
            $product
                ->fresh()
                ->media_verified
        );
    }
}
