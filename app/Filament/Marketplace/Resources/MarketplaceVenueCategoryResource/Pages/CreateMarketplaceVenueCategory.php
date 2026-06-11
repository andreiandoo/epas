<?php

namespace App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplaceVenueCategory extends CreateRecord
{
    protected static string $resource = MarketplaceVenueCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
