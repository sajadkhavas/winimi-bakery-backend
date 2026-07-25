<?php

namespace App\Console\Commands;

use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use App\Models\BakeryProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class ImportCatalogDrafts extends Command
{
    protected $signature = 'catalog:import-drafts
        {manifest : Absolute path to catalog-import-manifest.json}
        {--assets= : Root directory containing the exported assets directory}
        {--expected-sha256= : Required SHA-256 pin for the manifest}
        {--confirm= : Must equal IMPORT_DRAFTS}';

    protected $description = 'Import a validated catalog bundle as inactive drafts with zero stock';

    public function handle(): int
    {
        $manifestPath = realpath((string) $this->argument('manifest'));
        if ($manifestPath === false || ! is_file($manifestPath) || ! is_readable($manifestPath)) {
            $this->error('MANIFEST_NOT_READABLE');

            return self::FAILURE;
        }

        $assetsRoot = realpath(trim((string) $this->option('assets')) ?: dirname($manifestPath));
        if ($assetsRoot === false || ! is_dir($assetsRoot) || ! is_readable($assetsRoot)) {
            $this->error('ASSETS_ROOT_NOT_READABLE');

            return self::FAILURE;
        }
        $assetsRoot = rtrim($assetsRoot, DIRECTORY_SEPARATOR);

        if ((string) $this->option('confirm') !== 'IMPORT_DRAFTS') {
            $this->error('CONFIRMATION_REQUIRED=Use --confirm=IMPORT_DRAFTS');

            return self::FAILURE;
        }

        $actualHash = hash_file('sha256', $manifestPath);
        $expectedHash = strtolower(trim((string) $this->option('expected-sha256')));
        if ($expectedHash === '' || ! hash_equals($expectedHash, $actualHash)) {
            $this->error("MANIFEST_HASH_MISMATCH=actual:{$actualHash}");

            return self::FAILURE;
        }

        $auditExitCode = $this->call('catalog:audit-draft-import', [
            'manifest' => $manifestPath,
            '--assets' => $assetsRoot,
        ]);

        if ($auditExitCode !== self::SUCCESS) {
            $this->error('IMPORT_ABORTED=AUDIT_FAILED');

            return self::FAILURE;
        }

        try {
            $manifest = json_decode(
                (string) file_get_contents($manifestPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            $this->error("MANIFEST_JSON_INVALID={$exception->getMessage()}");

            return self::FAILURE;
        }

        $categories = is_array($manifest['categories'] ?? null) ? $manifest['categories'] : [];
        $products = is_array($manifest['products'] ?? null) ? $manifest['products'] : [];

        $categoryMap = [];
        $productMap = [];

        try {
            DB::transaction(function () use ($categories, $products, &$categoryMap, &$productMap): void {
                foreach ($categories as $categoryData) {
                    $category = new BakeryCategory();
                    $category->forceFill([
                        'public_id' => (string) Str::ulid(),
                        'name' => trim((string) $categoryData['name']),
                        'slug' => (string) $categoryData['slug'],
                        'description' => $this->nullableString($categoryData['description'] ?? null),
                        'is_active' => false,
                        'sort_order' => (int) ($categoryData['sortOrder'] ?? 0),
                    ]);
                    $category->saveQuietly();

                    $categoryMap[(string) $categoryData['slug']] = $category;
                }

                foreach ($products as $productData) {
                    $categorySlug = (string) $productData['categorySlug'];
                    $category = $categoryMap[$categorySlug] ?? null;
                    if (! $category instanceof BakeryCategory) {
                        throw new \RuntimeException("Category mapping missing: {$categorySlug}");
                    }

                    $seo = is_array($productData['seo'] ?? null) ? $productData['seo'] : [];

                    $product = new BakeryProduct();
                    $product->forceFill([
                        'public_id' => (string) Str::ulid(),
                        'category_id' => $category->getKey(),
                        'name' => trim((string) $productData['name']),
                        'slug' => (string) $productData['slug'],
                        'product_code' => trim((string) $productData['productCode']),
                        'short_description' => $this->nullableString($productData['shortDescription'] ?? null),
                        'description' => $this->nullableString($productData['description'] ?? null),
                        'ingredients' => is_array($productData['ingredients'] ?? null) ? $productData['ingredients'] : [],
                        'allergens' => is_array($productData['allergens'] ?? null) ? $productData['allergens'] : [],
                        'shelf_life' => $this->nullableString($productData['shelfLife'] ?? null),
                        'storage_instructions' => $this->nullableString($productData['storageInstructions'] ?? null),
                        'preparation_time_days' => $productData['preparationTimeDays'] ?? null,
                        'requires_cooling' => (bool) ($productData['requiresCooling'] ?? false),
                        'content_verified' => false,
                        'media_verified' => false,
                        'is_active' => false,
                        'is_featured' => (bool) ($productData['isFeatured'] ?? false),
                        'sort_order' => (int) ($productData['sortOrder'] ?? 0),
                        'meta_title' => $this->limitedNullableString($seo['title'] ?? null, 70),
                        'meta_description' => $this->limitedNullableString($seo['description'] ?? null, 180),
                    ]);
                    $product->saveQuietly();

                    foreach ($productData['variants'] as $variantData) {
                        BakeryProductVariant::query()->create([
                            'product_id' => $product->getKey(),
                            'name' => trim((string) $variantData['name']),
                            'sku' => trim((string) $variantData['sku']),
                            'weight_grams' => $variantData['weightGrams'] ?? null,
                            'regular_price_toman' => (int) $variantData['regularPriceToman'],
                            'sale_price_toman' => $variantData['salePriceToman'] ?? null,
                            'stock_quantity' => 0,
                            'low_stock_threshold' => 5,
                            'is_default' => (bool) ($variantData['isDefault'] ?? false),
                            'is_active' => false,
                            'sort_order' => (int) ($variantData['sortOrder'] ?? 0),
                        ]);
                    }

                    $productMap[(string) $productData['productCode']] = [
                        'model' => $product,
                        'image' => $productData['image'] ?? null,
                    ];
                }
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->error("DATABASE_IMPORT_FAILED={$exception->getMessage()}");

            return self::FAILURE;
        }

        $mediaAttached = 0;
        $mediaFailed = 0;

        foreach ($productMap as $productCode => $entry) {
            /** @var BakeryProduct $product */
            $product = $entry['model'];
            $image = is_array($entry['image']) ? $entry['image'] : [];
            $portablePath = (string) ($image['portablePath'] ?? '');
            $assetPath = $this->resolveAssetPath($assetsRoot, $portablePath);

            if ($assetPath === null) {
                $mediaFailed++;
                $this->warn("MEDIA_SKIPPED={$productCode}:{$portablePath}");

                continue;
            }

            try {
                $product
                    ->addMedia($assetPath)
                    ->preservingOriginal()
                    ->usingName($product->name)
                    ->toMediaCollection('catalog-main');
                $mediaAttached++;
            } catch (Throwable $exception) {
                report($exception);
                $mediaFailed++;
                $this->warn("MEDIA_FAILED={$productCode}:{$exception->getMessage()}");
            }
        }

        $importedProductIds = array_map(
            static fn (array $entry): int => (int) $entry['model']->getKey(),
            array_values($productMap),
        );
        $status = $mediaFailed === 0 ? 'completed' : 'completed-with-media-warnings';

        $this->newLine();
        $this->line("CATALOG_DRAFT_IMPORT_STATUS={$status}");
        $this->line("MANIFEST_SHA256={$actualHash}");
        $this->line('CATEGORIES_CREATED='.count($categoryMap));
        $this->line('PRODUCTS_CREATED='.count($productMap));
        $this->line('VARIANTS_CREATED='.BakeryProductVariant::query()
            ->whereIn('product_id', $importedProductIds)
            ->count());
        $this->line("MEDIA_ATTACHED={$mediaAttached}");
        $this->line("MEDIA_FAILED={$mediaFailed}");
        $this->line('PUBLIC_PRODUCTS_CREATED=0');
        $this->line('ALL_IMPORTED_PRODUCTS_ACTIVE=false');
        $this->line('ALL_IMPORTED_VARIANTS_ACTIVE=false');
        $this->line('ALL_IMPORTED_STOCK_ZERO=true');

        return self::SUCCESS;
    }

    private function resolveAssetPath(string $assetsRoot, string $portablePath): ?string
    {
        if (
            $portablePath === ''
            || Str::startsWith($portablePath, ['/', '\\'])
            || str_contains($portablePath, '..')
            || str_contains($portablePath, '\\')
            || ! Str::startsWith($portablePath, 'assets/')
        ) {
            return null;
        }

        $path = realpath($assetsRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $portablePath));
        if (
            $path === false
            || ! is_file($path)
            || ! is_readable($path)
            || ! str_starts_with($path, $assetsRoot.DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        return $path;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function limitedNullableString(mixed $value, int $limit): ?string
    {
        $value = $this->nullableString($value);

        return $value === null ? null : Str::limit($value, $limit, '');
    }
}
