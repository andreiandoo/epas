<?php

namespace App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceVenueCategories extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = MarketplaceVenueCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
