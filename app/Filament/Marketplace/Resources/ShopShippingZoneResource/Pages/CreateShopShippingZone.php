<?php

namespace App\Filament\Marketplace\Resources\ShopShippingZoneResource\Pages;

use App\Filament\Marketplace\Resources\ShopShippingZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShopShippingZone extends CreateRecord
{
    protected static string $resource = ShopShippingZoneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        return $data;
    }
}
