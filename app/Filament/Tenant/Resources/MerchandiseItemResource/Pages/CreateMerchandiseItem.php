<?php

namespace App\Filament\Tenant\Resources\MerchandiseItemResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchandiseItem extends CreateRecord
{
    protected static string $resource = MerchandiseItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
