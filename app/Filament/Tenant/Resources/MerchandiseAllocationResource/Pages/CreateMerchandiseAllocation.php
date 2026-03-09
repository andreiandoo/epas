<?php

namespace App\Filament\Tenant\Resources\MerchandiseAllocationResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseAllocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchandiseAllocation extends CreateRecord
{
    protected static string $resource = MerchandiseAllocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
