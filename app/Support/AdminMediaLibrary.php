<?php

namespace App\Support;

use App\Models\BakeryMediaAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AdminMediaLibrary
{
    /**
     * @return array<string, string>
     */
    public static function imageUrlOptions(?string $current = null): array
    {
        $options = [];

        self::eligibleAssets()->each(function (BakeryMediaAsset $asset) use (&$options): void {
            try {
                $url = $asset->optimizedUrl();
                if ($url === null) {
                    return;
                }

                $options[$url] = self::label($asset);
            } catch (Throwable $exception) {
                self::reportUnusableAsset($asset, 'url-option', $exception);
            }
        });

        return self::preserveCurrent($options, $current);
    }

    /**
     * Return public-disk relative paths for legacy fields that are intentionally
     * stored as paths rather than absolute URLs (for example category.image_path).
     * A relative path is safe only when the preview conversion itself lives on
     * the public disk because the public API resolves these fields with
     * Storage::disk('public')->url(...).
     *
     * @return array<string, string>
     */
    public static function imagePathOptions(?string $current = null): array
    {
        $options = [];

        self::eligibleAssets()->each(function (BakeryMediaAsset $asset) use (&$options): void {
            try {
                $media = $asset->sourceMedia();
                if ($media === null || ! $media->hasGeneratedConversion('preview')) {
                    return;
                }

                $conversionDisk = trim((string) ($media->conversions_disk ?: $media->disk));
                if ($conversionDisk !== 'public') {
                    self::reportSkippedAsset($asset, 'path-option-non-public-disk');

                    return;
                }

                $path = trim($media->getPathRelativeToRoot('preview'));
                if ($path === '') {
                    return;
                }

                $options[$path] = self::label($asset);
            } catch (Throwable $exception) {
                self::reportUnusableAsset($asset, 'path-option', $exception);
            }
        });

        return self::preserveCurrent($options, $current);
    }

    /**
     * @return Collection<int, BakeryMediaAsset>
     */
    private static function eligibleAssets(): Collection
    {
        return BakeryMediaAsset::query()
            ->with('media')
            ->whereIn('status', [BakeryMediaAsset::STATUS_READY, BakeryMediaAsset::STATUS_ASSIGNED])
            ->latest('updated_at')
            ->get()
            ->filter(function (BakeryMediaAsset $asset): bool {
                try {
                    return $asset->publicPreviewWithinBudget();
                } catch (Throwable $exception) {
                    self::reportUnusableAsset($asset, 'eligibility', $exception);

                    return false;
                }
            })
            ->values();
    }

    private static function label(BakeryMediaAsset $asset): string
    {
        $label = trim((string) $asset->title);
        if ($label === '') {
            $label = 'رسانه #'.$asset->getKey();
        }

        return $label.' — '.$asset->optimizedSizeLabel();
    }

    private static function reportSkippedAsset(BakeryMediaAsset $asset, string $stage): void
    {
        Log::warning('Admin media library skipped an incompatible asset.', [
            'asset_id' => $asset->getKey(),
            'stage' => $stage,
        ]);
    }

    private static function reportUnusableAsset(BakeryMediaAsset $asset, string $stage, Throwable $exception): void
    {
        Log::warning('Admin media library skipped an unusable asset.', [
            'asset_id' => $asset->getKey(),
            'stage' => $stage,
            'exception' => $exception::class,
        ]);
    }

    /**
     * @param array<string, string> $options
     * @return array<string, string>
     */
    private static function preserveCurrent(array $options, ?string $current): array
    {
        $current = trim((string) $current);
        if ($current !== '' && ! array_key_exists($current, $options)) {
            return [$current => 'تصویر فعلی (Legacy / نیازمند بازبینی کتابخانه)'] + $options;
        }

        return $options;
    }
}
