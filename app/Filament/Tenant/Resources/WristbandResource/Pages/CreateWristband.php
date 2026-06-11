<?php

namespace App\Filament\Tenant\Resources\WristbandResource\Pages;

use App\Filament\Tenant\Resources\WristbandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWristband extends CreateRecord
{
    protected static string $resource = WristbandResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
