<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BakeryMediaAssetResource;
use App\Filament\Resources\BakeryPostResource;
use App\Filament\Resources\BakeryProductResource;
use Filament\Widgets\Widget;

class QuickStartWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-start-widget';

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    public function productCreateUrl(): string
    {
        return BakeryProductResource::getUrl('create');
    }

    public function mediaLibraryUrl(): string
    {
        return BakeryMediaAssetResource::getUrl('index');
    }

    public function blogUrl(): string
    {
        return BakeryPostResource::getUrl('index');
    }
}
