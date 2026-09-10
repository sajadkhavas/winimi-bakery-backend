<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class CacheManagerPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'مدیریت کش';

    protected static ?string $navigationGroup = 'سیستم و امنیت';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'مدیریت کش';

    protected static string $view = 'filament.pages.cache-manager';

    public array $results = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function clearAll(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('optimize:clear');
        $this->results[] = ['type' => 'success', 'msg' => 'همه کش‌ها پاک شدند', 'time' => now()->format('H:i:s')];
        Notification::make()->title('همه کش‌ها پاک شدند')->success()->send();
    }

    public function clearCache(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('cache:clear');
        $this->results[] = ['type' => 'success', 'msg' => 'کش برنامه پاک شد', 'time' => now()->format('H:i:s')];
        Notification::make()->title('کش برنامه پاک شد')->success()->send();
    }

    public function clearConfig(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('config:clear');
        $this->results[] = ['type' => 'success', 'msg' => 'کش تنظیمات پاک شد', 'time' => now()->format('H:i:s')];
        Notification::make()->title('کش تنظیمات پاک شد')->success()->send();
    }

    public function clearRoutes(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('route:clear');
        $this->results[] = ['type' => 'success', 'msg' => 'کش مسیرها پاک شد', 'time' => now()->format('H:i:s')];
        Notification::make()->title('کش مسیرها پاک شد')->success()->send();
    }

    public function clearViews(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('view:clear');
        $this->results[] = ['type' => 'success', 'msg' => 'کش نماها پاک شد', 'time' => now()->format('H:i:s')];
        Notification::make()->title('کش نماها پاک شد')->success()->send();
    }

    public function clearEvents(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('event:clear');
        $this->results[] = ['type' => 'success', 'msg' => 'کش رویدادها پاک شد', 'time' => now()->format('H:i:s')];
        Notification::make()->title('کش رویدادها پاک شد')->success()->send();
    }

    public function cacheConfig(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('config:cache');
        Notification::make()->title('کش تنظیمات ساخته شد')->success()->send();
        $this->redirect(request()->header('Referer') ?? static::getUrl());
    }

    public function cacheRoutes(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('route:cache');
        Notification::make()->title('کش مسیرها ساخته شد')->success()->send();
        $this->redirect(request()->header('Referer') ?? static::getUrl());
    }

    public function cacheViews(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('view:cache');
        $this->results[] = ['type' => 'info', 'msg' => 'کش نماها ساخته شد', 'time' => now()->format('H:i:s')];
        Notification::make()->title('کش نماها ساخته شد')->success()->send();
    }

    public function clearResults(): void
    {
        $this->authorizeSensitiveAction();
        $this->results = [];
    }

    public function getStats(): array
    {
        $this->authorizeSensitiveAction();

        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'env' => app()->environment(),
            'cache' => config('cache.default'),
            'disk_free' => round(disk_free_space(base_path()) / 1024 / 1024 / 1024, 2).' GB',
            'disk_total' => round(disk_total_space(base_path()) / 1024 / 1024 / 1024, 2).' GB',
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 1).' MB',
        ];
    }

    public function getViewData(): array
    {
        $this->authorizeSensitiveAction();

        return ['stats' => $this->getStats()];
    }

    private function authorizeSensitiveAction(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
