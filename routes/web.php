<?php

use App\Http\Controllers\GoogleAuthController;
use App\Models\ShortUrl;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));
Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:30,1')
    ->name('auth.google.callback');
Route::get('/auth/google/link', [GoogleAuthController::class, 'link'])
    ->middleware(['auth:customer', 'customer.active', 'throttle:10,1'])
    ->name('auth.google.link');

Route::get('/sitemap.xml', fn () => redirect()->away(
    'https://winimibakery.com/sitemap.xml',
    301,
))->name('canonical-sitemap');

// Short URL redirect
Route::get('/s/{code}', function (string $code) {
    $shortUrl = ShortUrl::where('code', $code)->first();
    if (! $shortUrl || ! $shortUrl->isActive()) {
        abort(404);
    }
    $shortUrl->incrementClicks();

    return redirect($shortUrl->destination_url, 301);
});
