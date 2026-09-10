<?php

namespace App\Support;

use App\Models\BakeryMediaAsset;
use Illuminate\Support\Collection;

final class AdminMediaLibrary
{
    /**
     * @return array<string, string>
     */
    public static function imageUrlOptions(?string $current = null): array
    {
        $options = [];

        self::eligibleAssets()->each(function (BakeryMediaAsset $asset) use (&$options): void {
            $url = $asset->optimizedUrl();
            if ($url === null) {
                return;
            }

            $options[$url] = self::label($asset);
        });

        return self::preserveCurrent($options, $current);
    }

    /**
     * Return public-disk relative paths for legacy fields that are intentionally
     * stored as paths rather than absolute URLs (for example category.image_path).
     *
     * @return array<string, string>
     */
    public static function imagePathOptions(?string $current = null): array
    {
        $options = [];

        self::eligibleAssets()->each(function (BakeryMediaAsset $asset) use (&$options): void {
            $media = $asset->sourceMedia();
            if ($media === null || ! $media->hasGeneratedConversion('preview')) {
                return;
            }

            $path = trim($media->getPathRelativeToRoot('preview'));
            if ($path === '') {
                return;
            }

            $options[$path] = self::label($asset);
        });

        return self::preserveCurrent($options, $current);
    }

    /**
     * @return Collection<int, BakeryMediaAsset>
     */
    private static function eligibleAssets(): Collection
    {
        return BakeryMediaAsset::query()
            ->whereIn('status', [BakeryMediaAsset::STATUS_READY, BakeryMediaAsset::STATUS_ASSIGNED])
            ->latest('updated_at')
            ->get()
            ->filter(fn (BakeryMediaAsset $asset): bool => $asset->publicPreviewWithinBudget())
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
