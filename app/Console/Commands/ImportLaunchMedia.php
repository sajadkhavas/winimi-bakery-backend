<?php

namespace App\Console\Commands;

use App\Models\BakeryMediaAsset;
use App\Models\BakeryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class ImportLaunchMedia extends Command
{
    protected $signature = 'media:import-launch
        {stage : مسیر staging شامل originals و manifest}
        {--apply : پس از عبور کامل از preflight، واردات را اعمال کن}';

    protected $description =
        'واردات fail-closed و idempotent رسانه واقعی Launch از Manifest تأییدشده';

    public function handle(): int
    {
        try {
            $stage = $this->resolveStage(
                (string) $this->argument('stage')
            );

            $contract = $this->loadContract(
                $stage
            );

            $bindings = $this->preflight(
                $contract['bindings']
            );

            $this->renderPlan(
                $bindings,
                $contract['version'],
            );

            if (! $this->option('apply')) {
                $this->warn(
                    'DRY_RUN=PASS — هیچ DB/Media write انجام نشد.'
                );

                $this->info(
                    'برای اعمال همین Plan از --apply استفاده شود.'
                );

                return self::SUCCESS;
            }

            $this->applyBindings(
                $bindings,
                $contract['version'],
            );

            $this->newLine();
            $this->info('MEDIA_IMPORT_APPLY=PASS');
            $this->warn(
                'media_verified عمداً فعال نشده است؛ تأیید انسانی بعد از QA لازم است.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(
                'IMPORT_FAILED='.$exception->getMessage()
            );

            $this->warn(
                'عملیات fail-closed متوقف شد.'
            );

            return self::FAILURE;
        }
    }

    private function resolveStage(
        string $requested,
    ): string {
        $stage = realpath($requested);

        if (
            $stage === false
            || ! is_dir($stage)
        ) {
            throw new RuntimeException(
                'STAGE_NOT_FOUND'
            );
        }

        $normalized = rtrim(
            str_replace('\\', '/', $stage),
            '/'
        );

        if (
            $normalized === '/var/www/winimi'
            || str_starts_with(
                $normalized,
                '/var/www/winimi/',
            )
        ) {
            throw new RuntimeException(
                'PRODUCTION_STAGE_PATH_FORBIDDEN'
            );
        }

        foreach ([
            'originals',
            'manifest',
        ] as $required) {
            if (! is_dir(
                $stage.DIRECTORY_SEPARATOR.$required
            )) {
                throw new RuntimeException(
                    'STAGE_STRUCTURE_INVALID='.$required
                );
            }
        }

        return $stage;
    }

    /**
     * @return array{
     *     version:int,
     *     bindings:array<int,array<string,mixed>>
     * }
     *
     * @throws JsonException
     */
    private function loadContract(
        string $stage,
    ): array {
        $sourceManifestPath = base_path(
            'database/data/winimi-launch-media-manifest-v1.json'
        );

        $bundleManifestPath =
            $stage
            .'/manifest/winimi-launch-media-manifest-v1.json';

        $checksumPath =
            $stage
            .'/manifest/SHA256SUMS.txt';

        foreach ([
            $sourceManifestPath,
            $bundleManifestPath,
            $checksumPath,
        ] as $path) {
            if (! is_file($path)) {
                throw new RuntimeException(
                    'REQUIRED_FILE_MISSING='.$path
                );
            }
        }

        $sourceManifest = $this->decodeJsonFile(
            $sourceManifestPath
        );

        $bundleManifest = $this->decodeJsonFile(
            $bundleManifestPath
        );

        if ($sourceManifest !== $bundleManifest) {
            throw new RuntimeException(
                'MANIFEST_SEMANTIC_MISMATCH'
            );
        }

        $version = (int) (
            $sourceManifest['schemaVersion']
            ?? 0
        );

        if ($version !== 1) {
            throw new RuntimeException(
                'UNSUPPORTED_MANIFEST_VERSION='.$version
            );
        }

        if (
            ($sourceManifest[
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
            $sourceManifest['products']
            ?? null;

        if (
            ! is_array($products)
            || count($products) !== 5
        ) {
            throw new RuntimeException(
                'LAUNCH_PRODUCT_COUNT_MUST_BE_5'
            );
        }

        $checksums = $this->loadChecksums(
            $checksumPath
        );

        $bindings = [];
        $referenced = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                throw new RuntimeException(
                    'INVALID_PRODUCT_MANIFEST_ENTRY'
                );
            }

            if (
                ($product['launchEnabled'] ?? null)
                !== true
            ) {
                throw new RuntimeException(
                    'LAUNCH_PRODUCT_NOT_ENABLED'
                );
            }

            $productCode = trim(
                (string) (
                    $product['productCode']
                    ?? ''
                )
            );

            $publicId = trim(
                (string) (
                    $product['publicId']
                    ?? ''
                )
            );

            $productName = trim(
                (string) (
                    $product['name']
                    ?? ''
                )
            );

            if (
                $productCode === ''
                || $publicId === ''
                || $productName === ''
            ) {
                throw new RuntimeException(
                    'PRODUCT_IDENTITY_INCOMPLETE'
                );
            }

            $images = $product['images'] ?? null;

            if (! is_array($images)) {
                throw new RuntimeException(
                    'PRODUCT_IMAGES_INVALID='.$productCode
                );
            }

            $mainCount = 0;

            foreach ($images as $image) {
                if (! is_array($image)) {
                    throw new RuntimeException(
                        'IMAGE_ENTRY_INVALID='.$productCode
                    );
                }

                $roles = $image['roles'] ?? [];

                if (! is_array($roles)) {
                    throw new RuntimeException(
                        'IMAGE_ROLES_INVALID='.$productCode
                    );
                }

                $isMain = in_array(
                    BakeryMediaAsset::USAGE_PRODUCT_MAIN,
                    $roles,
                    true,
                );

                $isGallery = in_array(
                    BakeryMediaAsset::USAGE_PRODUCT_GALLERY,
                    $roles,
                    true,
                );

                if ($isMain === $isGallery) {
                    throw new RuntimeException(
                        'PRODUCT_IMAGE_MUST_HAVE_ONE_ASSIGNMENT_ROLE='
                        .$productCode
                    );
                }

                if ($isMain) {
                    $mainCount++;
                }

                $filename = $this->validateFilename(
                    (string) (
                        $image['sourceFilename']
                        ?? ''
                    )
                );

                if (isset($referenced[$filename])) {
                    throw new RuntimeException(
                        'DUPLICATE_MEDIA_REFERENCE='.$filename
                    );
                }

                $referenced[$filename] = true;

                $sha = $this->validatePhysicalFile(
                    $stage,
                    $filename,
                    $checksums,
                );

                $usage = $isMain
                    ? BakeryMediaAsset::USAGE_PRODUCT_MAIN
                    : BakeryMediaAsset::USAGE_PRODUCT_GALLERY;

                $bindings[] = $this->makeBinding(
                    version: $version,
                    filename: $filename,
                    sha: $sha,
                    roles: $roles,
                    usage: $usage,
                    alt: (string) (
                        $image['alt']
                        ?? $productName
                    ),
                    productCode: $productCode,
                    publicId: $publicId,
                    productName: $productName,
                    stage: $stage,
                    truthNote: isset(
                        $image['truthNote']
                    )
                        ? (string) $image['truthNote']
                        : null,
                );
            }

            if ($mainCount !== 1) {
                throw new RuntimeException(
                    'EXACTLY_ONE_MAIN_REQUIRED='.$productCode
                );
            }
        }

        $brandAssets =
            $sourceManifest['brandAssets']
            ?? [];

        if (! is_array($brandAssets)) {
            throw new RuntimeException(
                'BRAND_ASSETS_INVALID'
            );
        }

        foreach ($brandAssets as $asset) {
            if (! is_array($asset)) {
                throw new RuntimeException(
                    'BRAND_ASSET_INVALID'
                );
            }

            $filename = $this->validateFilename(
                (string) (
                    $asset['sourceFilename']
                    ?? ''
                )
            );

            if (isset($referenced[$filename])) {
                throw new RuntimeException(
                    'DUPLICATE_MEDIA_REFERENCE='.$filename
                );
            }

            $referenced[$filename] = true;

            $roles = $asset['roles'] ?? [];

            if (
                ! is_array($roles)
                || ! in_array(
                    BakeryMediaAsset::USAGE_BRAND,
                    $roles,
                    true,
                )
            ) {
                throw new RuntimeException(
                    'BRAND_USAGE_REQUIRED='.$filename
                );
            }

            $sha = $this->validatePhysicalFile(
                $stage,
                $filename,
                $checksums,
            );

            $bindings[] = $this->makeBinding(
                version: $version,
                filename: $filename,
                sha: $sha,
                roles: $roles,
                usage: BakeryMediaAsset::USAGE_BRAND,
                alt: (string) (
                    $asset['alt']
                    ?? 'وینیمی بیکری'
                ),
                productCode: null,
                publicId: null,
                productName: null,
                stage: $stage,
                truthNote: null,
            );
        }

        if (
            count($bindings) !== 15
            || count($referenced) !== 15
        ) {
            throw new RuntimeException(
                'MANIFEST_MEDIA_COUNT_MUST_BE_15'
            );
        }

        if (count($checksums) !== 15) {
            throw new RuntimeException(
                'CHECKSUM_MEDIA_COUNT_MUST_BE_15'
            );
        }

        return [
            'version' => $version,
            'bindings' => $bindings,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonFile(
        string $path,
    ): array {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(
                'FILE_READ_FAILED='.$path
            );
        }

        $decoded = json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'JSON_ROOT_MUST_BE_OBJECT='.$path
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
        $lines = file(
            $path,
            FILE_IGNORE_NEW_LINES
            | FILE_SKIP_EMPTY_LINES,
        );

        if ($lines === false) {
            throw new RuntimeException(
                'CHECKSUM_FILE_READ_FAILED'
            );
        }

        $checksums = [];

        foreach ($lines as $line) {
            if (
                preg_match(
                    '/^([a-f0-9]{64})\s{2}originals\/(.+)$/',
                    trim($line),
                    $matches,
                ) !== 1
            ) {
                throw new RuntimeException(
                    'INVALID_CHECKSUM_LINE'
                );
            }

            $filename = $this->validateFilename(
                $matches[2]
            );

            if (isset($checksums[$filename])) {
                throw new RuntimeException(
                    'DUPLICATE_CHECKSUM_ENTRY='.$filename
                );
            }

            $checksums[$filename] = $matches[1];
        }

        return $checksums;
    }

    private function validateFilename(
        string $filename,
    ): string {
        $filename = trim($filename);

        if (
            $filename === ''
            || basename($filename) !== $filename
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || $filename === '.'
            || $filename === '..'
        ) {
            throw new RuntimeException(
                'UNSAFE_FILENAME='.$filename
            );
        }

        return $filename;
    }

    /**
     * @param  array<string,string>  $checksums
     */
    private function validatePhysicalFile(
        string $stage,
        string $filename,
        array $checksums,
    ): string {
        $originals = realpath(
            $stage.'/originals'
        );

        $file = realpath(
            $stage.'/originals/'.$filename
        );

        if (
            $originals === false
            || $file === false
            || ! is_file($file)
            || dirname($file) !== $originals
        ) {
            throw new RuntimeException(
                'MEDIA_FILE_NOT_FOUND='.$filename
            );
        }

        $expected = $checksums[$filename]
            ?? null;

        if (! is_string($expected)) {
            throw new RuntimeException(
                'CHECKSUM_MISSING='.$filename
            );
        }

        $actual = hash_file(
            'sha256',
            $file
        );

        if (
            ! is_string($actual)
            || ! hash_equals(
                $expected,
                $actual,
            )
        ) {
            throw new RuntimeException(
                'CHECKSUM_MISMATCH='.$filename
            );
        }

        $size = filesize($file);

        if (
            $size === false
            || $size < 1
            || $size > 12 * 1024 * 1024
        ) {
            throw new RuntimeException(
                'INVALID_MEDIA_SIZE='.$filename
            );
        }

        $finfo = new \finfo(
            FILEINFO_MIME_TYPE
        );

        $mime = $finfo->file($file);

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
                'UNSUPPORTED_MEDIA_MIME='
                .$filename
                .'|'
                .$mime
            );
        }

        $dimensions = @getimagesize($file);

        if (! is_array($dimensions)) {
            throw new RuntimeException(
                'IMAGE_DECODE_FAILED='.$filename
            );
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];

        if (
            $width < 1
            || $height < 1
            || $width > 6000
            || $height > 6000
        ) {
            throw new RuntimeException(
                'INVALID_MEDIA_DIMENSIONS='.$filename
            );
        }

        return $actual;
    }

    /**
     * @param  array<int,string>  $roles
     * @return array<string,mixed>
     */
    private function makeBinding(
        int $version,
        string $filename,
        string $sha,
        array $roles,
        string $usage,
        string $alt,
        ?string $productCode,
        ?string $publicId,
        ?string $productName,
        string $stage,
        ?string $truthNote,
    ): array {
        $identity = implode(
            '|',
            [
                (string) $version,
                $productCode ?? 'brand',
                $usage,
                $filename,
                $sha,
            ],
        );

        return [
            'import_key' => 'launch-v'
                .$version
                .':'
                .hash('sha256', $identity),

            'source_filename' => $filename,

            'source_sha256' => $sha,

            'manifest_version' => $version,

            'roles' => array_values($roles),

            'usage' => $usage,

            'alt' => trim($alt) !== ''
                    ? trim($alt)
                    : ($productName ?? 'وینیمی بیکری'),

            'product_code' => $productCode,

            'public_id' => $publicId,

            'product_name' => $productName,

            'file' => $stage.'/originals/'.$filename,

            'truth_note' => $truthNote,

            'product' => null,

            'existing' => null,

            'skip' => false,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $bindings
     * @return array<int,array<string,mixed>>
     */
    private function preflight(
        array $bindings,
    ): array {
        foreach ($bindings as $index => $binding) {
            $product = null;

            if (
                is_string(
                    $binding['product_code']
                )
            ) {
                $product =
                    BakeryProduct::query()
                        ->where(
                            'product_code',
                            $binding[
                                'product_code'
                            ],
                        )
                        ->first();

                if (! $product instanceof BakeryProduct) {
                    throw new RuntimeException(
                        'PRODUCT_NOT_FOUND='
                        .$binding['product_code']
                    );
                }

                if (
                    (string) $product->public_id
                        !== $binding['public_id']
                    || (string) $product->name
                        !== $binding['product_name']
                ) {
                    throw new RuntimeException(
                        'PRODUCT_IDENTITY_MISMATCH='
                        .$binding['product_code']
                    );
                }
            }

            $existing =
                BakeryMediaAsset::query()
                    ->where(
                        'import_key',
                        $binding['import_key'],
                    )
                    ->first();

            if (
                $existing
                instanceof BakeryMediaAsset
            ) {
                $this->validateExistingImport(
                    $existing,
                    $binding,
                    $product,
                );

                $bindings[$index]['product'] =
                    $product;

                $bindings[$index]['existing'] =
                    $existing;

                $bindings[$index]['skip'] =
                    true;

                continue;
            }

            if (
                $product instanceof BakeryProduct
                && $binding['usage']
                    === BakeryMediaAsset::USAGE_PRODUCT_MAIN
            ) {
                $product->unsetRelation('media');

                if (
                    $product->getFirstMedia(
                        'catalog-main'
                    ) instanceof Media
                ) {
                    throw new RuntimeException(
                        'MAIN_CONFLICT='
                        .$binding['product_code']
                        .' — تصویر اصلی موجود است و جایگزینی خودکار ممنوع است.'
                    );
                }
            }

            $bindings[$index]['product'] =
                $product;
        }

        return $bindings;
    }

    /**
     * @param  array<string,mixed>  $binding
     */
    private function validateExistingImport(
        BakeryMediaAsset $existing,
        array $binding,
        ?BakeryProduct $product,
    ): void {
        if (
            (string) $existing->source_filename
                !== $binding['source_filename']
            || (string) $existing->source_sha256
                !== $binding['source_sha256']
            || (int) $existing->manifest_version
                !== $binding['manifest_version']
        ) {
            throw new RuntimeException(
                'IMPORT_KEY_COLLISION='
                .$binding['source_filename']
            );
        }

        if (
            ! $existing->sourceMedia()
            instanceof Media
        ) {
            throw new RuntimeException(
                'EXISTING_IMPORT_SOURCE_MISSING='
                .$binding['source_filename']
            );
        }

        if (
            $binding['usage']
            === BakeryMediaAsset::USAGE_BRAND
        ) {
            if (
                $existing->product_id !== null
                || $existing->usage
                    !== BakeryMediaAsset::USAGE_BRAND
                || $existing->status
                    !== BakeryMediaAsset::STATUS_READY
            ) {
                throw new RuntimeException(
                    'EXISTING_BRAND_IMPORT_INCONSISTENT='
                    .$binding['source_filename']
                );
            }

            return;
        }

        if (! $product instanceof BakeryProduct) {
            throw new RuntimeException(
                'EXISTING_PRODUCT_IMPORT_WITHOUT_PRODUCT'
            );
        }

        if (
            (int) $existing->product_id
                !== (int) $product->getKey()
            || $existing->usage
                !== $binding['usage']
            || $existing->status
                !== BakeryMediaAsset::STATUS_ASSIGNED
        ) {
            throw new RuntimeException(
                'EXISTING_PRODUCT_IMPORT_INCONSISTENT='
                .$binding['source_filename']
            );
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $bindings
     */
    private function renderPlan(
        array $bindings,
        int $version,
    ): void {
        $this->newLine();

        $this->info(
            $this->option('apply')
                ? 'MODE=APPLY'
                : 'MODE=DRY_RUN'
        );

        $this->line(
            'MANIFEST_VERSION='.$version
        );

        $rows = [];

        foreach ($bindings as $binding) {
            $rows[] = [
                $binding['source_filename'],
                $binding['product_code']
                    ?? 'BRAND',
                $binding['usage'],
                $binding['skip']
                    ? 'already-imported'
                    : 'new',
            ];
        }

        $this->table(
            [
                'File',
                'Target',
                'Usage',
                'Plan',
            ],
            $rows,
        );

        $this->line(
            'PLANNED_MEDIA='.count($bindings)
        );

        $this->line(
            'NEW_MEDIA='
            .count(
                array_filter(
                    $bindings,
                    static fn (
                        array $binding
                    ): bool => ! $binding['skip'],
                )
            )
        );
    }

    /**
     * @param  array<int,array<string,mixed>>  $bindings
     */
    private function applyBindings(
        array $bindings,
        int $version,
    ): void {
        $created = [];

        try {
            foreach ($bindings as $binding) {
                if ($binding['skip']) {
                    $this->line(
                        'SKIP_ALREADY_IMPORTED='
                        .$binding['source_filename']
                    );

                    continue;
                }

                $product =
                    $binding['product'];

                $title = Str::limit(
                    $binding['alt'],
                    220,
                    '',
                );

                $notes = json_encode(
                    [
                        'source' => 'winimi-launch-media-manifest-v1',

                        'manifestVersion' => $version,

                        'roles' => $binding['roles'],

                        'truthNote' => $binding['truth_note'],
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR,
                );

                $asset =
                    BakeryMediaAsset::query()
                        ->create([
                            'product_id' => null,

                            'title' => $title,

                            'import_key' => $binding['import_key'],

                            'source_filename' => $binding[
                                    'source_filename'
                                ],

                            'source_sha256' => $binding[
                                    'source_sha256'
                                ],

                            'manifest_version' => $version,

                            'alt_text' => $binding['alt'],

                            'usage' => $binding['usage']
                                === BakeryMediaAsset::USAGE_BRAND
                                    ? BakeryMediaAsset::USAGE_BRAND
                                    : BakeryMediaAsset::USAGE_UNASSIGNED,

                            'status' => BakeryMediaAsset::STATUS_READY,

                            'notes' => $notes,
                        ]);

                $created[] = [
                    'asset' => $asset,
                    'product_media' => null,
                ];

                $source =
                    $asset
                        ->addMedia(
                            $binding['file']
                        )
                        ->preservingOriginal()
                        ->usingName(
                            $title
                        )
                        ->usingFileName(
                            $binding[
                                'source_filename'
                            ]
                        )
                        ->withCustomProperties([
                            'import_key' => $binding['import_key'],

                            'source_filename' => $binding[
                                    'source_filename'
                                ],

                            'source_sha256' => $binding[
                                    'source_sha256'
                                ],

                            'manifest_version' => $version,

                            'roles' => $binding['roles'],
                        ])
                        ->toMediaCollection(
                            'source'
                        );

                if (! $source instanceof Media) {
                    throw new RuntimeException(
                        'SOURCE_MEDIA_CREATE_FAILED='
                        .$binding['source_filename']
                    );
                }

                if (
                    $product
                    instanceof BakeryProduct
                ) {
                    $copied =
                        $asset->assignToProduct(
                            $product,
                            $binding['usage'],
                            $binding['alt'],
                        );

                    $created[
                        array_key_last($created)
                    ][
                        'product_media'
                    ] = $copied;
                }

                $this->line(
                    'IMPORTED='
                    .$binding['source_filename']
                );
            }
        } catch (Throwable $exception) {
            foreach (
                array_reverse($created) as $createdItem
            ) {
                try {
                    $productMedia =
                        $createdItem[
                            'product_media'
                        ];

                    if (
                        $productMedia
                        instanceof Media
                        && $productMedia->exists
                    ) {
                        $productMedia->delete();
                    }
                } catch (Throwable) {
                    // Best-effort rollback.
                }

                try {
                    $asset =
                        $createdItem['asset'];

                    if (
                        $asset
                        instanceof BakeryMediaAsset
                        && $asset->exists
                    ) {
                        $asset->forceDelete();
                    }
                } catch (Throwable) {
                    // Best-effort rollback.
                }
            }

            throw $exception;
        }
    }
}
