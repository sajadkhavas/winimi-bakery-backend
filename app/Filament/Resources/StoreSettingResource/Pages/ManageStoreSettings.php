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
            'home' => Tab::make('صفحه اصلی')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->where('group', 'home'),
            ),
            'brand-contact' => Tab::make('برند و تماس')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', ['brand', 'contact', 'social']),
            ),
            'header' => Tab::make('هدر')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', ['navigation', 'header']),
            ),
            'footer' => Tab::make('فوتر')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->where('group', 'footer'),
            ),
            'public-pages' => Tab::make('صفحات عمومی')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', [
                    'blog_index',
                    'catalog',
                    'contact_page',
                    'faq_page',
                    'gift',
                    'corporate',
                    'gallery_page',
                    'locations_page',
                    'reviews_page',
                    'category_guide',
                    'managed_page_shell',
                ]),
            ),
            'commerce' => Tab::make('فروش، سفارش و ارسال')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', [
                    'pricing',
                    'checkout',
                    'delivery',
                    'orders',
                ]),
            ),
            'trust-seo' => Tab::make('اعتماد و سئو')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', ['trust', 'seo']),
            ),
            'pwa-launch' => Tab::make('PWA و یکپارچه‌سازی')->modifyQueryUsing(
                fn (Builder $query): Builder => $query->whereIn('group', [
                    'pwa',
                    'app_ui',
                    'integrations',
                    'consent',
                ]),
            ),
        ];
    }
}
