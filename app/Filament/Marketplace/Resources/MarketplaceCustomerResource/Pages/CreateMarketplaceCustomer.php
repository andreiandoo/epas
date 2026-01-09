<?php

namespace App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketplaceCustomer extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = MarketplaceCustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = $this->getMarketplaceClient();
        $data['marketplace_client_id'] = $marketplace->id;
        $data['status'] = $data['status'] ?? 'active';

        return $data;
    }
}
