<?php

namespace App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource;
use App\Filament\Marketplace\Concerns\MovesCreateButtonToTable;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceVenueCategories extends ListRecords
{
    use MovesCreateButtonToTable;

    protected static string $resource = MarketplaceVenueCategoryResource::class;
}
