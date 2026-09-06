<?php

namespace App\Console\Commands;

use App\Models\BakeryCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class SyncCategoryImages extends Command
{
    protected $signature = 'catalog:sync-category-images
        {manifest : Absolute path to category-images-manifest.json}
        {--assets= : Root directory containing the exported assets directory}
        {--apply : Copy audited images and update inactive category records}
        {--expected-sha256= : Required manifest SHA-256 pin when --apply is used}
        {--confirm= : Must equal SYNC_CATEGORY_IMAGES when --apply is used}';

    protected $description = 'Audit or synchronize canonical category images while keeping categories inactive';

    /** @var array<int, string> */
    private array $blockers = [];

    /** @var array<int, array{slug: string, name: string, portablePath: string, targetPath: string, sizeBytes: int, sha256: string, sourcePath: string}> */
    private array $validatedImages = [];

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

        $this->auditManifest($manifest, $assetsRoot);
        $this->renderAudit($manifestPath, $assetsRoot, $actualHash);

        if ($this->blockers !== []) {
            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->line('FILES_WRITTEN=0');
            $this->line('DATABASE_MUTATIONS=0');

            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'SYNC_CATEGORY_IMAGES') {
            $this->error('CONFIRMATION_REQUIRED=Use --confirm=SYNC_CATEGORY_IMAGES');

            return self::FAILURE;
        }

        $expectedHash = strtolower(trim((string) $this->option('expected-sha256')));
        if ($expectedHash === '' || ! hash_equals($expectedHash, $actualHash)) {
            $this->error("MANIFEST_HASH_MISMATCH=actual:{$actualHash}");

            return self::FAILURE;
        }

        $writtenTargets = [];

        try {
            $disk = Storage::disk('public');

            foreach ($this->validatedImages as $image) {
                if ($disk->exists($image['targetPath'])) {
                    throw new \RuntimeException("Target file appeared during sync: {$image['targetPath']}");
                }

                $contents = file_get_contents($image['sourcePath']);
                if ($contents === false) {
                    throw new \RuntimeException("Unable to read source image: {$image['portablePath']}");
                }

                if (! $disk->put($image['targetPath'], $contents, 'public')) {
                    throw new \RuntimeException("Unable to write category image: {$image['targetPath']}");
                }

                $writtenTargets[] = $image['targetPath'];

                $storedContents = $disk->get($image['targetPath']);
                if (
                    strlen($storedContents) !== $image['sizeBytes']
                    || hash('sha256', $storedContents) !== $image['sha256']
                ) {
                    throw new \RuntimeException("Stored image verification failed: {$image['targetPath']}");
                }
            }

            DB::transaction(function (): void {
                foreach ($this->validatedImages as $image) {
                    $category = BakeryCategory::query()
                        ->where('slug', $image['slug'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($category->is_active) {
                        throw new \RuntimeException(
                            "Category became active during image sync: {$category->slug}",
                        );
                    }

                    if (filled($category->image_path)) {
                        throw new \RuntimeException(
                            "Category image appeared during sync: {$category->slug}",
                        );
                    }

                    $category->forceFill([
                        'image_path' => $image['targetPath'],
                    ]);
                    $category->saveQuietly();
                }
            }, 3);
        } catch (Throwable $exception) {
            foreach ($writtenTargets as $target) {
                Storage::disk('public')->delete($target);
            }

            report($exception);
            $this->error("CATEGORY_IMAGE_SYNC_FAILED={$exception->getMessage()}");

            return self::FAILURE;
        }

        $verificationFailures = 0;
        $disk = Storage::disk('public');

        foreach ($this->validatedImages as $image) {
            $category = BakeryCategory::query()->where('slug', $image['slug'])->first();

            if (
                ! $category
                || $category->image_path !== $image['targetPath']
                || $category->is_active
                || ! $disk->exists($image['targetPath'])
            ) {
                $verificationFailures++;
                continue;
            }

            $storedContents = $disk->get($image['targetPath']);
            if (
                strlen($storedContents) !== $image['sizeBytes']
                || hash('sha256', $storedContents) !== $image['sha256']
            ) {
                $verificationFailures++;
            }
        }

        $this->newLine();
        $this->line('CATEGORY_IMAGE_SYNC_STATUS='.
            ($verificationFailures === 0 ? 'completed' : 'verification-failed'));
        $this->line("MANIFEST_SHA256={$actualHash}");
        $this->line('IMAGES_COPIED='.count($writtenTargets));
        $this->line('CATEGORIES_UPDATED='.count($this->validatedImages));
        $this->line('CATEGORIES_ACTIVATED=0');
        $this->line("VERIFICATION_FAILURES={$verificationFailures}");

        return $verificationFailures === 0 ? self::SUCCESS : self::FAILURE;
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
        if (($manifest['format'] ?? null) !== 'winimi-category-images-v1') {
            $this->blockers[] = 'Unsupported manifest format.';
        }

        $policy = $manifest['policy'] ?? null;
        if (! is_array($policy)) {
            $this->blockers[] = 'Manifest policy must be an object.';
        } else {
            if (($policy['categoriesRemainInactive'] ?? null) !== true) {
                $this->blockers[] = 'Unsafe policy: categoriesRemainInactive must be true.';
            }
            if (($policy['overwriteExistingImages'] ?? null) !== false) {
                $this->blockers[] = 'Unsafe policy: overwriteExistingImages must be false.';
            }
        }

        $images = $manifest['images'] ?? null;
        if (! is_array($images)) {
            $this->blockers[] = 'Manifest images must be an array.';

            return;
        }

        if (count($images) !== 6) {
            $this->blockers[] = 'Manifest must contain exactly six canonical category images.';
        }

        $seenSlugs = [];
        $seenTargets = [];

        foreach ($images as $index => $source) {
            $label = "images[{$index}]";
            if (! is_array($source)) {
                $this->blockers[] = "{$label} must be an object.";
                continue;
            }

            $slug = $this->requiredString($source, 'slug', 140, $label);
            $name = $this->requiredString($source, 'name', 120, $label);
            $portablePath = $this->requiredString($source, 'portablePath', 500, $label);
            $targetPath = $this->requiredString($source, 'targetPath', 500, $label);
            $sha256 = $this->requiredString($source, 'sha256', 64, $label);
            $sizeBytes = $source['sizeBytes'] ?? null;

            if (! is_int($sizeBytes) || $sizeBytes < 1) {
                $this->blockers[] = "{$label}.sizeBytes must be a positive integer.";
                $sizeBytes = null;
            }

            if (
                $slug === null
                || $name === null
                || $portablePath === null
                || $targetPath === null
                || $sha256 === null
                || $sizeBytes === null
            ) {
                continue;
            }

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $this->blockers[] = "{$label}.slug must be a lowercase ASCII slug.";
                continue;
            }

            if (! preg_match('/^[a-f0-9]{64}$/', $sha256)) {
                $this->blockers[] = "{$label}.sha256 must be a lowercase SHA-256 digest.";
                continue;
            }

            if (! $this->isSafeRelativePath($portablePath, 'assets/')) {
                $this->blockers[] = "{$label}.portablePath is unsafe: {$portablePath}.";
                continue;
            }

            if (! $this->isSafeRelativePath($targetPath, 'bakery/categories/')) {
                $this->blockers[] = "{$label}.targetPath is unsafe: {$targetPath}.";
                continue;
            }

            if (isset($seenSlugs[$slug])) {
                $this->blockers[] = "Duplicate category slug in manifest: {$slug}.";
                continue;
            }
            $seenSlugs[$slug] = true;

            if (isset($seenTargets[$targetPath])) {
                $this->blockers[] = "Duplicate image target in manifest: {$targetPath}.";
                continue;
            }
            $seenTargets[$targetPath] = true;

            $sourcePath = realpath(
                $assetsRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $portablePath),
            );
            if (
                $sourcePath === false
                || ! is_file($sourcePath)
                || ! is_readable($sourcePath)
                || ! str_starts_with($sourcePath, $assetsRoot.DIRECTORY_SEPARATOR)
            ) {
                $this->blockers[] = "Source image missing or outside assets root: {$portablePath}.";
                continue;
            }

            if (filesize($sourcePath) !== $sizeBytes) {
                $this->blockers[] = "Source image size mismatch: {$portablePath}.";
                continue;
            }

            if (hash_file('sha256', $sourcePath) !== $sha256) {
                $this->blockers[] = "Source image hash mismatch: {$portablePath}.";
                continue;
            }

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
                $this->blockers[] = "Database category must remain inactive: {$slug}.";
                continue;
            }
            if ($category->name !== $name) {
                $this->blockers[] = "Database category name mismatch: {$slug}.";
                continue;
            }
            if (filled($category->image_path)) {
                $this->blockers[] = "Database category already has an image: {$slug}.";
                continue;
            }
            if (Storage::disk('public')->exists($targetPath)) {
                $this->blockers[] = "Target image already exists: {$targetPath}.";
                continue;
            }

            $this->validatedImages[] = [
                'slug' => $slug,
                'name' => $name,
                'portablePath' => $portablePath,
                'targetPath' => $targetPath,
                'sizeBytes' => $sizeBytes,
                'sha256' => $sha256,
                'sourcePath' => $sourcePath,
            ];
        }
    }

    private function isSafeRelativePath(string $path, string $requiredPrefix): bool
    {
        return $path !== ''
            && Str::startsWith($path, $requiredPrefix)
            && ! Str::startsWith($path, ['/', '\\'])
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key, int $maximum, string $label): ?string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            $this->blockers[] = "{$label}.{$key} is required.";

            return null;
        }

        $value = trim($value);
        if (mb_strlen($value) > $maximum) {
            $this->blockers[] = "{$label}.{$key} exceeds {$maximum} characters.";

            return null;
        }

        return $value;
    }

    private function renderAudit(
        string $manifestPath,
        string $assetsRoot,
        string $actualHash,
    ): void {
        $mode = $this->option('apply') ? 'APPLY' : 'DRY_RUN';
        $status = $this->blockers === [] ? 'ready' : 'blocked';

        $this->line("MODE={$mode}");
        $this->line("CATEGORY_IMAGE_AUDIT_STATUS={$status}");
        $this->line("MANIFEST_SHA256={$actualHash}");
        $this->line("MANIFEST={$manifestPath}");
        $this->line("ASSETS_ROOT={$assetsRoot}");
        $this->line('VALID_IMAGES='.count($this->validatedImages));
        $this->line('IMAGES_TO_COPY='.count($this->validatedImages));
        $this->line('CATEGORY_RECORDS_TO_UPDATE='.count($this->validatedImages));
        $this->line('BLOCKERS='.count($this->blockers));

        foreach ($this->validatedImages as $image) {
            $this->line(
                "CATEGORY={$image['slug']}|SOURCE={$image['portablePath']}|TARGET={$image['targetPath']}|BYTES={$image['sizeBytes']}",
            );
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
