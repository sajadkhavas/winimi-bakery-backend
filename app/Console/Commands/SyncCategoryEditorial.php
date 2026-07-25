<?php

namespace App\Console\Commands;

use App\Models\BakeryCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

class SyncCategoryEditorial extends Command
{
    protected $signature = 'catalog:sync-category-editorial
        {manifest=database/data/winimi-category-editorial-v1.json : Manifest path, relative paths resolve from the Laravel root}
        {--apply : Apply the audited category metadata changes}
        {--expected-sha256= : Required SHA-256 pin when --apply is used}
        {--confirm= : Must equal SYNC_CATEGORY_EDITORIAL when --apply is used}';

    protected $description = 'Audit or synchronize canonical bakery category names, descriptions and SEO metadata';

    /** @var array<int, string> */
    private array $blockers = [];

    /** @var array<int, array<string, mixed>> */
    private array $changes = [];

    public function handle(): int
    {
        $manifestPath = $this->resolveManifestPath((string) $this->argument('manifest'));
        if ($manifestPath === null) {
            return self::FAILURE;
        }

        $actualHash = hash_file('sha256', $manifestPath);

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
            $this->error('MANIFEST_ROOT_INVALID');

            return self::FAILURE;
        }

        $categories = $this->auditManifest($manifest);
        $this->renderAudit($manifestPath, $actualHash, count($categories));

        if ($this->blockers !== []) {
            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->line('DATABASE_MUTATIONS=0');

            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'SYNC_CATEGORY_EDITORIAL') {
            $this->error('CONFIRMATION_REQUIRED=Use --confirm=SYNC_CATEGORY_EDITORIAL');

            return self::FAILURE;
        }

