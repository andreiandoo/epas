<?php

namespace App\Filament\Resources\MarketplaceClientResource\Pages;

use App\Filament\Resources\MarketplaceClientResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateMarketplaceClient extends CreateRecord
{
    protected static string $resource = MarketplaceClientResource::class;

    protected function afterCreate(): void
    {
        // Show the API credentials after creation
        Notification::make()
            ->title('Marketplace Client Created')
            ->body("API Key: {$this->record->api_key}\n\nSave this key securely - it cannot be retrieved later.")
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
