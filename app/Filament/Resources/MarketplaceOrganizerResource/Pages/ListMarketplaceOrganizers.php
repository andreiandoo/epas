<?php

namespace App\Filament\Resources\MarketplaceOrganizerResource\Pages;

use App\Filament\Resources\MarketplaceOrganizerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceOrganizers extends ListRecords
{
    protected static string $resource = MarketplaceOrganizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
