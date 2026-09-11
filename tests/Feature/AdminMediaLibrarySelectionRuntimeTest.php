<?php

namespace Tests\Feature;

use App\Filament\Resources\BakeryCategoryResource\Pages\EditBakeryCategory;
use App\Filament\Resources\BakeryMediaAssetResource\Pages\ManageBakeryMediaAssets;
use App\Models\BakeryCategory;
use App\Models\BakeryMediaAsset;
use App\Models\User;
use App\Support\AdminMediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminMediaLibrarySelectionRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_library_image_can_replace_a_bakery_category_image_without_server_error(): void
    {
        $this->configureMediaTesting();
        $this->actingAs($this->operator());

        $asset = BakeryMediaAsset::query()->create([
            'title' => 'تصویر دسته جدید',
            'usage' => BakeryMediaAsset::USAGE_CATEGORY,
            'status' => BakeryMediaAsset::STATUS_PENDING,
        ]);

        $asset
            ->addMedia(UploadedFile::fake()->image('category-new.jpg', 1200, 900))
            ->toMediaCollection('source');

        $asset->refresh();
        $asset->update(['status' => BakeryMediaAsset::STATUS_READY]);

        $path = array_key_first(AdminMediaLibrary::imagePathOptions());
        $this->assertIsString($path);
        $this->assertNotSame('', $path);

        $category = BakeryCategory::query()->create([
            'name' => 'دسته تست رسانه',
            'slug' => 'media-category-test',
            'description' => 'توضیح تست',
            'image_path' => null,
            'image_alt' => null,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        Livewire::test(EditBakeryCategory::class, [
            'record' => $category->getRouteKey(),
        ])
            ->fillForm([
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_path' => $path,
                'image_alt' => 'تصویر دسته تست رسانه',
                'is_active' => true,
                'sort_order' => 10,
                'meta_title' => null,
                'meta_description' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($path, $category->fresh()->image_path);
    }

    public function test_broken_library_asset_cannot_take_down_media_selectors_or_library_table(): void
    {
        $this->configureMediaTesting();
        $this->actingAs($this->operator());

        $asset = BakeryMediaAsset::query()->create([
            'title' => 'رسانه خراب قدیمی',
            'usage' => BakeryMediaAsset::USAGE_CATEGORY,
            'status' => BakeryMediaAsset::STATUS_PENDING,
        ]);

        $asset->forceFill(['status' => BakeryMediaAsset::STATUS_READY])->saveQuietly();

        Media::query()->create([
            'model_type' => $asset->getMorphClass(),
            'model_id' => $asset->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'source',
            'name' => 'broken-source',
            'file_name' => 'broken-source.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'missing-media-disk',
            'conversions_disk' => 'missing-media-disk',
            'size' => 1000,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [
                'thumb' => true,
                'preview' => true,
            ],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $options = AdminMediaLibrary::imagePathOptions('legacy/category.webp');

        $this->assertArrayHasKey('legacy/category.webp', $options);
        $this->assertSame(
            'تصویر فعلی (Legacy / نیازمند بازبینی کتابخانه)',
            $options['legacy/category.webp'],
        );

        $asset->unsetRelation('media');
        $asset->load('media');
        $this->assertFalse($asset->publicPreviewWithinBudget());
        $this->assertContains($asset->conversionState(), ['pending', 'broken']);
        $this->assertNull($asset->previewUrl());
        $this->assertNull($asset->optimizedUrl());
        $this->assertSame('نامشخص', $asset->dimensionsLabel());

        Livewire::test(ManageBakeryMediaAssets::class)
            ->assertOk();
    }

    public function test_path_selector_never_exposes_preview_from_non_public_conversion_disk(): void
    {
        $this->configureMediaTesting();
        Storage::fake('private-media');
        config(['media-library.conversions_disk_name' => 'private-media']);

        $asset = BakeryMediaAsset::query()->create([
            'title' => 'رسانه با conversion خصوصی',
            'usage' => BakeryMediaAsset::USAGE_CATEGORY,
            'status' => BakeryMediaAsset::STATUS_PENDING,
        ]);

        $asset
            ->addMedia(UploadedFile::fake()->image('private-preview.jpg', 1200, 900))
            ->toMediaCollection('source');

        $asset->refresh();
        $asset->update(['status' => BakeryMediaAsset::STATUS_READY]);

        $media = $asset->sourceMedia();
        $this->assertNotNull($media);
        $this->assertSame('private-media', $media->conversions_disk);
        $this->assertTrue($asset->publicPreviewWithinBudget());

        $options = AdminMediaLibrary::imagePathOptions('legacy/category.webp');

        $this->assertCount(1, $options);
        $this->assertArrayHasKey('legacy/category.webp', $options);
        $this->assertSame(
            'تصویر فعلی (Legacy / نیازمند بازبینی کتابخانه)',
            $options['legacy/category.webp'],
        );
    }

    private function configureMediaTesting(): void
    {
        Storage::fake('public');

        config([
            'filesystems.default' => 'public',
            'media-library.disk_name' => 'public',
            'media-library.conversions_disk_name' => null,
            'media-library.queue_conversions_by_default' => false,
        ]);
    }

    private function operator(): User
    {
        Role::findOrCreate('super_admin', 'web');

        $user = User::query()->create([
            'name' => 'Admin Media Selection',
            'email' => 'admin-media-selection@example.test',
            'password' => 'admin-media-selection-password',
        ]);
        $user->assignRole('super_admin');

        return $user;
    }
}
