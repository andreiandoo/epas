<?php

namespace App\Filament\Resources\Marketplace\MarketplaceResource\Pages;

use App\Filament\Resources\Marketplace\MarketplaceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplace extends EditRecord
{
    protected static string $resource = MarketplaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
