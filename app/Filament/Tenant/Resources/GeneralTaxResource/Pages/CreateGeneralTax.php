<?php

namespace App\Filament\Tenant\Resources\GeneralTaxResource\Pages;

use App\Filament\Tenant\Resources\GeneralTaxResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGeneralTax extends CreateRecord
{
    protected static string $resource = GeneralTaxResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        return $data;
    }
}
