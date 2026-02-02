<?php

namespace App\Filament\Resources\MarketplaceClientResource\Pages;

use App\Filament\Resources\MarketplaceClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceClients extends ListRecords
{
    protected static string $resource = MarketplaceClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
