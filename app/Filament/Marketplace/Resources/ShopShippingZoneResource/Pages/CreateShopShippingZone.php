<?php

namespace App\Filament\Marketplace\Resources\ShopShippingZoneResource\Pages;

use App\Filament\Marketplace\Resources\ShopShippingZoneResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateShopShippingZone extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ShopShippingZoneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        return $data;
    }
}