        $expectedHash = strtolower(trim((string) $this->option('expected-sha256')));
        if ($expectedHash === '' || ! hash_equals($expectedHash, $actualHash)) {
            $this->error("MANIFEST_HASH_MISMATCH=actual:{$actualHash}");

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($categories): void {
                foreach ($categories as $data) {
                    $category = BakeryCategory::query()
                        ->where('slug', $data['slug'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($category->is_active) {
                        throw new \RuntimeException(
                            "Category became active during sync: {$category->slug}",
                        );
                    }

                    $category->forceFill([
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'meta_title' => $data['metaTitle'],
                        'meta_description' => $data['metaDescription'],
                    ]);
                    $category->saveQuietly();
                }
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->error("CATEGORY_EDITORIAL_SYNC_FAILED={$exception->getMessage()}");

            return self::FAILURE;
        }

        $verificationFailures = 0;
        foreach ($categories as $data) {
            $category = BakeryCategory::query()->where('slug', $data['slug'])->first();
            if (
                ! $category
                || $category->name !== $data['name']
                || $category->description !== $data['description']
                || $category->meta_title !== $data['metaTitle']
                || $category->meta_description !== $data['metaDescription']
                || $category->is_active
            ) {
                $verificationFailures++;
            }
        }

        $this->newLine();
        $this->line('CATEGORY_EDITORIAL_SYNC_STATUS='.
            ($verificationFailures === 0 ? 'completed' : 'verification-failed'));
        $this->line("MANIFEST_SHA256={$actualHash}");
        $this->line('CATEGORIES_SYNCHRONIZED='.count($categories));
        $this->line('CATEGORIES_ACTIVATED=0');
        $this->line("VERIFICATION_FAILURES={$verificationFailures}");

        return $verificationFailures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resolveManifestPath(string $value): ?string
    {
        $candidate = str_starts_with($value, DIRECTORY_SEPARATOR)
            ? $value
            : base_path($value);
        $path = realpath($candidate);

        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            $this->error("MANIFEST_NOT_READABLE={$candidate}");

            return null;
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<int, array{slug: string, name: string, description: string, metaTitle: string, metaDescription: string}>
     */
    private function auditManifest(array $manifest): array
    {
        if (($manifest['format'] ?? null) !== 'winimi-category-editorial-v1') {
            $this->blockers[] = 'Unsupported manifest format.';
        }

        $sourceCategories = $manifest['categories'] ?? null;
        if (! is_array($sourceCategories)) {
            $this->blockers[] = 'Manifest categories must be an array.';

            return [];
        }

        $categories = [];
        $seenSlugs = [];

        foreach ($sourceCategories as $index => $source) {
            $label = "categories[{$index}]";
            if (! is_array($source)) {
                $this->blockers[] = "{$label} must be an object.";

                continue;
            }

            $slug = $this->requiredString($source, 'slug', 140, $label);
            $name = $this->requiredString($source, 'name', 120, $label);
            $description = $this->requiredString($source, 'description', null, $label);
            $metaTitle = $this->requiredString($source, 'metaTitle', 70, $label);
            $metaDescription = $this->requiredString($source, 'metaDescription', 180, $label);

            if (
                $slug === null
                || $name === null
                || $description === null
                || $metaTitle === null
                || $metaDescription === null
            ) {
                continue;
            }

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $this->blockers[] = "{$label}.slug must be a lowercase ASCII slug.";
                continue;
            }

            if (isset($seenSlugs[$slug])) {
                $this->blockers[] = "Duplicate category slug in manifest: {$slug}.";
                continue;
            }
            $seenSlugs[$slug] = true;

            $category = BakeryCategory::withTrashed()->where('slug', $slug)->first();
            if (! $category) {
                $this->blockers[] = "Database category missing: {$slug}.";
                continue;
            }
            if ($category->trashed()) {
                $this->blockers[] = "Database category is deleted: {$slug}.";
                continue;
            }
            if ($category->is_active) {
                $this->blockers[] = "Database category must remain inactive during editorial sync: {$slug}.";
                continue;
            }

            $changedFields = [];
            foreach ([
                'name' => $name,
                'description' => $description,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
            ] as $field => $value) {
                if ($category->{$field} !== $value) {
                    $changedFields[] = $field;
                }
            }

            $this->changes[] = [
                'slug' => $slug,
                'changedFields' => $changedFields,
                'products' => $category->products()->withTrashed()->count(),
                'active' => $category->is_active,
            ];

            $categories[] = [
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'metaTitle' => $metaTitle,
                'metaDescription' => $metaDescription,
            ];
        }

        if (count($categories) !== 6) {
            $this->blockers[] = 'Canonical category manifest must contain exactly 6 valid categories.';
        }

        return $categories;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(
        array $data,
        string $key,
        ?int $maximum,
        string $label,
    ): ?string {
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

    private function renderAudit(string $manifestPath, string $actualHash, int $validCategories): void
    {
        $status = $this->blockers === [] ? 'ready' : 'blocked';
        $fieldsToChange = array_sum(array_map(
            static fn (array $change): int => count($change['changedFields']),
            $this->changes,
        ));

        $this->newLine();
        $this->line('MODE='.($this->option('apply') ? 'APPLY' : 'DRY_RUN'));
        $this->line("CATEGORY_EDITORIAL_AUDIT_STATUS={$status}");
        $this->line("MANIFEST_SHA256={$actualHash}");
        $this->line("MANIFEST={$manifestPath}");
        $this->line("VALID_CATEGORIES={$validCategories}");
        $this->line('CATEGORY_RECORDS_WITH_CHANGES='.count(array_filter(
            $this->changes,
            static fn (array $change): bool => $change['changedFields'] !== [],
        )));
        $this->line("FIELDS_TO_CHANGE={$fieldsToChange}");
        $this->line('BLOCKERS='.count($this->blockers));

        foreach ($this->changes as $change) {
            $fields = $change['changedFields'] === []
                ? 'none'
                : implode(',', $change['changedFields']);
            $this->line("CATEGORY={$change['slug']}|FIELDS={$fields}|PRODUCTS={$change['products']}|ACTIVE=false");
        }

        if ($this->blockers !== []) {
            $this->newLine();
            $this->error('BLOCKER_DETAILS');
            foreach ($this->blockers as $blocker) {
                $this->line("- {$blocker}");
            }
        }

        $this->newLine();
    }
}
