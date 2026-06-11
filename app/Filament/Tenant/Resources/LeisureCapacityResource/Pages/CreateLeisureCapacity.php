<?php

namespace App\Filament\Tenant\Resources\LeisureCapacityResource\Pages;

use App\Filament\Tenant\Resources\LeisureCapacityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeisureCapacity extends CreateRecord
{
    protected static string $resource = LeisureCapacityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant?->id;
        return $data;
    }
}
