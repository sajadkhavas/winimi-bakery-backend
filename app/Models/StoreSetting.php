<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StoreSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'type',
        'value',
        'label',
        'is_public',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget('winimi.store_settings'));
        static::deleted(fn (): bool => Cache::forget('winimi.store_settings'));
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        $settings = Cache::rememberForever(
            'winimi.store_settings',
            fn (): array => static::query()->get()->mapWithKeys(
                fn (self $setting): array => [$setting->key => $setting->typedValue()],
            )->all(),
        );

        return $settings[$key] ?? $default;
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $this->value,
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
