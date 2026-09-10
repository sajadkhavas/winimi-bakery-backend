<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BakeryContentPage extends Model
{
    protected $fillable = [
        'type',
        'slug',
        'title',
        'excerpt',
        'cover_url',
        'content',
        'meta_title',
        'meta_description',
        'status',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            $page->public_id ??= (string) Str::ulid();
        });

        static::saving(function (self $page): void {
            if ($page->status === 'published' && $page->published_at === null) {
                $page->published_at = now();
            }

            if ($page->status !== 'published') {
                $page->published_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
