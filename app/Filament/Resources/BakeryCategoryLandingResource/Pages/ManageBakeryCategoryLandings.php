<?php

namespace App\Filament\Resources\BakeryCategoryLandingResource\Pages;

use App\Filament\Resources\BakeryCategoryLandingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBakeryCategoryLandings extends ManageRecords
{
    protected static string $resource = BakeryCategoryLandingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
