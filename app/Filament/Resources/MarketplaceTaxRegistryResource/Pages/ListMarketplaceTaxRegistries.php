<?php

namespace App\Filament\Resources\MarketplaceTaxRegistryResource\Pages;

use App\Filament\Resources\MarketplaceTaxRegistryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceTaxRegistries extends ListRecords
{
    protected static string $resource = MarketplaceTaxRegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
