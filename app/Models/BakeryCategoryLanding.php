<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BakeryCategoryLanding extends Model
{
    protected $fillable = [
        'public_slug',
        'catalog_category_slug',
        'catalog_search',
        'name',
        'eyebrow',
        'card_description',
        'meta_title',
        'meta_description',
        'heading',
        'intro',
        'sections',
        'faq',
        'guides',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'faq' => 'array',
            'guides' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $landing): void {
            $landing->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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
