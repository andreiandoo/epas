<?php

namespace App\Filament\Marketplace\Resources\MarketplacePartnerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplacePartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplacePartners extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = MarketplacePartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
