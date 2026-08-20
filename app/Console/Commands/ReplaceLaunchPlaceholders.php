<?php

namespace App\Console\Commands;

use App\Models\BakeryMediaAsset;
use App\Models\BakeryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class ReplaceLaunchPlaceholders extends Command
{
    protected $signature = 'media:replace-launch-placeholders
        {stage : مسیر staging تأییدشده تصاویر واقعی}
        {--apply : Backup، حذف Placeholder و اجرای Importer}
        {--replacement-contract= : مسیر قرارداد Placeholder؛ پیش‌فرض قرارداد canonical پروژه}
        {--backup-dir= : مسیر مستقل و خارج از Production برای Backup Mainهای قبلی}';

    protected $description =
        'جایگزینی fail-closed و rollback-safe تصاویر Placeholder لانچ با تصاویر واقعی تأییدشده';

    public function handle(): int
    {
        $backups = [];

        try {
            $stage = $this->resolveStage(
                (string) $this->argument('stage')
            );

            $launch = $this->loadLaunchContract(
                $stage
            );

            $replacement =
                $this->loadReplacementContract();

            $targets = $this->preflight(
                $stage,
                $launch,
                $replacement,
            );

            $this->renderPlan(
                $targets
            );

            if (! $this->option('apply')) {
                $this->info(
                    'PLACEHOLDER_REPLACEMENT_DRY_RUN=PASS'
                );

                $this->warn(
                    'هیچ DB/Media write انجام نشد.'
                );

                return self::SUCCESS;
            }

            if (! Schema::hasTable(
                'bakery_media_assets'
            )) {
                throw new RuntimeException(
                    'BAKERY_MEDIA_ASSETS_TABLE_REQUIRED'
                );
            }

            $backupRoot =
                $this->resolveBackupRoot();

            /*
             * همه Placeholderها قبل از اولین حذف Backup
             * و checksum-verify می‌شوند.
             */
            foreach ($targets as $target) {
                $backups[] =
                    $this->backupPlaceholder(
                        $target,
                        $backupRoot,
                    );
            }

            if (
                count($backups)
                !== count($targets)
            ) {
                throw new RuntimeException(
                    'PLACEHOLDER_BACKUP_COUNT_MISMATCH'
                );
            }

            $this->info(
                'ALL_PLACEHOLDERS_BACKED_UP=PASS'
            );

            /*
             * فقط Mediaهایی حذف می‌شوند که preflight قبلاً
             * با ID + filename + MIME + size + SHA قفل کرده است.
             */
            foreach ($targets as $target) {
                /** @var Media $media */
                $media = $target['media'];

                $media->delete();

                /** @var BakeryProduct $product */
                $product = $target['product'];

                $product->unsetRelation(
                    'media'
                );

                if (
                    $product
                        ->getMedia(
                            'catalog-main'
                        )
                        ->count() !== 0
                ) {
                    throw new RuntimeException(
                        'PLACEHOLDER_DELETE_VERIFY_FAILED='
                        .$target['product_code']
                    );
                }

                $this->line(
                    'PLACEHOLDER_REMOVED='
                    .$target['product_code']
                );
            }

            /*
             * Importer اصلی عمداً دست‌نخورده می‌ماند.
             */
            /*
             * اجرای Command فرزند از Output stream همین
             * Orchestrator استفاده می‌کند تا نتیجه کامل
             * Parent + Child قابل مشاهده و تست باشد.
             */
            $importExit = $this->call(
                'media:import-launch',
                [
                    'stage' => $stage,
                    '--apply' => true,
                ],
            );

            if ($importExit !== self::SUCCESS) {
                throw new RuntimeException(
                    'LAUNCH_MEDIA_IMPORT_FAILED'
                );
            }

            $this->verifyImportedState(
                $launch,
                $targets,
            );

            $this->newLine();
            $this->info(
                'PLACEHOLDER_REPLACEMENT_APPLY=PASS'
            );

            $this->warn(
                'Backup Mainهای قبلی حفظ شده و media_verified عمداً false باقی مانده است.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $rollbackErrors = [];

            if ($backups !== []) {
                foreach (
                    array_reverse($backups) as $backup
                ) {
                    try {
                        $this->restorePlaceholder(
                            $backup
                        );
                    } catch (
                        Throwable $rollbackException
                    ) {
                        $rollbackErrors[] =
                            $rollbackException
                                ->getMessage();
                    }
                }
            }

            if ($rollbackErrors !== []) {
                $this->error(
                    'ROLLBACK_FAILED='
                    .implode(
                        ';',
                        $rollbackErrors,
                    )
                );
            } elseif ($backups !== []) {
                $this->warn(
                    'PLACEHOLDER_ROLLBACK=PASS'
                );
            }

            $this->error(
                'REPLACEMENT_FAILED='
                .$exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function resolveStage(
        string $requested,
    ): string {
        $stage = realpath(
            $requested
        );

        if (
            $stage === false
            || ! is_dir($stage)
        ) {
            throw new RuntimeException(
                'STAGE_NOT_FOUND'
            );
        }

        $stage = rtrim(
            str_replace(
                '\\',
                '/',
                $stage,
            ),
            '/',
        );

        if (
            $stage === '/var/www/winimi'
            || str_starts_with(
                $stage,
                '/var/www/winimi/',
            )
        ) {
            throw new RuntimeException(
                'PRODUCTION_STAGE_PATH_FORBIDDEN'
            );
        }

        foreach ([
            '/originals',
            '/manifest',
        ] as $required) {
            if (! is_dir(
                $stage.$required
            )) {
                throw new RuntimeException(
                    'STAGE_STRUCTURE_INVALID'
                );
            }
        }

        return $stage;
    }

    /**
     * @return array<string,mixed>
     *
     * @throws JsonException
     */
    private function loadLaunchContract(
        string $stage,
    ): array {
        $canonicalPath =
            base_path(
                'database/data/winimi-launch-media-manifest-v1.json'
            );

        $bundlePath =
            $stage
            .'/manifest/'
            .'winimi-launch-media-manifest-v1.json';

        $checksumPath =
            $stage
            .'/manifest/'
            .'SHA256SUMS.txt';

        foreach ([
            $canonicalPath,
            $bundlePath,
            $checksumPath,
        ] as $path) {
            if (! is_file($path)) {
                throw new RuntimeException(
                    'REQUIRED_LAUNCH_FILE_MISSING='
                    .$path
                );
            }
        }

        $canonical =
            $this->decodeJson(
                $canonicalPath
            );

        $bundle =
            $this->decodeJson(
                $bundlePath
            );

        if ($canonical !== $bundle) {
            throw new RuntimeException(
                'LAUNCH_MANIFEST_MISMATCH'
            );
        }

        if (
            ($canonical[
                'schemaVersion'
            ] ?? null) !== 1
        ) {
            throw new RuntimeException(
                'LAUNCH_MANIFEST_VERSION_INVALID'
            );
        }

        if (
            ($canonical[
                'launchPolicy'
            ][
                'giftBoxLaunchEnabled'
            ] ?? null) !== false
        ) {
            throw new RuntimeException(
                'GIFT_BOX_MUST_REMAIN_DISABLED'
            );
        }

        $products =
            $canonical['products']
            ?? null;

        if (
            ! is_array($products)
            || count($products) !== 5
        ) {
            throw new RuntimeException(
                'LAUNCH_PRODUCTS_MUST_EQUAL_5'
            );
        }

        $checksums =
            $this->loadChecksums(
                $checksumPath
            );

        $referenced = [];

        foreach ($products as $product) {
            $images =
                $product['images']
                ?? null;

            if (! is_array($images)) {
                throw new RuntimeException(
                    'LAUNCH_IMAGES_INVALID'
                );
            }

            $mainCount = 0;

            foreach ($images as $image) {
                $filename =
                    $this->safeFilename(
                        (string) (
                            $image[
                                'sourceFilename'
                            ]
                            ?? ''
                        )
                    );

                if (isset(
                    $referenced[$filename]
                )) {
                    throw new RuntimeException(
                        'DUPLICATE_MEDIA_REFERENCE='
                        .$filename
                    );
                }

                $referenced[$filename] =
                    true;

                $roles =
                    $image['roles']
                    ?? [];

                if (
                    is_array($roles)
                    && in_array(
                        BakeryMediaAsset::USAGE_PRODUCT_MAIN,
                        $roles,
                        true,
                    )
                ) {
                    $mainCount++;
                }

                $this->verifyPhysicalImage(
                    $stage,
                    $filename,
                    $checksums,
                );
            }

            if ($mainCount !== 1) {
                throw new RuntimeException(
                    'EXACTLY_ONE_MAIN_REQUIRED='
                    .($product[
                        'productCode'
                    ] ?? '')
                );
            }
        }

        foreach (
            $canonical[
                'brandAssets'
            ] ?? [] as $asset
        ) {
            $filename =
                $this->safeFilename(
                    (string) (
                        $asset[
                            'sourceFilename'
                        ]
                        ?? ''
                    )
                );

            if (isset(
                $referenced[$filename]
            )) {
                throw new RuntimeException(
                    'DUPLICATE_MEDIA_REFERENCE='
                    .$filename
                );
            }

            $referenced[$filename] =
                true;

            $this->verifyPhysicalImage(
                $stage,
                $filename,
                $checksums,
            );
        }

        if (
            count($referenced) !== 15
            || count($checksums) !== 15
        ) {
            throw new RuntimeException(
                'LAUNCH_MEDIA_COUNT_MUST_EQUAL_15'
            );
        }

        return $canonical;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadReplacementContract(): array
    {
        $requested = trim(
            (string) (
                $this->option(
                    'replacement-contract'
                )
                ?? ''
            )
        );

        $path = $requested !== ''
            ? $requested
            : base_path(
                'database/data/winimi-production-placeholder-replacement-v1.json'
            );

        if (! is_file($path)) {
            throw new RuntimeException(
                'REPLACEMENT_CONTRACT_NOT_FOUND'
            );
        }

        $contract =
            $this->decodeJson(
                $path
            );

        if (
            ($contract[
                'schemaVersion'
            ] ?? null) !== 1
            || ($contract[
                'environment'
            ] ?? null) !== 'production'
        ) {
            throw new RuntimeException(
                'REPLACEMENT_CONTRACT_HEADER_INVALID'
            );
        }

        $policy =
            $contract['policy']
            ?? null;

        if (! is_array($policy)) {
            throw new RuntimeException(
                'REPLACEMENT_POLICY_MISSING'
            );
        }

        foreach ([
            'explicitReplacementFlagRequired',
            'exactExistingMediaIdentityRequired',
            'existingOriginalMustBeBackedUpBeforeDeletion',
            'replacementFailureMustRestorePreviousMain',
        ] as $key) {
            if (
                ($policy[$key] ?? null)
                !== true
            ) {
                throw new RuntimeException(
                    'REPLACEMENT_POLICY_INVALID='
                    .$key
                );
            }
        }

        foreach ([
            'automaticUnknownMediaDeletionAllowed',
            'mediaVerifiedAutoEnableAllowed',
            'galleryDeletionAllowed',
            'giftBoxIncluded',
        ] as $key) {
            if (
                ($policy[$key] ?? null)
                !== false
            ) {
                throw new RuntimeException(
                    'REPLACEMENT_POLICY_INVALID='
                    .$key
                );
            }
        }

        if (
            ! is_array(
                $contract['products']
                ?? null
            )
            || count(
                $contract['products']
            ) !== 5
        ) {
            throw new RuntimeException(
                'REPLACEMENT_PRODUCTS_MUST_EQUAL_5'
            );
        }

        return $contract;
    }

    /**
     * @param  array<string,mixed>  $launch
     * @param  array<string,mixed>  $replacement
     * @return array<int,array<string,mixed>>
     */
    private function preflight(
        string $stage,
        array $launch,
        array $replacement,
    ): array {
        unset($stage);

        $launchByCode = [];

        foreach (
            $launch['products'] as $product
        ) {
            $code =
                (string) $product[
                    'productCode'
                ];

            $main = [];

            foreach (
                $product['images'] as $image
            ) {
                if (
                    in_array(
                        BakeryMediaAsset::USAGE_PRODUCT_MAIN,
                        $image['roles'],
                        true,
                    )
                ) {
                    $main[] = $image;
                }
            }

            if (count($main) !== 1) {
                throw new RuntimeException(
                    'LAUNCH_MAIN_COUNT_INVALID='
                    .$code
                );
            }

            $launchByCode[$code] = [
                'definition' => $product,
                'main' => $main[0],
            ];
        }

        $targets = [];

        foreach (
            $replacement['products'] as $spec
        ) {
            $code =
                (string) (
                    $spec['productCode']
                    ?? ''
                );

            $launchEntry =
                $launchByCode[$code]
                ?? null;

            if (! is_array(
                $launchEntry
            )) {
                throw new RuntimeException(
                    'REPLACEMENT_PRODUCT_NOT_IN_LAUNCH='
                    .$code
                );
            }

            $launchProduct =
                $launchEntry[
                    'definition'
                ];

            if (
                (string) (
                    $spec['publicId']
                    ?? ''
                )
                    !== (string) $launchProduct[
                        'publicId'
                    ]
                || (string) (
                    $spec['name']
                    ?? ''
                )
                    !== (string) $launchProduct[
                        'name'
                    ]
                || (string) (
                    $spec[
                        'replacementSource'
                    ]
                    ?? ''
                )
                    !== (string) $launchEntry[
                        'main'
                    ][
                        'sourceFilename'
                    ]
            ) {
                throw new RuntimeException(
                    'CROSS_CONTRACT_IDENTITY_MISMATCH='
                    .$code
                );
            }

            $product =
                BakeryProduct::query()
                    ->where(
                        'product_code',
                        $code,
                    )
                    ->first();

            if (! $product instanceof BakeryProduct) {
                throw new RuntimeException(
                    'PRODUCT_NOT_FOUND='
                    .$code
                );
            }

            if (
                (string) $product->public_id
                    !== (string) $spec[
                        'publicId'
                    ]
                || (string) $product->name
                    !== (string) $spec[
                        'name'
                    ]
            ) {
                throw new RuntimeException(
                    'PRODUCT_IDENTITY_MISMATCH='
                    .$code
                );
            }

            $product->unsetRelation(
                'media'
            );

            if (
                $product
                    ->getMedia(
                        'catalog-gallery'
                    )
                    ->count() !== 0
            ) {
                throw new RuntimeException(
                    'UNEXPECTED_EXISTING_GALLERY='
                    .$code
                );
            }

            $mains =
                $product->getMedia(
                    'catalog-main'
                );

            if ($mains->count() !== 1) {
                throw new RuntimeException(
                    'EXACT_EXISTING_MAIN_COUNT_REQUIRED='
                    .$code
                );
            }

            /** @var Media $media */
            $media =
                $mains->first();

            $expected =
                $spec['existingMain']
                ?? null;

            if (! is_array(
                $expected
            )) {
                throw new RuntimeException(
                    'EXISTING_MAIN_CONTRACT_MISSING='
                    .$code
                );
            }

            if (
                (int) $media->getKey()
                    !== (int) (
                        $expected[
                            'mediaId'
                        ]
                        ?? 0
                    )
                || (string) $media->file_name
                    !== (string) (
                        $expected[
                            'filename'
                        ]
                        ?? ''
                    )
                || (string) $media->mime_type
                    !== (string) (
                        $expected[
                            'mimeType'
                        ]
                        ?? ''
                    )
                || (int) $media->size
                    !== (int) (
                        $expected[
                            'sizeBytes'
                        ]
                        ?? -1
                    )
            ) {
                throw new RuntimeException(
                    'PLACEHOLDER_IDENTITY_MISMATCH='
                    .$code
                );
            }

            if (
                ($expected[
                    'customPropertiesMustBeEmpty'
                ] ?? false) === true
                && $media->custom_properties
                    !== []
            ) {
                throw new RuntimeException(
                    'PLACEHOLDER_METADATA_CHANGED='
                    .$code
                );
            }

            $mediaPath =
                $media->getPath();

            if (! is_file(
                $mediaPath
            )) {
                throw new RuntimeException(
                    'PLACEHOLDER_FILE_MISSING='
                    .$code
                );
            }

            $actualSha =
                hash_file(
                    'sha256',
                    $mediaPath,
                );

            $expectedSha =
                (string) (
                    $expected['sha256']
                    ?? ''
                );

            if (
                ! is_string($actualSha)
                || ! preg_match(
                    '/^[a-f0-9]{64}$/',
                    $expectedSha,
                )
                || ! hash_equals(
                    $expectedSha,
                    $actualSha,
                )
            ) {
                throw new RuntimeException(
                    'PLACEHOLDER_SHA256_MISMATCH='
                    .$code
                );
            }

            $targets[] = [
                'product_code' => $code,
                'product' => $product,
                'media' => $media,
                'expected' => $expected,
                'replacement_source' => $spec[
                        'replacementSource'
                    ],
            ];
        }

        if (count($targets) !== 5) {
            throw new RuntimeException(
                'PREFLIGHT_TARGET_COUNT_MUST_EQUAL_5'
            );
        }

        return $targets;
    }

    /**
     * @param  array<int,array<string,mixed>>  $targets
     */
    private function renderPlan(
        array $targets,
    ): void {
        $rows = [];

        foreach ($targets as $target) {
            /** @var Media $media */
            $media = $target['media'];

            $rows[] = [
                $target[
                    'product_code'
                ],
                $media->getKey(),
                $media->file_name,
                $target[
                    'replacement_source'
                ],
            ];
        }

        $this->table(
            [
                'Product',
                'Old Media ID',
                'Old File',
                'New Main',
            ],
            $rows,
        );

        $this->line(
            'REPLACEMENT_TARGETS='
            .count($targets)
        );
    }

    private function resolveBackupRoot(): string
    {
        $requested = trim(
            (string) (
                $this->option(
                    'backup-dir'
                )
                ?? ''
            )
        );

        if ($requested === '') {
            throw new RuntimeException(
                'BACKUP_DIR_REQUIRED_FOR_APPLY'
            );
        }

        $requested = rtrim(
            str_replace(
                '\\',
                '/',
                $requested,
            ),
            '/',
        );

        if (
            ! str_starts_with(
                $requested,
                '/'
            )
        ) {
            throw new RuntimeException(
                'BACKUP_DIR_MUST_BE_ABSOLUTE'
            );
        }

        if (
            $requested === '/var/www/winimi'
            || str_starts_with(
                $requested,
                '/var/www/winimi/',
            )
        ) {
            throw new RuntimeException(
                'BACKUP_IN_PRODUCTION_TREE_FORBIDDEN'
            );
        }

        if (
            is_dir($requested)
            && iterator_count(
                new \FilesystemIterator(
                    $requested,
                    \FilesystemIterator::SKIP_DOTS,
                )
            ) > 0
        ) {
            throw new RuntimeException(
                'BACKUP_DIR_MUST_BE_EMPTY'
            );
        }

        if (
            ! is_dir($requested)
            && ! mkdir(
                $requested,
                0700,
                true,
            )
            && ! is_dir($requested)
        ) {
            throw new RuntimeException(
                'BACKUP_DIR_CREATE_FAILED'
            );
        }

        chmod(
            $requested,
            0700,
        );

        $resolved =
            realpath($requested);

        if ($resolved === false) {
            throw new RuntimeException(
                'BACKUP_DIR_RESOLVE_FAILED'
            );
        }

        return $resolved;
    }

    /**
     * @param  array<string,mixed>  $target
     * @return array<string,mixed>
     */
    private function backupPlaceholder(
        array $target,
        string $backupRoot,
    ): array {
        /** @var BakeryProduct $product */
        $product = $target['product'];

        /** @var Media $media */
        $media = $target['media'];

        $code =
            $target['product_code'];

        $source =
            $media->getPath();

        if (! is_file($source)) {
            throw new RuntimeException(
                'BACKUP_SOURCE_MISSING='
                .$code
            );
        }

        $dir =
            $backupRoot
            .DIRECTORY_SEPARATOR
            .$code;

        if (
            ! mkdir(
                $dir,
                0700,
                true,
            )
            && ! is_dir($dir)
        ) {
            throw new RuntimeException(
                'BACKUP_PRODUCT_DIR_FAILED='
                .$code
            );
        }

        chmod(
            $dir,
            0700,
        );

        $backupFile =
            $dir
            .DIRECTORY_SEPARATOR
            .$media->file_name;

        if (file_exists(
            $backupFile
        )) {
            throw new RuntimeException(
                'BACKUP_FILE_ALREADY_EXISTS='
                .$code
            );
        }

        if (! copy(
            $source,
            $backupFile,
        )) {
            throw new RuntimeException(
                'BACKUP_COPY_FAILED='
                .$code
            );
        }

        chmod(
            $backupFile,
            0600,
        );

        $sourceSha =
            hash_file(
                'sha256',
                $source,
            );

        $backupSha =
            hash_file(
                'sha256',
                $backupFile,
            );

        if (
            ! is_string($sourceSha)
            || ! is_string($backupSha)
            || ! hash_equals(
                $sourceSha,
                $backupSha,
            )
        ) {
            throw new RuntimeException(
                'BACKUP_SHA256_FAILED='
                .$code
            );
        }

        $metadata = [
            'product' => $product,
            'product_code' => $code,
            'backup_file' => $backupFile,
            'sha256' => $backupSha,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'custom_properties' => $media->custom_properties,
            'manipulations' => $media->manipulations,
            'responsive_images' => $media->responsive_images,
            'order_column' => $media->order_column,
        ];

        $metadataPath =
            $backupFile
            .'.metadata.json';

        $encoded =
            json_encode(
                [
                    'productCode' => $code,
                    'mediaId' => $media->getKey(),
                    'filename' => $media->file_name,
                    'sha256' => $backupSha,
                    'createdAt' => now()
                        ->toIso8601String(),
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
                | JSON_THROW_ON_ERROR,
            );

        if (
            file_put_contents(
                $metadataPath,
                $encoded.PHP_EOL,
            ) === false
        ) {
            throw new RuntimeException(
                'BACKUP_METADATA_WRITE_FAILED='
                .$code
            );
        }

        chmod(
            $metadataPath,
            0600,
        );

        $this->line(
            'BACKUP='
            .$code
            .'|'
            .$backupFile
        );

        return $metadata;
    }

    /**
     * @param  array<string,mixed>  $backup
     */
    private function restorePlaceholder(
        array $backup,
    ): void {
        /** @var BakeryProduct|null $product */
        $product =
            $backup['product']
            ?? null;

        if (! $product instanceof BakeryProduct) {
            throw new RuntimeException(
                'ROLLBACK_PRODUCT_MISSING'
            );
        }

        $code =
            (string) (
                $backup['product_code']
                ?? 'unknown'
            );

        $backupFile =
            (string) (
                $backup['backup_file']
                ?? ''
            );

        if (! is_file(
            $backupFile
        )) {
            throw new RuntimeException(
                'ROLLBACK_BACKUP_MISSING='
                .$code
            );
        }

        $backupSha =
            hash_file(
                'sha256',
                $backupFile,
            );

        if (
            ! is_string($backupSha)
            || ! hash_equals(
                (string) $backup['sha256'],
                $backupSha,
            )
        ) {
            throw new RuntimeException(
                'ROLLBACK_BACKUP_SHA_MISMATCH='
                .$code
            );
        }

        $product->unsetRelation(
            'media'
        );

        $current =
            $product->getFirstMedia(
                'catalog-main'
            );

        if ($current instanceof Media) {
            $currentPath =
                $current->getPath();

            $currentSha =
                is_file($currentPath)
                    ? hash_file(
                        'sha256',
                        $currentPath,
                    )
                    : null;

            if (
                is_string($currentSha)
                && hash_equals(
                    $backupSha,
                    $currentSha,
                )
                && $current->file_name
                    === $backup[
                        'file_name'
                    ]
            ) {
                return;
            }

            /*
             * فقط Main ساخته‌شده توسط Launch Importer
             * اجازه حذف در rollback دارد.
             */
            $sourceAssetId =
                $current->getCustomProperty(
                    'source_asset_id'
                );

            if (
                ! is_numeric(
                    $sourceAssetId
                )
            ) {
                throw new RuntimeException(
                    'ROLLBACK_UNKNOWN_MAIN_BLOCKED='
                    .$code
                );
            }

            $sourceAsset =
                BakeryMediaAsset::query()
                    ->find(
                        (int) $sourceAssetId
                    );

            if (
                ! $sourceAsset
                    instanceof BakeryMediaAsset
                || (int) $sourceAsset
                    ->manifest_version
                    !== 1
                || (int) $sourceAsset
                    ->product_id
                    !== (int) $product
                        ->getKey()
            ) {
                throw new RuntimeException(
                    'ROLLBACK_UNKNOWN_IMPORTED_MAIN_BLOCKED='
                    .$code
                );
            }

            $current->delete();

            $product->unsetRelation(
                'media'
            );
        }

        if (
            $product
                ->getMedia(
                    'catalog-main'
                )
                ->count() !== 0
        ) {
            throw new RuntimeException(
                'ROLLBACK_MAIN_NOT_EMPTY='
                .$code
            );
        }

        $restored =
            $product
                ->addMedia(
                    $backupFile
                )
                ->preservingOriginal()
                ->usingName(
                    (string) $backup[
                        'name'
                    ]
                )
                ->usingFileName(
                    (string) $backup[
                        'file_name'
                    ]
                )
                ->toMediaCollection(
                    'catalog-main'
                );

        $restored
            ->forceFill([
                'custom_properties' => $backup[
                        'custom_properties'
                    ],
                'manipulations' => $backup[
                        'manipulations'
                    ],
                'responsive_images' => $backup[
                        'responsive_images'
                    ],
                'order_column' => $backup[
                        'order_column'
                    ],
            ])
            ->save();

        $restoredSha =
            hash_file(
                'sha256',
                $restored->getPath(),
            );

        if (
            ! is_string($restoredSha)
            || ! hash_equals(
                $backupSha,
                $restoredSha,
            )
        ) {
            throw new RuntimeException(
                'ROLLBACK_RESTORE_SHA_FAILED='
                .$code
            );
        }

        $this->line(
            'RESTORED='
            .$code
        );
    }

    /**
     * @param  array<string,mixed>  $launch
     * @param  array<int,array<string,mixed>>  $targets
     */
    private function verifyImportedState(
        array $launch,
        array $targets,
    ): void {
        $expectedFiles = [];

        foreach (
            $launch['products'] as $product
        ) {
            foreach (
                $product['images'] as $image
            ) {
                $expectedFiles[] =
                    $image[
                        'sourceFilename'
                    ];
            }
        }

        foreach (
            $launch[
                'brandAssets'
            ] ?? [] as $asset
        ) {
            $expectedFiles[] =
                $asset[
                    'sourceFilename'
                ];
        }

        $assets =
            BakeryMediaAsset::query()
                ->where(
                    'manifest_version',
                    1,
                )
                ->whereIn(
                    'source_filename',
                    $expectedFiles,
                )
                ->get();

        if ($assets->count() !== 15) {
            throw new RuntimeException(
                'POST_IMPORT_ASSET_COUNT_MISMATCH='
                .$assets->count()
            );
        }

        foreach ($targets as $target) {
            /** @var BakeryProduct $product */
            $product =
                $target['product'];

            $product->refresh();
            $product->unsetRelation(
                'media'
            );

            $main =
                $product->getFirstMedia(
                    'catalog-main'
                );

            if (! $main instanceof Media) {
                throw new RuntimeException(
                    'POST_IMPORT_MAIN_MISSING='
                    .$target[
                        'product_code'
                    ]
                );
            }

            if (
                $main->file_name
                    !== $target[
                        'replacement_source'
                    ]
                || ! is_numeric(
                    $main->getCustomProperty(
                        'source_asset_id'
                    )
                )
            ) {
                throw new RuntimeException(
                    'POST_IMPORT_MAIN_IDENTITY_FAILED='
                    .$target[
                        'product_code'
                    ]
                );
            }

            if ($product->media_verified) {
                throw new RuntimeException(
                    'MEDIA_VERIFIED_MUST_REMAIN_FALSE='
                    .$target[
                        'product_code'
                    ]
                );
            }
        }

        $this->info(
            'POST_IMPORT_VERIFICATION=PASS'
        );
    }

    /**
     * @return array<string,mixed>
     *
     * @throws JsonException
     */
    private function decodeJson(
        string $path,
    ): array {
        $contents =
            file_get_contents(
                $path
            );

        if ($contents === false) {
            throw new RuntimeException(
                'JSON_READ_FAILED='
                .$path
            );
        }

        $decoded =
            json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

        if (! is_array(
            $decoded
        )) {
            throw new RuntimeException(
                'JSON_ROOT_INVALID='
                .$path
            );
        }

        return $decoded;
    }

    /**
     * @return array<string,string>
     */
    private function loadChecksums(
        string $path,
    ): array {
        $lines =
            file(
                $path,
                FILE_IGNORE_NEW_LINES
                | FILE_SKIP_EMPTY_LINES,
            );

        if ($lines === false) {
            throw new RuntimeException(
                'CHECKSUM_FILE_READ_FAILED'
            );
        }

        $result = [];

        foreach ($lines as $line) {
            if (
                preg_match(
                    '/^([a-f0-9]{64})\s{2}originals\/(.+)$/',
                    trim($line),
                    $matches,
                ) !== 1
            ) {
                throw new RuntimeException(
                    'CHECKSUM_LINE_INVALID'
                );
            }

            $filename =
                $this->safeFilename(
                    $matches[2]
                );

            if (isset(
                $result[$filename]
            )) {
                throw new RuntimeException(
                    'DUPLICATE_CHECKSUM='
                    .$filename
                );
            }

            $result[$filename] =
                $matches[1];
        }

        return $result;
    }

    private function safeFilename(
        string $filename,
    ): string {
        $filename = trim(
            $filename
        );

        if (
            $filename === ''
            || basename($filename)
                !== $filename
            || str_contains(
                $filename,
                '/'
            )
            || str_contains(
                $filename,
                '\\'
            )
        ) {
            throw new RuntimeException(
                'UNSAFE_FILENAME='
                .$filename
            );
        }

        return $filename;
    }

    /**
     * @param  array<string,string>  $checksums
     */
    private function verifyPhysicalImage(
        string $stage,
        string $filename,
        array $checksums,
    ): void {
        $originals =
            realpath(
                $stage.'/originals'
            );

        $file =
            realpath(
                $stage
                .'/originals/'
                .$filename
            );

        if (
            $originals === false
            || $file === false
            || ! is_file($file)
            || dirname($file)
                !== $originals
        ) {
            throw new RuntimeException(
                'REAL_MEDIA_MISSING='
                .$filename
            );
        }

        $expected =
            $checksums[$filename]
            ?? null;

        $actual =
            hash_file(
                'sha256',
                $file,
            );

        if (
            ! is_string($expected)
            || ! is_string($actual)
            || ! hash_equals(
                $expected,
                $actual,
            )
        ) {
            throw new RuntimeException(
                'REAL_MEDIA_SHA_MISMATCH='
                .$filename
            );
        }

        $size = filesize(
            $file
        );

        if (
            $size === false
            || $size < 1
            || $size > 12 * 1024 * 1024
        ) {
            throw new RuntimeException(
                'REAL_MEDIA_SIZE_INVALID='
                .$filename
            );
        }

        $mime =
            (new \finfo(
                FILEINFO_MIME_TYPE
            ))->file(
                $file
            );

        if (! in_array(
            $mime,
            [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            true,
        )) {
            throw new RuntimeException(
                'REAL_MEDIA_MIME_INVALID='
                .$filename
            );
        }

        $dimensions =
            @getimagesize(
                $file
            );

        if (! is_array(
            $dimensions
        )) {
            throw new RuntimeException(
                'REAL_MEDIA_DECODE_FAILED='
                .$filename
            );
        }

        if (
            (int) $dimensions[0] > 6000
            || (int) $dimensions[1] > 6000
            || (int) $dimensions[0] < 1
            || (int) $dimensions[1] < 1
        ) {
            throw new RuntimeException(
                'REAL_MEDIA_DIMENSIONS_INVALID='
                .$filename
            );
        }
    }
}
