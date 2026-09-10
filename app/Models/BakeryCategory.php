<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class BakeryCategory extends Model
{
    use HasSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'image_alt',
        'meta_title',
        'meta_description',
        'is_active',
        'show_on_home',
        'show_in_footer',
        'home_sort_order',
        'footer_sort_order',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_on_home' => 'boolean',
        'show_in_footer' => 'boolean',
        'home_sort_order' => 'integer',
        'footer_sort_order' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $category->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function products(): HasMany
    {
        return $this->hasMany(BakeryProduct::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
