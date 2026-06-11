<?php

namespace App\Filament\Resources\MarketplaceTaxTemplateResource\Pages;

use App\Filament\Resources\MarketplaceTaxTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceTaxTemplate extends EditRecord
{
    protected static string $resource = MarketplaceTaxTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
