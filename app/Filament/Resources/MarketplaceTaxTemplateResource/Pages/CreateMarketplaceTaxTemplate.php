<?php

namespace App\Filament\Resources\MarketplaceTaxTemplateResource\Pages;

use App\Filament\Resources\MarketplaceTaxTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplaceTaxTemplate extends CreateRecord
{
    protected static string $resource = MarketplaceTaxTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
