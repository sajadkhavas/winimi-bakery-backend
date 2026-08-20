<?php

namespace Tests\Feature;

use App\Models\BakeryCategory;
use App\Models\BakeryMediaAsset;
use App\Models\BakeryProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportLaunchMediaTest extends TestCase
{
    use RefreshDatabase;

    private ?string $stage = null;

    protected function tearDown(): void
    {
        if (
            is_string($this->stage)
            && is_dir($this->stage)
        ) {
            File::deleteDirectory(
                $this->stage
            );
        }

        parent::tearDown();
    }

    private function configureMediaTesting(): void
    {
        Storage::fake('public');

        config([
            'filesystems.default' => 'public',
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);
    }

    private function canonicalManifest(): array
    {
        $contents = file_get_contents(
            base_path(
                'database/data/winimi-launch-media-manifest-v1.json'
            )
        );

        $this->assertNotFalse($contents);

        return json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function createCanonicalProducts(): void
    {
        foreach (
            $this->canonicalManifest()['products'] as $index => $definition
        ) {
            $category =
                BakeryCategory::query()
                    ->create([
                        'name' => $definition['category'],

                        'slug' => 'import-test-category-'
                            .$index,

                        'is_active' => false,

                        'sort_order' => $index,
                    ]);

            $product =
                BakeryProduct::query()
                    ->create([
                        'category_id' => $category->getKey(),

                        'name' => $definition['name'],

                        'slug' => 'import-test-product-'
                            .$index,

                        'product_code' => $definition[
                                'productCode'
                            ],

                        'content_verified' => false,

                        'media_verified' => false,

                        'is_active' => false,

                        'sort_order' => $index,
                    ]);

            $product
                ->forceFill([
                    'public_id' => $definition[
                            'publicId'
                        ],
                ])
                ->save();
        }
    }

    private function makeStage(): string
    {
        $stage =
            sys_get_temp_dir()
            .'/winimi-launch-media-'
            .Str::uuid();

        File::makeDirectory(
            $stage.'/originals',
            0700,
            true,
        );

        File::makeDirectory(
            $stage.'/manifest',
            0700,
            true,
        );

        $sourceManifest =
            base_path(
                'database/data/winimi-launch-media-manifest-v1.json'
            );

        File::copy(
            $sourceManifest,
            $stage
                .'/manifest/'
                .'winimi-launch-media-manifest-v1.json',
        );

        $manifest =
            $this->canonicalManifest();

        $filenames = [];

        foreach (
            $manifest['products'] as $product
        ) {
            foreach (
                $product['images'] as $image
            ) {
                $filenames[] =
                    $image['sourceFilename'];
            }
        }

        foreach (
            $manifest['brandAssets'] as $asset
        ) {
            $filenames[] =
                $asset['sourceFilename'];
        }

        $this->assertCount(
            15,
            $filenames,
        );

        $this->assertCount(
            15,
            array_unique($filenames),
        );

        $checksumLines = [];

        foreach (
            array_values($filenames) as $index => $filename
        ) {
            $path =
                $stage
                .'/originals/'
                .$filename;

            $image =
                imagecreatetruecolor(
                    640,
                    480,
                );

            $this->assertNotFalse($image);

            $color =
                imagecolorallocate(
                    $image,
                    ($index * 31) % 255,
                    ($index * 47) % 255,
                    ($index * 67) % 255,
                );

            imagefill(
                $image,
                0,
                0,
                $color,
            );

            imagejpeg(
                $image,
                $path,
                88,
            );

            imagedestroy($image);

            $this->assertFileExists(
                $path
            );

            $checksumLines[] =
                hash_file(
                    'sha256',
                    $path,
                )
                .'  originals/'
                .$filename;
        }

        file_put_contents(
            $stage
                .'/manifest/'
                .'SHA256SUMS.txt',
            implode(
                PHP_EOL,
                $checksumLines,
            ).PHP_EOL,
        );

        $this->stage = $stage;

        return $stage;
    }

    public function test_dry_run_validates_full_manifest_without_writing_media(): void
    {
        $this->configureMediaTesting();
        $this->createCanonicalProducts();

        $stage = $this->makeStage();

        $exit = Artisan::call(
            'media:import-launch',
            [
                'stage' => $stage,
            ],
        );

        $output = Artisan::output();

        $this->assertSame(
            0,
            $exit,
            $output,
        );

        $this->assertStringContainsString(
            'DRY_RUN=PASS',
            $output,
        );

        $this->assertSame(
            0,
            BakeryMediaAsset::query()->count(),
        );

        foreach (
            File::files(
                $stage.'/originals'
            ) as $file
        ) {
            $this->assertFileExists(
                $file->getPathname()
            );
        }
    }

    public function test_apply_imports_all_assets_assigns_products_and_is_idempotent(): void
    {
        $this->configureMediaTesting();
        $this->createCanonicalProducts();

        $stage = $this->makeStage();

        $exit = Artisan::call(
            'media:import-launch',
            [
                'stage' => $stage,
                '--apply' => true,
            ],
        );

        $this->assertSame(
            0,
            $exit,
            Artisan::output(),
        );

        $this->assertSame(
            15,
            BakeryMediaAsset::query()->count(),
        );

        $this->assertSame(
            14,
            BakeryMediaAsset::query()
                ->where(
                    'status',
                    BakeryMediaAsset::STATUS_ASSIGNED,
                )
                ->count(),
        );

        $brand =
            BakeryMediaAsset::query()
                ->where(
                    'usage',
                    BakeryMediaAsset::USAGE_BRAND,
                )
                ->first();

        $this->assertNotNull($brand);

        $this->assertSame(
            BakeryMediaAsset::STATUS_READY,
            $brand->status,
        );

        $this->assertNull(
            $brand->product_id
        );

        $manifest =
            $this->canonicalManifest();

        foreach (
            $manifest['products'] as $definition
        ) {
            $product =
                BakeryProduct::query()
                    ->where(
                        'product_code',
                        $definition[
                            'productCode'
                        ],
                    )
                    ->firstOrFail();

            $product->unsetRelation(
                'media'
            );

            $expectedMain = 0;
            $expectedGallery = 0;

            foreach (
                $definition['images'] as $image
            ) {
                if (in_array(
                    'product_main',
                    $image['roles'],
                    true,
                )) {
                    $expectedMain++;
                }

                if (in_array(
                    'product_gallery',
                    $image['roles'],
                    true,
                )) {
                    $expectedGallery++;
                }
            }

            $this->assertSame(
                $expectedMain,
                $product
                    ->getMedia(
                        'catalog-main'
                    )
                    ->count(),
            );

            $this->assertSame(
                $expectedGallery,
                $product
                    ->getMedia(
                        'catalog-gallery'
                    )
                    ->count(),
            );

            $this->assertFalse(
                $product
                    ->fresh()
                    ->media_verified
            );
        }

        foreach (
            BakeryMediaAsset::query()->get() as $asset
        ) {
            $this->assertNotNull(
                $asset->sourceMedia()
            );

            $this->assertNotNull(
                $asset->import_key
            );

            $this->assertMatchesRegularExpression(
                '/^[a-f0-9]{64}$/',
                (string) $asset->source_sha256,
            );
        }

        foreach (
            File::files(
                $stage.'/originals'
            ) as $file
        ) {
            $this->assertFileExists(
                $file->getPathname()
            );
        }

        $secondExit = Artisan::call(
            'media:import-launch',
            [
                'stage' => $stage,
                '--apply' => true,
            ],
        );

        $secondOutput = Artisan::output();

        $this->assertSame(
            0,
            $secondExit,
            $secondOutput,
        );

        $this->assertStringContainsString(
            'NEW_MEDIA=0',
            $secondOutput,
        );

        $this->assertSame(
            15,
            BakeryMediaAsset::query()->count(),
        );
    }

    public function test_existing_product_main_blocks_import_before_any_write(): void
    {
        $this->configureMediaTesting();
        $this->createCanonicalProducts();

        $stage = $this->makeStage();

        $product =
            BakeryProduct::query()
                ->where(
                    'product_code',
                    'VIN-CW-001',
                )
                ->firstOrFail();

        $product
            ->addMedia(
                UploadedFile::fake()
                    ->image(
                        'existing-main.jpg',
                        800,
                        600,
                    )
            )
            ->toMediaCollection(
                'catalog-main'
            );

        $exit = Artisan::call(
            'media:import-launch',
            [
                'stage' => $stage,
            ],
        );

        $this->assertSame(
            1,
            $exit,
        );

        $this->assertStringContainsString(
            'MAIN_CONFLICT=VIN-CW-001',
            Artisan::output(),
        );

        $this->assertSame(
            0,
            BakeryMediaAsset::query()->count(),
        );

        $product->unsetRelation(
            'media'
        );

        $this->assertSame(
            1,
            $product
                ->getMedia(
                    'catalog-main'
                )
                ->count(),
        );
    }

    public function test_checksum_mismatch_fails_closed_without_database_write(): void
    {
        $this->configureMediaTesting();
        $this->createCanonicalProducts();

        $stage = $this->makeStage();

        file_put_contents(
            $stage
                .'/originals/'
                .'IMG_8944.JPG',
            'tampered',
        );

        $exit = Artisan::call(
            'media:import-launch',
            [
                'stage' => $stage,
                '--apply' => true,
            ],
        );

        $this->assertSame(
            1,
            $exit,
        );

        $this->assertStringContainsString(
            'CHECKSUM_MISMATCH=IMG_8944.JPG',
            Artisan::output(),
        );

        $this->assertSame(
            0,
            BakeryMediaAsset::query()->count(),
        );
    }
}
