<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BakeryMediaAsset extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    public const USAGE_UNASSIGNED = 'unassigned';

    public const USAGE_PRODUCT_MAIN = 'product_main';

    public const USAGE_PRODUCT_GALLERY = 'product_gallery';

    public const USAGE_HERO = 'hero';

    public const USAGE_BRAND = 'brand';

    public const USAGE_CATEGORY = 'category';

    public const USAGES = [
        self::USAGE_UNASSIGNED,
        self::USAGE_PRODUCT_MAIN,
        self::USAGE_PRODUCT_GALLERY,
        self::USAGE_HERO,
        self::USAGE_BRAND,
        self::USAGE_CATEGORY,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_READY,
        self::STATUS_ASSIGNED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'product_id',
        'title',
        'import_key',
        'source_filename',
        'source_sha256',
        'manifest_version',
        'alt_text',
        'usage',
        'status',
        'notes',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('source')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ]);
    }

    public function registerMediaConversions(
        ?Media $media = null,
    ): void {
        $this->addMediaConversion('thumb')
            ->performOnCollections('source')
            ->fit(Fit::Crop, 240, 240)
            ->format('webp')
            ->quality(80);

        $this->addMediaConversion('preview')
            ->performOnCollections('source')
            ->fit(Fit::Max, 1200, 1200)
            ->format('webp')
            ->quality(84);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            BakeryProduct::class,
            'product_id',
        );
    }

    public function assignToProduct(
        BakeryProduct $product,
        string $usage,
        ?string $altText = null,
    ): Media {
        if ($this->status !== self::STATUS_READY) {
            throw new \DomainException(
                'این رسانه باید ابتدا در وضعیت آماده تخصیص قرار بگیرد.'
            );
        }

        if (! in_array(
            $usage,
            [
                self::USAGE_PRODUCT_MAIN,
                self::USAGE_PRODUCT_GALLERY,
            ],
            true,
        )) {
            throw new \InvalidArgumentException(
                'کاربرد انتخاب‌شده برای رسانه محصول معتبر نیست.'
            );
        }

        $this->unsetRelation('media');
        $product->unsetRelation('media');

        $source = $this->sourceMedia();

        if (! $source instanceof Media) {
            throw new \DomainException(
                'فایل اصلی این رسانه پیدا نشد.'
            );
        }

        $collection = match ($usage) {
            self::USAGE_PRODUCT_MAIN => 'catalog-main',

            self::USAGE_PRODUCT_GALLERY => 'catalog-gallery',
        };

        if (
            $collection === 'catalog-main'
            && $product->getFirstMedia(
                'catalog-main'
            ) instanceof Media
        ) {
            throw new \DomainException(
                'این محصول از قبل تصویر اصلی دارد؛ جایگزینی خودکار انجام نشد.'
            );
        }

        $resolvedAlt = trim(
            (string) (
                $altText
                ?? $this->alt_text
                ?? $this->title
            )
        );

        if ($resolvedAlt === '') {
            $resolvedAlt = $product->name;
        }

        $copiedMedia = $source->copy(
            $product,
            $collection,
        );

        try {
            $copiedMedia
                ->setCustomProperty(
                    'alt',
                    $resolvedAlt,
                )
                ->setCustomProperty(
                    'source_asset_id',
                    (int) $this->getKey(),
                )
                ->setCustomProperty(
                    'source_asset_title',
                    $this->title,
                )
                ->save();

            $this
                ->forceFill([
                    'product_id' => $product->getKey(),

                    'usage' => $usage,

                    'status' => self::STATUS_ASSIGNED,

                    'alt_text' => $resolvedAlt,
                ])
                ->save();
        } catch (\Throwable $exception) {
            $copiedMedia->delete();

            throw $exception;
        }

        return $copiedMedia;
    }

    public function sourceMedia(): ?Media
    {
        return $this->getFirstMedia(
            'source'
        );
    }

    public function previewUrl(): ?string
    {
        $media = $this->sourceMedia();

        if ($media === null) {
            return null;
        }

        return $media->hasGeneratedConversion(
            'thumb'
        )
            ? $media->getFullUrl('thumb')
            : $media->getFullUrl();
    }
}
