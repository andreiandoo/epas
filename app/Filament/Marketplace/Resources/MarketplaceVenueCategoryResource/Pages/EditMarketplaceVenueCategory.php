<?php

namespace App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceVenueCategory extends EditRecord
{
    protected static string $resource = MarketplaceVenueCategoryResource::class;

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
