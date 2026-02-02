<?php

namespace App\Filament\Resources\MarketplaceUserResource\Pages;

use App\Filament\Resources\MarketplaceUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceUsers extends ListRecords
{
    protected static string $resource = MarketplaceUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
