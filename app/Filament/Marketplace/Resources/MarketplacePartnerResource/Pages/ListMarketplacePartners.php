<?php

namespace App\Filament\Marketplace\Resources\MarketplacePartnerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplacePartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplacePartners extends ListRecords
{
    protected static string $resource = MarketplacePartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
