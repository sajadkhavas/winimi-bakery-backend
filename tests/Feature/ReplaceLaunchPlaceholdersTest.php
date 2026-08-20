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

class ReplaceLaunchPlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (
            array_reverse(
                $this->cleanup
            ) as $path
        ) {
            if (is_dir($path)) {
                File::deleteDirectory(
                    $path
                );
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function configureMedia(): void
    {
        Storage::fake('public');

        config([
            'filesystems.default' => 'public',
            'media-library.disk_name' => 'public',
            'media-library.queue_conversions_by_default' => false,
        ]);
    }

    private function manifest(): array
    {
        return json_decode(
            file_get_contents(
                base_path(
                    'database/data/winimi-launch-media-manifest-v1.json'
                )
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function createProducts(): void
    {
        foreach (
            $this->manifest()['products'] as $index => $definition
        ) {
            $category =
                BakeryCategory::query()
                    ->create([
                        'name' => $definition[
                                'category'
                            ],
                        'slug' => 'replace-category-'
                            .$index,
                        'is_active' => false,
                        'sort_order' => $index,
                    ]);

            $product =
                BakeryProduct::query()
                    ->create([
                        'category_id' => $category->getKey(),
                        'name' => $definition[
                                'name'
                            ],
                        'slug' => 'replace-product-'
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
            .'/winimi-stage-'
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

        File::copy(
            base_path(
                'database/data/winimi-launch-media-manifest-v1.json'
            ),
            $stage
                .'/manifest/'
                .'winimi-launch-media-manifest-v1.json',
        );

        $names = [];

        foreach (
            $this->manifest()['products'] as $product
        ) {
            foreach (
                $product['images'] as $image
            ) {
                $names[] =
                    $image[
                        'sourceFilename'
                    ];
            }
        }

        foreach (
            $this->manifest()[
                'brandAssets'
            ] as $asset
        ) {
            $names[] =
                $asset[
                    'sourceFilename'
                ];
        }

        $lines = [];

        foreach (
            $names as $index => $name
        ) {
            $path =
                $stage
                .'/originals/'
                .$name;

            $image =
                imagecreatetruecolor(
                    640,
                    480,
                );

            $this->assertNotFalse(
                $image
            );

            $color =
                imagecolorallocate(
                    $image,
                    ($index * 29) % 255,
                    ($index * 43) % 255,
                    ($index * 71) % 255,
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

            imagedestroy(
                $image
            );

            $lines[] =
                hash_file(
                    'sha256',
                    $path,
                )
                .'  originals/'
                .$name;
        }

        file_put_contents(
            $stage
                .'/manifest/'
                .'SHA256SUMS.txt',
            implode(
                PHP_EOL,
                $lines,
            ).PHP_EOL,
        );

        $this->cleanup[] =
            $stage;

        return $stage;
    }

    private function makeReplacementContract(): array
    {
        $manifest =
            $this->manifest();

        $mainByCode = [];

        foreach (
            $manifest['products'] as $definition
        ) {
            foreach (
                $definition['images'] as $image
            ) {
                if (in_array(
                    'product_main',
                    $image['roles'],
                    true,
                )) {
                    $mainByCode[
                        $definition[
                            'productCode'
                        ]
                    ] = $image[
                        'sourceFilename'
                    ];
                }
            }
        }

        $products = [];
        $old = [];

        foreach (
            $manifest['products'] as $index => $definition
        ) {
            $code =
                $definition[
                    'productCode'
                ];

            $product =
                BakeryProduct::query()
                    ->where(
                        'product_code',
                        $code,
                    )
                    ->firstOrFail();

            $oldName =
                'old-placeholder-'
                .$index
                .'.jpg';

            $media =
                $product
                    ->addMedia(
                        UploadedFile::fake()
                            ->image(
                                $oldName,
                                800,
                                600,
                            )
                    )
                    ->toMediaCollection(
                        'catalog-main'
                    );

            /*
             * Placeholder واقعی Production
             * custom_properties خالی دارد.
             */
            $media
                ->forceFill([
                    'custom_properties' => [],
                ])
                ->save();

            $sha =
                hash_file(
                    'sha256',
                    $media->getPath(),
                );

            $this->assertIsString(
                $sha
            );

            $old[$code] = [
                'filename' => $media->file_name,
                'sha256' => $sha,
            ];

            $products[] = [
                'productCode' => $code,
                'publicId' => $definition[
                        'publicId'
                    ],
                'name' => $definition[
                        'name'
                    ],
                'existingMain' => [
                    'mediaId' => $media->getKey(),
                    'filename' => $media->file_name,
                    'mimeType' => $media->mime_type,
                    'sizeBytes' => $media->size,
                    'sha256' => $sha,
                    'customPropertiesMustBeEmpty' => true,
                ],
                'replacementSource' => $mainByCode[$code],
            ];
        }

        $contract = [
            'schemaVersion' => 1,
            'environment' => 'production',
            'capturedAt' => '2026-08-20',
            'capturedFromRelease' => 'test',
            'purpose' => 'controlled-placeholder-main-replacement',
            'policy' => [
                'automaticUnknownMediaDeletionAllowed' => false,
                'explicitReplacementFlagRequired' => true,
                'exactExistingMediaIdentityRequired' => true,
                'existingOriginalMustBeBackedUpBeforeDeletion' => true,
                'replacementFailureMustRestorePreviousMain' => true,
                'mediaVerifiedAutoEnableAllowed' => false,
                'galleryDeletionAllowed' => false,
                'giftBoxIncluded' => false,
            ],
            'products' => $products,
        ];

        $path =
            sys_get_temp_dir()
            .'/winimi-contract-'
            .Str::uuid()
            .'.json';

        file_put_contents(
            $path,
            json_encode(
                $contract,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
                | JSON_THROW_ON_ERROR,
            ),
        );

        $this->cleanup[] =
            $path;

        return [
            'path' => $path,
            'old' => $old,
        ];
    }

    private function backupDir(): string
    {
        $path =
            sys_get_temp_dir()
            .'/winimi-backup-'
            .Str::uuid();

        $this->cleanup[] =
            $path;

        return $path;
    }

    public function test_dry_run_validates_exact_placeholders_without_writing(): void
    {
        $this->configureMedia();
        $this->createProducts();

        $stage =
            $this->makeStage();

        $contract =
            $this->makeReplacementContract();

        $exit = Artisan::call(
            'media:replace-launch-placeholders',
            [
                'stage' => $stage,
                '--replacement-contract' => $contract['path'],
            ],
        );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit,
            $output,
        );

        $this->assertStringContainsString(
            'PLACEHOLDER_REPLACEMENT_DRY_RUN=PASS',
            $output,
        );

        $this->assertSame(
            0,
            BakeryMediaAsset::query()
                ->count(),
        );

        foreach (
            $this->manifest()['products'] as $definition
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

            $this->assertSame(
                1,
                $product
                    ->getMedia(
                        'catalog-main'
                    )
                    ->count(),
            );
        }
    }

    public function test_apply_backs_up_exact_placeholders_and_imports_real_media(): void
    {
        $this->configureMedia();
        $this->createProducts();

        $stage =
            $this->makeStage();

        $contract =
            $this->makeReplacementContract();

        $backup =
            $this->backupDir();

        $exit = Artisan::call(
            'media:replace-launch-placeholders',
            [
                'stage' => $stage,
                '--apply' => true,
                '--replacement-contract' => $contract['path'],
                '--backup-dir' => $backup,
            ],
        );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit,
            $output,
        );

        $this->assertStringContainsString(
            'POST_IMPORT_VERIFICATION=PASS',
            $output,
        );

        $this->assertSame(
            15,
            BakeryMediaAsset::query()
                ->count(),
        );

        foreach (
            $this->manifest()['products'] as $definition
        ) {
            $code =
                $definition[
                    'productCode'
                ];

            $product =
                BakeryProduct::query()
                    ->where(
                        'product_code',
                        $code,
                    )
                    ->firstOrFail();

            $product->unsetRelation(
                'media'
            );

            $main =
                $product->getFirstMedia(
                    'catalog-main'
                );

            $this->assertNotNull(
                $main
            );

            $this->assertNotSame(
                $contract[
                    'old'
                ][$code][
                    'filename'
                ],
                $main->file_name,
            );

            $this->assertNotNull(
                $main->getCustomProperty(
                    'source_asset_id'
                )
            );

            $backupFiles =
                glob(
                    $backup
                    .'/'
                    .$code
                    .'/*'
                );

            $this->assertIsArray(
                $backupFiles
            );

            $originalFiles =
                array_values(
                    array_filter(
                        $backupFiles,
                        static fn (
                            string $file
                        ): bool => ! str_ends_with(
                            $file,
                            '.metadata.json',
                        ),
                    )
                );

            $this->assertCount(
                1,
                $originalFiles,
            );

            $this->assertSame(
                $contract[
                    'old'
                ][$code][
                    'sha256'
                ],
                hash_file(
                    'sha256',
                    $originalFiles[0],
                ),
            );

            $this->assertFalse(
                $product
                    ->fresh()
                    ->media_verified
            );
        }
    }

    public function test_changed_placeholder_sha_fails_before_any_write(): void
    {
        $this->configureMedia();
        $this->createProducts();

        $stage =
            $this->makeStage();

        $contract =
            $this->makeReplacementContract();

        $data =
            json_decode(
                file_get_contents(
                    $contract['path']
                ),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

        $data[
            'products'
        ][0][
            'existingMain'
        ][
            'sha256'
        ] = str_repeat(
            '0',
            64,
        );

        file_put_contents(
            $contract['path'],
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
                | JSON_THROW_ON_ERROR,
            ),
        );

        $exit = Artisan::call(
            'media:replace-launch-placeholders',
            [
                'stage' => $stage,
                '--replacement-contract' => $contract['path'],
            ],
        );

        $output =
            Artisan::output();

        $this->assertSame(
            1,
            $exit,
            $output,
        );

        $this->assertStringContainsString(
            'PLACEHOLDER_SHA256_MISMATCH=',
            $output,
        );

        $this->assertSame(
            0,
            BakeryMediaAsset::query()
                ->count(),
        );
    }

    public function test_import_failure_restores_all_old_main_images(): void
    {
        $this->configureMedia();
        $this->createProducts();

        $stage =
            $this->makeStage();

        $contract =
            $this->makeReplacementContract();

        $backup =
            $this->backupDir();

        /*
         * Importer پس از حذف Placeholderها باید fail شود:
         * import_key معتبر ولی Source Library ناقص.
         */
        $manifest =
            $this->manifest();

        $first =
            $manifest[
                'products'
            ][0];

        $main =
            collect(
                $first['images']
            )->first(
                fn (array $image): bool => in_array(
                    'product_main',
                    $image['roles'],
                    true,
                )
            );

        $this->assertIsArray(
            $main
        );

        $file =
            $stage
            .'/originals/'
            .$main[
                'sourceFilename'
            ];

        $sha =
            hash_file(
                'sha256',
                $file,
            );

        $this->assertIsString(
            $sha
        );

        $identity =
            '1'
            .'|'
            .$first[
                'productCode'
            ]
            .'|product_main|'
            .$main[
                'sourceFilename'
            ]
            .'|'
            .$sha;

        $collision =
            BakeryMediaAsset::query()
                ->create([
                    'title' => 'intentional-test-collision',
                    'import_key' => 'launch-v1:'
                        .hash(
                            'sha256',
                            $identity,
                        ),
                    'source_filename' => $main[
                            'sourceFilename'
                        ],
                    'source_sha256' => $sha,
                    'manifest_version' => 1,
                    'usage' => BakeryMediaAsset::USAGE_UNASSIGNED,
                    'status' => BakeryMediaAsset::STATUS_READY,
                ]);

        $exit = Artisan::call(
            'media:replace-launch-placeholders',
            [
                'stage' => $stage,
                '--apply' => true,
                '--replacement-contract' => $contract['path'],
                '--backup-dir' => $backup,
            ],
        );

        $output =
            Artisan::output();

        $this->assertSame(
            1,
            $exit,
            $output,
        );

        $this->assertStringContainsString(
            'PLACEHOLDER_ROLLBACK=PASS',
            $output,
        );

        $this->assertTrue(
            $collision->fresh()->exists
        );

        foreach (
            $manifest['products'] as $definition
        ) {
            $code =
                $definition[
                    'productCode'
                ];

            $product =
                BakeryProduct::query()
                    ->where(
                        'product_code',
                        $code,
                    )
                    ->firstOrFail();

            $product->unsetRelation(
                'media'
            );

            $mainMedia =
                $product->getFirstMedia(
                    'catalog-main'
                );

            $this->assertNotNull(
                $mainMedia
            );

            $this->assertSame(
                $contract[
                    'old'
                ][$code][
                    'filename'
                ],
                $mainMedia->file_name,
            );

            $this->assertSame(
                $contract[
                    'old'
                ][$code][
                    'sha256'
                ],
                hash_file(
                    'sha256',
                    $mainMedia->getPath(),
                ),
            );
        }
    }
}
