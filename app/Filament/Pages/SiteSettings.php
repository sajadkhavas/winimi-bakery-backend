<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StoreSettingResource;
use Filament\Pages\Page;

/**
 * Compatibility route for the retired SiteSetting editor.
 *
 * Public storefront content is authoritative in StoreSetting and is exposed by
 * /api/store/settings. Keeping the legacy editor writable would create a second,
 * disconnected source of truth, so old bookmarks are redirected to the current
 * resource instead.
 */
class SiteSettings extends Page
{
    protected static string $view = 'filament.pages.site-settings';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->redirect(StoreSettingResource::getUrl(), navigate: true);
    }
}
