<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Cache;

class Product extends Model implements HasMedia
{
    use HasSlug, SoftDeletes, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'name', 'model', 'slug', 'category_id', 'subcategory_id', 'brand_id',
        'country', 'description', 'long_description', 'excerpt',
        'specs', 'usage', 'applications', 'gallery', 'image', 'price_range',
        'in_stock', 'is_featured', 'status',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image', 'schema_type',
        'view_count', 'rfq_count', 'sort_order',
    ];

    protected $casts = [
        'specs'        => 'array',
        'usage'        => 'array',
        'applications' => 'array',
        'gallery'      => 'array',
        'in_stock'     => 'boolean',
        'is_featured'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $model) {
            Cache::forget("product.{$model->slug}");
            Cache::forget("products.featured");
            Cache::tags(["products"])->flush();
        });
        static::deleted(function (self $model) {
            Cache::forget("product.{$model->slug}");
            Cache::forget("products.featured");
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'model', 'status', 'in_stock', 'is_featured'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "محصول {$this->name} {$eventName} شد");
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main-image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('og-image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name', 'model'])
            ->saveSlugsTo('slug');
    }

    public function category(): BelongsTo    { return $this->belongsTo(Category::class); }
    public function subcategory(): BelongsTo { return $this->belongsTo(Subcategory::class); }
    public function brand(): BelongsTo       { return $this->belongsTo(Brand::class); }
    public function rfqItems(): HasMany      { return $this->hasMany(RfqItem::class); }

    public function scopePublished(Builder $q): Builder { return $q->where('status', 'published'); }
    public function scopeByCategory(Builder $q, string $slug): Builder { return $q->whereHas('category', fn($x) => $x->where('slug', $slug)); }
    public function scopeByBrand(Builder $q, string $slug): Builder    { return $q->whereHas('brand', fn($x) => $x->where('slug', $slug)); }
    public function scopeByType(Builder $q, string $slug): Builder     { return $q->whereHas('subcategory', fn($x) => $x->where('slug', $slug)); }

    public function scopeFiltered(Builder $q, array $f): Builder
    {
        if (!empty($f['category']))    $q->byCategory($f['category']);
        if (!empty($f['brand']))       $q->byBrand($f['brand']);
        if (!empty($f['type']))        $q->byType($f['type']);
        if (!empty($f['country']))     $q->where('country', $f['country']);
        if (!empty($f['price_range'])) $q->where('price_range', $f['price_range']);
        if (isset($f['in_stock']))     $q->where('in_stock', filter_var($f['in_stock'], FILTER_VALIDATE_BOOL));
        if (!empty($f['usage']))       $q->whereJsonContains('usage', $f['usage']);
        if (!empty($f['search'])) {
            $s = $f['search'];
            $q->where(fn($x) => $x->where('name', 'like', "%{$s}%")
                ->orWhere('model', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%"));
        }
        return $q;
    }

    public function getProductSchemaAttribute(): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $this->name,
            'model'       => $this->model,
            'description' => $this->description,
            'brand'       => ['@type' => 'Brand', 'name' => $this->brand?->name],
            'offers'      => [
                '@type'         => 'Offer',
                'availability'  => $this->in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'priceCurrency' => 'IRR',
                'seller'        => ['@type' => 'Organization', 'name' => 'ToolMaster'],
            ],
        ];
    }
}
