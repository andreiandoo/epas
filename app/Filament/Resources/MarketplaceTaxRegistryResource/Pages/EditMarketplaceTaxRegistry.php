<?php

namespace App\Filament\Resources\MarketplaceTaxRegistryResource\Pages;

use App\Filament\Resources\MarketplaceTaxRegistryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceTaxRegistry extends EditRecord
{
    protected static string $resource = MarketplaceTaxRegistryResource::class;

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
