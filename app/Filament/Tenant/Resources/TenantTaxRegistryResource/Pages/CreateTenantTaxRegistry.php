<?php

namespace App\Filament\Tenant\Resources\TenantTaxRegistryResource\Pages;

use App\Filament\Tenant\Resources\TenantTaxRegistryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantTaxRegistry extends CreateRecord
{
    protected static string $resource = TenantTaxRegistryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant?->id;
        return $data;
    }
}
