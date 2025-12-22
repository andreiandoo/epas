<?php

namespace App\Filament\Resources\LocalTaxResource\Pages;

use App\Filament\Resources\LocalTaxResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocalTax extends CreateRecord
{
    protected static string $resource = LocalTaxResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        return $data;
    }
}
