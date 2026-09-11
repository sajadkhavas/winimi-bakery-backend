<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class BakeryMediaAsset extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    public const MAX_PUBLIC_PREVIEW_BYTES = 1_048_576;

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

    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            if (
                $asset->isDirty('status')
                && in_array($asset->status, [self::STATUS_READY, self::STATUS_ASSIGNED], true)
                && ! $asset->publicPreviewWithinBudget()
            ) {
                throw new \DomainException(
                    'نسخه WebP مصرفی باید آماده و حداکثر ۱ مگابایت باشد؛ ابتدا پردازش/بازسازی رسانه را کامل کنید.'
                );
            }
        });
    }

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

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('source')
            ->fit(Fit::Crop, 240, 240)
            ->format('webp')
            ->quality(78);

        $this->addMediaConversion('preview')
            ->performOnCollections('source')
            ->fit(Fit::Max, 1200, 1200)
            ->format('webp')
            ->quality(80);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(BakeryProduct::class, 'product_id');
    }

    public function assignToProduct(
        BakeryProduct $product,
        string $usage,
        ?string $altText = null,
    ): Media {
        if ($this->status !== self::STATUS_READY) {
            throw new \DomainException('این رسانه باید ابتدا در وضعیت آماده تخصیص قرار بگیرد.');
        }

        if (! $this->publicPreviewWithinBudget()) {
            throw new \DomainException('نسخه بهینه رسانه آماده نیست یا از سقف ۱ مگابایت بیشتر است.');
        }

        if (! in_array($usage, [self::USAGE_PRODUCT_MAIN, self::USAGE_PRODUCT_GALLERY], true)) {
            throw new \InvalidArgumentException('کاربرد انتخاب‌شده برای رسانه محصول معتبر نیست.');
        }

        $this->unsetRelation('media');
        $product->unsetRelation('media');

        $source = $this->sourceMedia();

        if (! $source instanceof Media) {
            throw new \DomainException('فایل اصلی این رسانه پیدا نشد.');
        }

        $collection = match ($usage) {
            self::USAGE_PRODUCT_MAIN => 'catalog-main',
            self::USAGE_PRODUCT_GALLERY => 'catalog-gallery',
        };

        if ($collection === 'catalog-main' && $product->getFirstMedia('catalog-main') instanceof Media) {
            throw new \DomainException('این محصول از قبل تصویر اصلی دارد؛ جایگزینی خودکار انجام نشد.');
        }

        $resolvedAlt = trim((string) ($altText ?? $this->alt_text ?? $this->title));

        if ($resolvedAlt === '') {
            $resolvedAlt = $product->name;
        }

        $copiedMedia = $source->copy($product, $collection);

        try {
            $copiedMedia
                ->setCustomProperty('alt', $resolvedAlt)
                ->setCustomProperty('source_asset_id', (int) $this->getKey())
                ->setCustomProperty('source_asset_title', $this->title)
                ->save();

            $this
                ->forceFill([
                    'product_id' => $product->getKey(),
                    'usage' => $usage,
                    'status' => self::STATUS_ASSIGNED,
                    'alt_text' => $resolvedAlt,
                ])
                ->save();
        } catch (Throwable $exception) {
            $copiedMedia->delete();

            throw $exception;
        }

        return $copiedMedia;
    }

    public function sourceMedia(): ?Media
    {
        return $this->getFirstMedia('source');
    }

    public function conversionsReady(): bool
    {
        try {
            $media = $this->sourceMedia();

            return $media !== null
                && $media->hasGeneratedConversion('thumb')
                && $media->hasGeneratedConversion('preview');
        } catch (Throwable) {
            return false;
        }
    }

    public function publicPreviewBytes(): ?int
    {
        try {
            $media = $this->sourceMedia();

            if ($media === null || ! $media->hasGeneratedConversion('preview')) {
                return null;
            }

            $path = $media->getPath('preview');

            if (! is_file($path)) {
                return null;
            }

            $bytes = filesize($path);

            return $bytes === false ? null : $bytes;
        } catch (Throwable) {
            return null;
        }
    }

    public function publicPreviewWithinBudget(): bool
    {
        $bytes = $this->publicPreviewBytes();

        return $this->conversionsReady()
            && $bytes !== null
            && $bytes <= self::MAX_PUBLIC_PREVIEW_BYTES;
    }

    public function conversionState(): string
    {
        try {
            $media = $this->sourceMedia();

            if ($media === null) {
                return 'missing';
            }

            if (! $this->conversionsReady()) {
                return 'pending';
            }

            $bytes = $this->publicPreviewBytes();
            if ($bytes === null) {
                return 'broken';
            }

            return $bytes <= self::MAX_PUBLIC_PREVIEW_BYTES ? 'ready' : 'oversized';
        } catch (Throwable) {
            return 'broken';
        }
    }

    public function originalUrl(): ?string
    {
        try {
            return $this->sourceMedia()?->getFullUrl();
        } catch (Throwable) {
            return null;
        }
    }

    public function optimizedUrl(): ?string
    {
        try {
            $media = $this->sourceMedia();

            return $media !== null && $media->hasGeneratedConversion('preview')
                ? $media->getFullUrl('preview')
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function originalSizeLabel(): string
    {
        try {
            return self::humanBytes($this->sourceMedia()?->size);
        } catch (Throwable) {
            return '—';
        }
    }

    public function optimizedSizeLabel(): string
    {
        return self::humanBytes($this->publicPreviewBytes());
    }

    public function dimensionsLabel(): string
    {
        try {
            $media = $this->sourceMedia();
            if ($media === null) {
                return '—';
            }

            $path = $media->getPath();
            if (! is_file($path)) {
                return 'نامشخص';
            }

            $size = @getimagesize($path);
            if (! is_array($size) || ! isset($size[0], $size[1])) {
                return 'نامشخص';
            }

            return $size[0].'×'.$size[1].' px';
        } catch (Throwable) {
            return 'نامشخص';
        }
    }

    public function formatLabel(): string
    {
        try {
            return $this->sourceMedia()?->mime_type ?? '—';
        } catch (Throwable) {
            return '—';
        }
    }

    public function previewUrl(): ?string
    {
        try {
            $media = $this->sourceMedia();

            if ($media === null) {
                return null;
            }

            return $media->hasGeneratedConversion('thumb')
                ? $media->getFullUrl('thumb')
                : $media->getFullUrl();
        } catch (Throwable) {
            return null;
        }
    }

    private static function humanBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 2).' MB';
    }
}
