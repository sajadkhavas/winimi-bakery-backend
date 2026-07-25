<?php

namespace App\Console\Commands;

use App\Models\BakeryCategory;
use App\Models\BakeryProduct;
use App\Models\BakeryProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class AuditCatalogDraftImport extends Command
{
    protected $signature = 'catalog:audit-draft-import
        {manifest : Absolute path to catalog-import-manifest.json}
        {--assets= : Root directory containing the exported assets directory}';

    protected $description = 'Validate a prepared legacy catalog bundle without changing the database';

    /** @var array<int, string> */
    private array $blockers = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<string, true> */
    private array $seenCategorySlugs = [];

    /** @var array<string, true> */
    private array $seenProductSlugs = [];

    /** @var array<string, true> */
    private array $seenProductCodes = [];

    /** @var array<string, true> */
    private array $seenVariantSkus = [];

    private int $categoriesToCreate = 0;

    private int $productsToCreate = 0;

    private int $variantsToCreate = 0;

    private int $mediaToAttach = 0;

    private int $databaseConflicts = 0;

    public function handle(): int
    {
        $manifestPath = $this->resolveManifestPath((string) $this->argument('manifest'));

        if ($manifestPath === null) {
            return self::FAILURE;
        }

        $assetsRoot = $this->resolveAssetsRoot(
            trim((string) $this->option('assets')),
            dirname($manifestPath),
        );

        if ($assetsRoot === null) {
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
        } catch (Throwable $exception) {
            $this->error("MANIFEST_READ_FAILED={$exception->getMessage()}");

            return self::FAILURE;
        }

        if (! is_array($manifest)) {
            $this->error('MANIFEST_ROOT_INVALID=Expected a JSON object.');

            return self::FAILURE;
        }

        $this->auditManifest($manifest, $assetsRoot);
        $this->renderSummary($manifestPath, $assetsRoot);

        return $this->blockers === [] ? self::SUCCESS : self::FAILURE;
    }

    private function resolveManifestPath(string $value): ?string
    {
        $path = realpath($value);

        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            $this->error('MANIFEST_NOT_READABLE='.($value !== '' ? $value : '(empty)'));

            return null;
        }

        return $path;
    }

    private function resolveAssetsRoot(string $value, string $fallback): ?string
    {
        $path = realpath($value !== '' ? $value : $fallback);

        if ($path === false || ! is_dir($path) || ! is_readable($path)) {
            $this->error('ASSETS_ROOT_NOT_READABLE='.($value !== '' ? $value : $fallback));

            return null;
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /** @param array<string, mixed> $manifest */
    private function auditManifest(array $manifest, string $assetsRoot): void
    {
        if (($manifest['format'] ?? null) !== 'winimi-catalog-draft-import-v1') {
            $this->blockers[] = 'Unsupported manifest format.';
        }

        $this->auditImportPolicy($manifest['importPolicy'] ?? null);

        $categories = $manifest['categories'] ?? null;
        $products = $manifest['products'] ?? null;

        if (! is_array($categories)) {
            $this->blockers[] = 'Manifest categories must be an array.';
            $categories = [];
        }

        if (! is_array($products)) {
            $this->blockers[] = 'Manifest products must be an array.';
            $products = [];
        }

        foreach ($categories as $index => $category) {
            if (! is_array($category)) {
                $this->blockers[] = "Category at index {$index} must be an object.";

                continue;
            }

            $this->auditCategory($category, $index);
        }

        foreach ($products as $index => $product) {
            if (! is_array($product)) {
                $this->blockers[] = "Product at index {$index} must be an object.";

                continue;
            }

            $this->auditProduct($product, $index, $assetsRoot);
        }
    }

    private function auditImportPolicy(mixed $policy): void
    {
        if (! is_array($policy)) {
            $this->blockers[] = 'Manifest importPolicy must be an object.';

            return;
        }

        foreach ([
            'categoriesActive',
            'productsActive',
            'variantsActive',
            'contentVerified',
            'mediaVerified',
        ] as $key) {
            if (($policy[$key] ?? null) !== false) {
                $this->blockers[] = "Unsafe import policy: {$key} must be false.";
            }
        }

        if (($policy['stockQuantity'] ?? null) !== 0) {
            $this->blockers[] = 'Unsafe import policy: stockQuantity must be zero.';
        }
    }

    /** @param array<string, mixed> $category */
    private function auditCategory(array $category, int $index): void
    {
        $label = "category[{$index}]";
        $name = $this->requiredString($category, 'name', 120, $label);
        $slug = $this->requiredSlug($category, 'slug', 140, $label);

        if (($category['isActive'] ?? null) !== false) {
            $this->blockers[] = "{$label}.isActive must be false.";
        }

        $this->nonNegativeInteger($category, 'sortOrder', $label);

        if ($name === null || $slug === null) {
            return;
        }

        if (isset($this->seenCategorySlugs[$slug])) {
            $this->blockers[] = "Duplicate category slug in manifest: {$slug}.";

            return;
        }

        $this->seenCategorySlugs[$slug] = true;

        if (BakeryCategory::withTrashed()->where('slug', $slug)->exists()) {
            $this->databaseConflicts++;
            $this->blockers[] = "Database category slug conflict: {$slug}.";

            return;
        }

        $this->categoriesToCreate++;
    }

    /** @param array<string, mixed> $product */
    private function auditProduct(array $product, int $index, string $assetsRoot): void
    {
        $label = "product[{$index}]";
        $name = $this->requiredString($product, 'name', 180, $label);
        $slug = $this->requiredSlug($product, 'slug', 200, $label);
        $productCode = $this->requiredString($product, 'productCode', 80, $label);
        $categorySlug = $this->requiredSlug($product, 'categorySlug', 140, $label);

        $this->nullableString($product, 'shortDescription', 320, $label);
        $this->nullableString($product, 'description', null, $label);
        $this->nullableString($product, 'shelfLife', 220, $label);
        $this->nullableString($product, 'storageInstructions', null, $label);
        $this->nullablePositiveInteger($product, 'preparationTimeDays', $label);
        $this->nonNegativeInteger($product, 'sortOrder', $label);
        $this->stringList($product, 'ingredients', $label);
        $this->stringList($product, 'allergens', $label);

        foreach (['contentVerified', 'mediaVerified', 'isActive'] as $key) {
            if (($product[$key] ?? null) !== false) {
                $this->blockers[] = "{$label}.{$key} must be false.";
            }
        }

        foreach (['requiresCooling', 'isFeatured'] as $key) {
            if (! is_bool($product[$key] ?? null)) {
                $this->blockers[] = "{$label}.{$key} must be boolean.";
            }
        }

        if (($product['migrationStatus'] ?? null) !== 'draft-ready') {
            $this->blockers[] = "{$label}.migrationStatus must be draft-ready.";
        }

        if ($categorySlug !== null && ! isset($this->seenCategorySlugs[$categorySlug])) {
            $this->blockers[] = "{$label} references unknown category {$categorySlug}.";
        }

        if ($slug !== null) {
            if (isset($this->seenProductSlugs[$slug])) {
                $this->blockers[] = "Duplicate product slug in manifest: {$slug}.";
            }
            $this->seenProductSlugs[$slug] = true;
        }

        if ($productCode !== null) {
            if (isset($this->seenProductCodes[$productCode])) {
                $this->blockers[] = "Duplicate product code in manifest: {$productCode}.";
            }
            $this->seenProductCodes[$productCode] = true;
        }

        if ($slug !== null || $productCode !== null) {
            $databaseProductConflict = BakeryProduct::withTrashed()
                ->where(function ($query) use ($slug, $productCode): void {
                    if ($slug !== null) {
                        $query->where('slug', $slug);
                    }

                    if ($productCode !== null) {
                        $method = $slug !== null ? 'orWhere' : 'where';
                        $query->{$method}('product_code', $productCode);
                    }
                })
                ->exists();

            if ($databaseProductConflict) {
                $this->databaseConflicts++;
                $this->blockers[] = "Database product conflict: {$productCode}/{$slug}.";
            }
        }

        $variants = $product['variants'] ?? null;
        if (! is_array($variants) || $variants === []) {
            $this->blockers[] = "{$label}.variants must contain at least one variant.";
            $variants = [];
        }

        $defaultVariants = 0;
        $productVariantNames = [];
        foreach ($variants as $variantIndex => $variant) {
            if (! is_array($variant)) {
                $this->blockers[] = "{$label}.variants[{$variantIndex}] must be an object.";

                continue;
            }

            $variantName = $this->auditVariant($variant, $label, $variantIndex);
            if ($variantName !== null) {
                if (isset($productVariantNames[$variantName])) {
                    $this->blockers[] = "Duplicate variant name for {$productCode}: {$variantName}.";
                }
                $productVariantNames[$variantName] = true;
            }

            if (($variant['isDefault'] ?? null) === true) {
                $defaultVariants++;
            }
        }

        if ($defaultVariants !== 1) {
            $this->blockers[] = "{$label} must contain exactly one default variant.";
        }

        $this->auditProductImage($product['image'] ?? null, $label, $assetsRoot);

        if ($name !== null && $slug !== null && $productCode !== null && $categorySlug !== null) {
            $this->productsToCreate++;
        }
    }

    /** @param array<string, mixed> $variant */
    private function auditVariant(array $variant, string $productLabel, int $index): ?string
    {
        $label = "{$productLabel}.variants[{$index}]";
        $name = $this->requiredString($variant, 'name', 120, $label);
        $sku = $this->requiredString($variant, 'sku', 100, $label);
        $regularPrice = $this->positiveInteger($variant, 'regularPriceToman', $label);
        $salePrice = $this->nullablePositiveInteger($variant, 'salePriceToman', $label);
        $this->nullablePositiveInteger($variant, 'weightGrams', $label);
        $stock = $this->nonNegativeInteger($variant, 'stockQuantity', $label);
        $this->nonNegativeInteger($variant, 'sortOrder', $label);

        if ($stock !== null && $stock !== 0) {
            $this->blockers[] = "{$label}.stockQuantity must be zero.";
        }

        if (($variant['isActive'] ?? null) !== false) {
            $this->blockers[] = "{$label}.isActive must be false.";
        }

        if (! is_bool($variant['isDefault'] ?? null)) {
            $this->blockers[] = "{$label}.isDefault must be boolean.";
        }

        if ($regularPrice !== null && $salePrice !== null && $salePrice >= $regularPrice) {
            $this->blockers[] = "{$label}.salePriceToman must be lower than regularPriceToman.";
        }

        if ($sku !== null) {
            if (isset($this->seenVariantSkus[$sku])) {
                $this->blockers[] = "Duplicate variant SKU in manifest: {$sku}.";
            }
            $this->seenVariantSkus[$sku] = true;

            if (BakeryProductVariant::query()->where('sku', $sku)->exists()) {
                $this->databaseConflicts++;
                $this->blockers[] = "Database variant SKU conflict: {$sku}.";
            }
        }

        if ($name !== null && $sku !== null && $regularPrice !== null) {
            $this->variantsToCreate++;
        }

        return $name;
    }

    private function auditProductImage(mixed $image, string $label, string $assetsRoot): void
    {
        if (! is_array($image)) {
            $this->blockers[] = "{$label}.image must be an object.";

            return;
        }

        $portablePath = $image['portablePath'] ?? null;
        if (! is_string($portablePath) || $portablePath === '') {
            $this->blockers[] = "{$label}.image.portablePath is required.";

            return;
        }

        if (
            Str::startsWith($portablePath, ['/','\\'])
            || str_contains($portablePath, '..')
            || str_contains($portablePath, '\\')
            || ! Str::startsWith($portablePath, 'assets/')
        ) {
            $this->blockers[] = "{$label}.image.portablePath is unsafe: {$portablePath}.";

            return;
        }

        $assetPath = realpath($assetsRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $portablePath));
        $allowedPrefix = $assetsRoot.DIRECTORY_SEPARATOR;

        if (
            $assetPath === false
            || ! is_file($assetPath)
            || ! is_readable($assetPath)
            || ! str_starts_with($assetPath, $allowedPrefix)
        ) {
            $this->blockers[] = "{$label} image is missing or outside assets root: {$portablePath}.";

            return;
        }

        if (filesize($assetPath) === 0) {
            $this->blockers[] = "{$label} image is empty: {$portablePath}.";

            return;
        }

        $this->mediaToAttach++;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key, ?int $maximum, string $label): ?string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            $this->blockers[] = "{$label}.{$key} is required.";

            return null;
        }

        $value = trim($value);
        if ($maximum !== null && mb_strlen($value) > $maximum) {
            $this->blockers[] = "{$label}.{$key} exceeds {$maximum} characters.";

            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function requiredSlug(array $data, string $key, int $maximum, string $label): ?string
    {
        $value = $this->requiredString($data, $key, $maximum, $label);

        if ($value !== null && ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $this->blockers[] = "{$label}.{$key} must be a lowercase ASCII slug.";

            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function nullableString(array $data, string $key, ?int $maximum, string $label): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            $this->blockers[] = "{$label}.{$key} must be a string or null.";

            return null;
        }

        if ($maximum !== null && mb_strlen($value) > $maximum) {
            $this->blockers[] = "{$label}.{$key} exceeds {$maximum} characters.";

            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function positiveInteger(array $data, string $key, string $label): ?int
    {
        $value = $data[$key] ?? null;
        if (! is_int($value) || $value < 1) {
            $this->blockers[] = "{$label}.{$key} must be an integer greater than zero.";

            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function nullablePositiveInteger(array $data, string $key, string $label): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        return $this->positiveInteger($data, $key, $label);
    }

    /** @param array<string, mixed> $data */
    private function nonNegativeInteger(array $data, string $key, string $label): ?int
    {
        $value = $data[$key] ?? null;
        if (! is_int($value) || $value < 0) {
            $this->blockers[] = "{$label}.{$key} must be a non-negative integer.";

            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function stringList(array $data, string $key, string $label): void
    {
        $value = $data[$key] ?? null;
        if (! is_array($value)) {
            $this->blockers[] = "{$label}.{$key} must be an array.";

            return;
        }

        foreach ($value as $index => $item) {
            if (! is_string($item) || trim($item) === '') {
                $this->blockers[] = "{$label}.{$key}[{$index}] must be a non-empty string.";
            }
        }
    }

    private function renderSummary(string $manifestPath, string $assetsRoot): void
    {
        $status = $this->blockers === [] ? 'ready' : 'blocked';

        $this->newLine();
        $this->line('MODE=DRY_RUN');
        $this->line("CATALOG_DRAFT_AUDIT_STATUS={$status}");
        $this->line('MANIFEST_SHA256='.hash_file('sha256', $manifestPath));
        $this->line("MANIFEST={$manifestPath}");
        $this->line("ASSETS_ROOT={$assetsRoot}");
        $this->line("CATEGORIES_TO_CREATE={$this->categoriesToCreate}");
        $this->line("PRODUCTS_TO_CREATE={$this->productsToCreate}");
        $this->line("VARIANTS_TO_CREATE={$this->variantsToCreate}");
        $this->line("MEDIA_TO_ATTACH={$this->mediaToAttach}");
        $this->line("DATABASE_CONFLICTS={$this->databaseConflicts}");
        $this->line('BLOCKERS='.count($this->blockers));
        $this->line('WARNINGS='.count($this->warnings));

        if ($this->blockers !== []) {
            $this->newLine();
            $this->error('BLOCKER_DETAILS');
            foreach ($this->blockers as $blocker) {
                $this->line("- {$blocker}");
            }
        }

        if ($this->warnings !== []) {
            $this->newLine();
            $this->warn('WARNING_DETAILS');
            foreach ($this->warnings as $warning) {
                $this->line("- {$warning}");
            }
        }

        $this->newLine();
        $this->line('DATABASE_MUTATIONS=0');
    }
}
