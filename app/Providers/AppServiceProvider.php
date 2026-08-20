<?php

namespace App\Providers;

use App\Models\BakeryProduct;
use App\Models\Product;
use App\Observers\ProductObserver;
use App\Support\IranianMobile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $phase18 = config('phase18', []);
        if (is_array($phase18) && filled($phase18['roadmap_version'] ?? null)) {
            config([
                'winimi.launch.roadmap_version' => $phase18['roadmap_version'],
                'winimi.launch.internal_gates.frontend_integrated' => $phase18['internal_gates']['frontend_integrated'],
                'winimi.launch.internal_gates.end_to_end_verified' => $phase18['internal_gates']['end_to_end_verified'],
                'winimi.launch.internal_gates.production_deployed' => $phase18['internal_gates']['production_deployed'],
            ]);
        }

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Product::observe(ProductObserver::class);

        Event::listen(
            MediaHasBeenAddedEvent::class,
            static function (MediaHasBeenAddedEvent $event): void {
                $media = $event->media;
                $model = $media->model;

                if (! $model instanceof BakeryProduct) {
                    return;
                }

                $dimensions = @getimagesize(
                    $media->getPath()
                );

                if (is_array($dimensions)) {
                    $media->setCustomProperty(
                        'width',
                        (int) $dimensions[0],
                    );

                    $media->setCustomProperty(
                        'height',
                        (int) $dimensions[1],
                    );

                    if (
                        isset($dimensions['mime'])
                        && is_string($dimensions['mime'])
                    ) {
                        $media->setCustomProperty(
                            'detected_mime',
                            $dimensions['mime'],
                        );
                    }

                    $media->save();
                }

                if ($model->media_verified) {
                    $model
                        ->forceFill([
                            'media_verified' => false,
                        ])
                        ->save();
                }
            },
        );

        Media::deleting(
            static function (Media $media): void {
                $bakeryProductMorphClass = (
                    new BakeryProduct
                )->getMorphClass();

                if (
                    (string) $media->model_type
                    !== $bakeryProductMorphClass
                ) {
                    return;
                }

                $model = BakeryProduct::query()->find(
                    $media->model_id
                );

                if (
                    ! $model instanceof BakeryProduct
                    || ! $model->media_verified
                ) {
                    return;
                }

                $model
                    ->forceFill([
                        'media_verified' => false,
                    ])
                    ->save();
            },
        );

        RateLimiter::for('otp-request', function (Request $request): array {
            try {
                $mobileKey = IranianMobile::hash((string) $request->input('mobile'));
            } catch (Throwable) {
                $mobileKey = hash('sha256', (string) $request->input('mobile'));
            }

            return [
                Limit::perMinute(5)->by('otp-request-ip:'.($request->ip() ?? 'unknown')),
                Limit::perMinute(2)->by('otp-request-mobile:'.$mobileKey),
            ];
        });

        RateLimiter::for('otp-verify', static function (Request $request): array {
            return [
                Limit::perMinute(15)->by('otp-verify-ip:'.($request->ip() ?? 'unknown')),
                Limit::perMinute(8)->by('otp-verify-challenge:'.(string) $request->input('challengeId')),
            ];
        });
    }
}
