<?php

namespace App\Filament\Marketplace\Resources\MarketplacePartnerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplacePartnerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplacePartner extends CreateRecord
{
    protected static string $resource = MarketplacePartnerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = MarketplacePartnerResource::getMarketplaceClient()?->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        MarketplacePartnerResource::notifyNewKey($this->record, $this->record->regenerateApiKey());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
