<?php

namespace Tests\Feature;

use App\Models\BakeryCategory;
use App\Models\BakeryMediaAsset;
use App\Models\BakeryProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BakeryMediaLibraryTest extends TestCase
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

    public function test_media_asset_preserves_source_and_generates_webp_preview_variants(): void
    {
        $this->configureMediaTesting();

        $asset = BakeryMediaAsset::query()->create([
            'title' => 'عکس واقعی کوکی',
            'usage' => BakeryMediaAsset::USAGE_UNASSIGNED,
            'status' => BakeryMediaAsset::STATUS_PENDING,
        ]);

        $media = $asset
            ->addMedia(
                UploadedFile::fake()->image(
                    'real-cookie.jpg',
                    1800,
                    1400,
                ),
            )
            ->toMediaCollection('source');

        $media->refresh();

        $this->assertSame(
            'image/jpeg',
            $media->mime_type,
        );

        $this->assertTrue(
            $media->hasGeneratedConversion(
                'thumb'
            ),
        );

        $this->assertTrue(
            $media->hasGeneratedConversion(
                'preview'
            ),
        );

        $this->assertStringEndsWith(
            '.webp',
            $media->getPath('thumb'),
        );

        $this->assertStringEndsWith(
            '.webp',
            $media->getPath('preview'),
        );

        $this->assertNotNull(
            $asset->fresh()->sourceMedia(),
        );

        $this->assertSame(
            BakeryMediaAsset::STATUS_PENDING,
            $asset->fresh()->status,
        );
    }

    public function test_media_asset_can_exist_unassigned_until_review(): void
    {
        $asset = BakeryMediaAsset::query()->create([
            'title' => 'عکس ورودی',
            'usage' => BakeryMediaAsset::USAGE_UNASSIGNED,
            'status' => BakeryMediaAsset::STATUS_PENDING,
        ]);

        $this->assertNull(
            $asset->product_id
        );

        $this->assertSame(
            BakeryMediaAsset::USAGE_UNASSIGNED,
            $asset->usage,
        );

        $this->assertSame(
            BakeryMediaAsset::STATUS_PENDING,
            $asset->status,
        );
    }

    private function createProduct(
        string $slug,
        string $code,
        bool $mediaVerified = false,
    ): BakeryProduct {
        $category =
            BakeryCategory::query()->create([
                'name' => 'دسته '.$slug,

                'slug' => 'category-'.$slug,

                'is_active' => false,

                'sort_order' => 0,
            ]);

        return BakeryProduct::query()
            ->create([
                'category_id' => $category->getKey(),

                'name' => 'محصول '.$slug,

                'slug' => $slug,

                'product_code' => $code,

                'media_verified' => $mediaVerified,

                'is_active' => false,
            ]);
    }

    private function readyAsset(
        string $title,
        string $fileName,
    ): BakeryMediaAsset {
        $asset =
            BakeryMediaAsset::query()->create([
                'title' => $title,

                'usage' => BakeryMediaAsset::USAGE_UNASSIGNED,

                'status' => BakeryMediaAsset::STATUS_READY,
            ]);

        $asset
            ->addMedia(
                UploadedFile::fake()->image(
                    $fileName,
                    1600,
                    1200,
                )
            )
            ->toMediaCollection(
                'source'
            );

        return $asset->fresh();
    }

    public function test_ready_asset_can_be_assigned_as_product_main_without_losing_source(): void
    {
        $this->configureMediaTesting();

        $product = $this->createProduct(
            'assignment-main',
            'MEDIA-ASSIGN-MAIN-001',
            true,
        );

        $asset = $this->readyAsset(
            'کوکی اصلی واقعی',
            'real-main.jpg',
        );

        $source = $asset->sourceMedia();

        $this->assertNotNull($source);

        $sourceId = $source->getKey();

        $copied = $asset->assignToProduct(
            $product,
            BakeryMediaAsset::USAGE_PRODUCT_MAIN,
            'تصویر واقعی کوکی وینیمی',
        );

        $asset->refresh();
        $product->refresh();

        $this->assertSame(
            BakeryMediaAsset::STATUS_ASSIGNED,
            $asset->status,
        );

        $this->assertSame(
            BakeryMediaAsset::USAGE_PRODUCT_MAIN,
            $asset->usage,
        );

        $this->assertSame(
            $product->getKey(),
            $asset->product_id,
        );

        $this->assertFalse(
            $product->media_verified,
        );

        $this->assertSame(
            $sourceId,
            $asset
                ->sourceMedia()
                ?->getKey(),
        );

        $this->assertSame(
            1,
            $product
                ->getMedia(
                    'catalog-main'
                )
                ->count(),
        );

        $this->assertSame(
            'تصویر واقعی کوکی وینیمی',
            $copied->getCustomProperty(
                'alt'
            ),
        );

        $this->assertSame(
            $asset->getKey(),
            $copied->getCustomProperty(
                'source_asset_id'
            ),
        );

        foreach ([
            'thumb',
            'card',
            'detail',
        ] as $conversion) {
            $this->assertTrue(
                $copied
                    ->hasGeneratedConversion(
                        $conversion
                    ),
            );
        }
    }

    public function test_existing_product_main_is_never_replaced_silently(): void
    {
        $this->configureMediaTesting();

        $product = $this->createProduct(
            'assignment-main-conflict',
            'MEDIA-MAIN-CONFLICT-001',
        );

        $existing =
            $product
                ->addMedia(
                    UploadedFile::fake()
                        ->image(
                            'existing-main.jpg',
                            1400,
                            1000,
                        )
                )
                ->toMediaCollection(
                    'catalog-main'
                );

        $asset = $this->readyAsset(
            'تصویر جایگزین',
            'replacement-main.jpg',
        );

        try {
            $asset->assignToProduct(
                $product,
                BakeryMediaAsset::USAGE_PRODUCT_MAIN,
            );

            $this->fail(
                'Expected main-image conflict.'
            );
        } catch (\DomainException $exception) {
            $this->assertStringContainsString(
                'تصویر اصلی',
                $exception->getMessage(),
            );
        }

        $asset->refresh();
        $product->unsetRelation('media');

        $this->assertSame(
            BakeryMediaAsset::STATUS_READY,
            $asset->status,
        );

        $this->assertNull(
            $asset->product_id,
        );

        $this->assertSame(
            1,
            $product
                ->getMedia(
                    'catalog-main'
                )
                ->count(),
        );

        $this->assertSame(
            $existing->getKey(),
            $product
                ->getFirstMedia(
                    'catalog-main'
                )
                ?->getKey(),
        );
    }

    public function test_multiple_ready_assets_can_be_assigned_to_product_gallery(): void
    {
        $this->configureMediaTesting();

        $product = $this->createProduct(
            'assignment-gallery',
            'MEDIA-GALLERY-001',
            true,
        );

        $first = $this->readyAsset(
            'گالری یک',
            'gallery-one.jpg',
        );

        $second = $this->readyAsset(
            'گالری دو',
            'gallery-two.jpg',
        );

        foreach ([
            $first,
            $second,
        ] as $asset) {
            $asset->assignToProduct(
                $product,
                BakeryMediaAsset::USAGE_PRODUCT_GALLERY,
                $asset->title,
            );
        }

        $product->refresh();
        $product->unsetRelation('media');

        $this->assertFalse(
            $product->media_verified,
        );

        $this->assertSame(
            2,
            $product
                ->getMedia(
                    'catalog-gallery'
                )
                ->count(),
        );

        foreach ([
            $first,
            $second,
        ] as $asset) {
            $fresh = $asset->fresh();

            $this->assertSame(
                BakeryMediaAsset::STATUS_ASSIGNED,
                $fresh->status,
            );

            $this->assertSame(
                BakeryMediaAsset::USAGE_PRODUCT_GALLERY,
                $fresh->usage,
            );

            $this->assertNotNull(
                $fresh->sourceMedia(),
            );
        }
    }

    public function test_pending_asset_cannot_bypass_review_gate(): void
    {
        $this->configureMediaTesting();

        $product = $this->createProduct(
            'assignment-pending',
            'MEDIA-PENDING-001',
        );

        $asset =
            BakeryMediaAsset::query()
                ->create([
                    'title' => 'تصویر بررسی‌نشده',

                    'usage' => BakeryMediaAsset::USAGE_UNASSIGNED,

                    'status' => BakeryMediaAsset::STATUS_PENDING,
                ]);

        $asset
            ->addMedia(
                UploadedFile::fake()->image(
                    'pending.jpg',
                    1200,
                    900,
                )
            )
            ->toMediaCollection(
                'source'
            );

        $this->expectException(
            \DomainException::class
        );

        $asset->assignToProduct(
            $product,
            BakeryMediaAsset::USAGE_PRODUCT_GALLERY,
        );
    }
}
