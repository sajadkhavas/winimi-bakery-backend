<?php

namespace App\Filament\Resources\WebPushSubscriptionResource\Pages;

use App\Filament\Resources\WebPushSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListWebPushSubscriptions extends ListRecords
{
    protected static string $resource = WebPushSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
