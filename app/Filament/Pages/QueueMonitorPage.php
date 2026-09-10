<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueMonitorPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'مانیتور صف';

    protected static ?string $navigationGroup = 'سیستم و امنیت';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'مانیتور صف';

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
        try {
            return DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(20)
                ->get()
                ->map(fn ($job) => [
                    'id' => $job->id,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'payload' => json_decode($job->payload, true)['displayName'] ?? 'Unknown',
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
        Artisan::call('queue:retry all');
        Notification::make()->title('همه job های failed مجدداً اجرا شدند')->success()->send();
    }

    public function flushFailed(): void
    {
        Artisan::call('queue:flush');
        Notification::make()->title('همه failed jobs پاک شدند')->success()->send();
    }

    public function getViewData(): array
    {
        return [
            'stats' => $this->getStats(),
            'failedJobs' => $this->getFailedJobs(),
        ];
    }
}
