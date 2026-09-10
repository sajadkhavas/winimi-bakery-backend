<?php

namespace App\Support;

use App\Models\BakeryMediaAsset;

final class AdminMediaLibrary
{
    /**
     * @return array<string, string>
     */
    public static function imageUrlOptions(?string $current = null): array
    {
        $options = [];

        BakeryMediaAsset::query()
            ->whereIn('status', [BakeryMediaAsset::STATUS_READY, BakeryMediaAsset::STATUS_ASSIGNED])
            ->latest('updated_at')
            ->get()
            ->each(function (BakeryMediaAsset $asset) use (&$options): void {
                if (! $asset->publicPreviewWithinBudget()) {
                    return;
                }

                $url = $asset->optimizedUrl();
                if ($url === null) {
                    return;
                }

                $label = trim((string) $asset->title);
                if ($label === '') {
                    $label = 'رسانه #'.$asset->getKey();
                }

                $options[$url] = $label.' — '.$asset->optimizedSizeLabel();
            });

        $current = trim((string) $current);
        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options = [$current => 'تصویر فعلی (Legacy / نیازمند بازبینی کتابخانه)'] + $options;
        }

        return $options;
    }
}
