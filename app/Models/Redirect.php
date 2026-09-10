<?php

namespace App\Models;

use App\Services\Store\StorefrontRedirectService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Redirect extends Model
{
    protected $fillable = [
        'from_url',
        'to_url',
        'status_code',
        'is_active',
        'hit_count',
        'note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hit_count' => 'integer',
        'status_code' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $redirect): void {
            $source = StorefrontRedirectService::normalizeSource(
                (string) $redirect->from_url,
            );
            $target = StorefrontRedirectService::normalizeTarget(
                (string) $redirect->to_url,
            );
            $statusCode = (int) $redirect->status_code;

            $errors = [];
            if ($source === null) {
                $errors['from_url'] = 'آدرس قدیمی باید یک مسیر داخلی امن مثل /old-page باشد.';
            }
            if ($target === null) {
                $errors['to_url'] = 'آدرس مقصد باید یک مسیر داخلی امن در همین سایت باشد.';
            }
            if (! StorefrontRedirectService::isAllowedStatusCode($statusCode)) {
                $errors['status_code'] = 'کد ریدایرکت باید یکی از 301، 302، 307 یا 308 باشد.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $service = app(StorefrontRedirectService::class);
            if ($service->wouldCreateLoop(
                $source,
                $target,
                $redirect->exists ? (int) $redirect->getKey() : null,
            )) {
                throw ValidationException::withMessages([
                    'to_url' => 'این مقصد باعث حلقه ریدایرکت یا زنجیره نامعتبر می‌شود.',
                ]);
            }

            $redirect->from_url = $source;
            $redirect->to_url = $target;
            $redirect->status_code = $statusCode;
        });
    }

    public static function findRedirect(string $url): ?self
    {
        $source = StorefrontRedirectService::normalizeSource($url);
        if ($source === null) {
            return null;
        }

        return static::query()
            ->where('from_url', $source)
            ->where('is_active', true)
            ->first();
    }
}
