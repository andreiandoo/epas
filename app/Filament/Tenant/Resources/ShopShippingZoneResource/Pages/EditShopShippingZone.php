<?php

namespace App\Filament\Tenant\Resources\ShopShippingZoneResource\Pages;

use App\Filament\Tenant\Resources\ShopShippingZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopShippingZone extends EditRecord
{
    protected static string $resource = ShopShippingZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
