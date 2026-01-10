<?php

namespace App\Filament\Resources\MarketplaceTaxRegistryResource\Pages;

use App\Filament\Resources\MarketplaceTaxRegistryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketplaceTaxRegistry extends ViewRecord
{
    protected static string $resource = MarketplaceTaxRegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
