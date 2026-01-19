<?php

namespace App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceCustomers extends ListRecords
{
    protected static string $resource = MarketplaceCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
