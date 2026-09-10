<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueMonitorPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'پایش صف';

    protected static ?string $navigationGroup = 'سیستم و امنیت';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'پایش صف';

    protected static string $view = 'filament.pages.queue-monitor';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getStats(): array
    {
        $this->authorizeSensitiveAction();
        $failed = 0;
        $pending = 0;

        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (\Exception) {
        }

        try {
            $pending = DB::table('jobs')->count();
        } catch (\Exception) {
        }

        return [
            'driver' => config('queue.default'),
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    public function getFailedJobs(): array
    {
        $this->authorizeSensitiveAction();

        try {
            return DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(20)
                ->get()
                ->map(fn ($job) => [
                    'id' => $job->id,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'payload' => json_decode($job->payload, true)['displayName'] ?? 'نامشخص',
                    'exception' => substr($job->exception, 0, 100).'...',
                    'failed_at' => $job->failed_at,
                ])
                ->toArray();
        } catch (\Exception) {
            return [];
        }
    }

    public function retryAll(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('queue:retry all');
        Notification::make()->title('همه وظیفه‌های ناموفق دوباره اجرا شدند')->success()->send();
    }

    public function flushFailed(): void
    {
        $this->authorizeSensitiveAction();
        Artisan::call('queue:flush');
        Notification::make()->title('همه وظیفه‌های ناموفق پاک شدند')->success()->send();
    }

    public function getViewData(): array
    {
        $this->authorizeSensitiveAction();

        return [
            'stats' => $this->getStats(),
            'failedJobs' => $this->getFailedJobs(),
        ];
    }

    private function authorizeSensitiveAction(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
