<?php

namespace App\Filament\Resources\MarketplaceTaxRegistryResource\Pages;

use App\Filament\Resources\MarketplaceTaxRegistryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplaceTaxRegistry extends CreateRecord
{
    protected static string $resource = MarketplaceTaxRegistryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
