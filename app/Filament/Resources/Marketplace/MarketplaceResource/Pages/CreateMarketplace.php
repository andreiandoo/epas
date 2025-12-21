<?php

namespace App\Filament\Resources\Marketplace\MarketplaceResource\Pages;

use App\Filament\Resources\Marketplace\MarketplaceResource;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplace extends CreateRecord
{
    protected static string $resource = MarketplaceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_type is set to marketplace
        $data['tenant_type'] = Tenant::TYPE_MARKETPLACE;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
