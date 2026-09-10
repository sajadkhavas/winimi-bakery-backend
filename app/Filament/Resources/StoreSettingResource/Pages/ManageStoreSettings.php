<?php

namespace App\Filament\Resources\StoreSettingResource\Pages;

use App\Filament\Resources\StoreSettingResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageStoreSettings extends ManageRecords
{
    protected static string $resource = StoreSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('همه'),
            'brand-contact' => Tab::make('برند و تماس')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', ['brand', 'contact', 'social']),
            ),
            'header-footer' => Tab::make('هدر و فوتر')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', ['navigation', 'header', 'footer']),
            ),
            'home' => Tab::make('صفحه اصلی')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->where('group', 'home'),
            ),
            'commerce' => Tab::make('فروش و تخفیف')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', ['pricing', 'checkout', 'delivery']),
            ),
            'trust-seo' => Tab::make('اعتماد و سئو')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', ['trust', 'seo']),
            ),
            'pwa-launch' => Tab::make('اپ و راه‌اندازی')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', ['pwa', 'integrations', 'consent']),
            ),
        ];
    }
}
