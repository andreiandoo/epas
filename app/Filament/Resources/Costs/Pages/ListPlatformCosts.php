<?php

namespace App\Filament\Resources\Costs\Pages;

use App\Filament\Resources\Costs\PlatformCostResource;
use Filament\Resources\Pages\ListRecords;

class ListPlatformCosts extends ListRecords
{
    protected static string $resource = PlatformCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
