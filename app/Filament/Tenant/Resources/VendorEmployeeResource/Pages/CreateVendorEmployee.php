<?php

namespace App\Filament\Tenant\Resources\VendorEmployeeResource\Pages;

use App\Filament\Tenant\Resources\VendorEmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorEmployee extends CreateRecord
{
    protected static string $resource = VendorEmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
