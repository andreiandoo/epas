<?php

namespace App\Filament\Tenant\Resources\ShopShippingZoneResource\Pages;

use App\Filament\Tenant\Resources\ShopShippingZoneResource;
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
