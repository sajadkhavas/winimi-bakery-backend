<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class NavigationItem extends Model
{
    protected $fillable = [
        'label',
        'href',
        'parent_id',
        'linked_category_id',
        'placement',
        'sort_order',
        'is_active',
        'open_in_new_tab',
        'hide_when_empty',
        'icon',
        'description',
        'image_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'open_in_new_tab' => 'boolean',
        'hide_when_empty' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->parent_id && self::query()->whereKey($item->parent_id)->whereNotNull('parent_id')->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'منوی عمومی حداکثر دو سطح دارد.',
                ]);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function linkedCategory(): BelongsTo
    {
        return $this->belongsTo(BakeryCategory::class, 'linked_category_id');
    }
}
