<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class TwoFactorPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'سیستم و امنیت';

    protected static ?string $navigationLabel = 'ورود دومرحله‌ای';

    protected static ?string $title = 'احراز هویت دومرحله‌ای';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.two-factor';

    public bool $showQr = false;

    public bool $showRecoveryCodes = false;

    public function enableAction(): Action
    {
        return Action::make('enable')
            ->label('فعال‌کردن ورود دومرحله‌ای')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (): void {
                app(EnableTwoFactorAuthentication::class)(auth()->user());
                $this->showQr = true;
                Notification::make()
                    ->success()
                    ->title('ورود دومرحله‌ای فعال شد')
                    ->body('QR Code را با برنامه احراز هویت معتبر اسکن کنید.')
                    ->send();
            });
    }

    public function disableAction(): Action
    {
        return Action::make('disable')
            ->label('غیرفعال‌کردن ورود دومرحله‌ای')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (): void {
                app(DisableTwoFactorAuthentication::class)(auth()->user());
                $this->showQr = false;
                Notification::make()->warning()->title('ورود دومرحله‌ای غیرفعال شد')->send();
            });
    }

    public function regenerateCodesAction(): Action
    {
        return Action::make('regenerate')
            ->label('تولید کدهای بازیابی جدید')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (): void {
                app(GenerateNewRecoveryCodes::class)(auth()->user());
                $this->showRecoveryCodes = true;
                Notification::make()->success()->title('کدهای بازیابی جدید تولید شدند')->send();
            });
    }

    public function getUser()
    {
        return auth()->user();
    }
}
