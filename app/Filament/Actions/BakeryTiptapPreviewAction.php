<?php

namespace App\Filament\Actions;

use App\Support\ManagedHtmlSanitizer;
use Filament\Forms\Components\Actions\Action;

final class BakeryTiptapPreviewAction
{
    public static function make(): Action
    {
        return Action::make('bakery_tiptap_preview')
            ->label('پیش‌نمایش')
            ->icon('heroicon-m-eye')
            ->color('gray')
            ->modalHeading('پیش‌نمایش محتوای فعلی')
            ->modalDescription('این پیش‌نمایش از متن فعلی و ذخیره‌نشده ساخته می‌شود و چیزی را در پایگاه داده تغییر نمی‌دهد.')
            ->modalContent(static function ($state) {
                $html = ManagedHtmlSanitizer::sanitize(is_string($state) ? $state : '') ?? '';

                return view('filament.components.bakery-tiptap-preview', ['html' => $html]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('بستن');
    }
}
