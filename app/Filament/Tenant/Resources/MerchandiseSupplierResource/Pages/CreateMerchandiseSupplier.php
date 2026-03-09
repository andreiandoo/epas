<?php

namespace App\Filament\Tenant\Resources\MerchandiseSupplierResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseSupplierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchandiseSupplier extends CreateRecord
{
    protected static string $resource = MerchandiseSupplierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
