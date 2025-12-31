<?php

namespace App\Filament\Tenant\Resources\Tracking\PersonTagResource\Pages;

use App\Filament\Tenant\Resources\Tracking\PersonTagResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonTag extends CreateRecord
{
    protected static string $resource = PersonTagResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = filament()->getTenant()->id;

        return $data;
    }
}
