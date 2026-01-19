<?php

namespace App\Filament\Resources\MarketplaceTaxTemplateResource\Pages;

use App\Filament\Resources\MarketplaceTaxTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketplaceTaxTemplate extends ViewRecord
{
    protected static string $resource = MarketplaceTaxTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
