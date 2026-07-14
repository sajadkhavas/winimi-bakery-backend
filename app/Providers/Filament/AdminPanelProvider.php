<?php
namespace App\Providers\Filament;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use Tapp\FilamentAuthenticationLog\FilamentAuthenticationLogPlugin;
use Awcodes\Curator\CuratorPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch->locales(['fa', 'en']);
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('ShineThree')
            ->brandLogo(asset('images/shinethree-logo.svg'))
            ->brandLogoHeight('48px')
            ->favicon(asset('favicon.ico'))
            ->colors(['primary' => Color::Purple])
            ->font('Vazirmatn')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Pages\Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                FilamentSpatieLaravelBackupPlugin::make(),
                FilamentAuthenticationLogPlugin::make(),
                CuratorPlugin::make()
                    ->label('رسانه')
                    ->pluralLabel('مدیریت رسانه')
                    ->navigationIcon('heroicon-o-photo')
                    ->navigationGroup('محتوا')
                    ->navigationSort(5)
                    ->resource(\Awcodes\Curator\Resources\MediaResource::class),
            ])
            ->authMiddleware([Authenticate::class])
            ->navigationGroups(['محتوا', 'فروش', 'تنظیمات', 'سیستم'])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarCollapsibleOnDesktop()
            ->viteTheme('resources/css/filament/admin/theme.css');
            //->spa();
    }
}
