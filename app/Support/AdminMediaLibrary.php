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
            ->where('status', '!=', BakeryMediaAsset::STATUS_REJECTED)
            ->latest('updated_at')
            ->get()
            ->each(function (BakeryMediaAsset $asset) use (&$options): void {
                $media = $asset->sourceMedia();
                if ($media === null) {
                    return;
                }

                $url = $media->hasGeneratedConversion('preview')
                    ? $media->getFullUrl('preview')
                    : $media->getFullUrl();

                $label = trim((string) $asset->title);
                if ($label === '') {
                    $label = 'رسانه #'.$asset->getKey();
                }

                $options[$url] = $label;
            });

        $current = trim((string) $current);
        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options = [$current => 'تصویر فعلی (خارج از کتابخانه تخصصی)'] + $options;
        }

        return $options;
    }
}
