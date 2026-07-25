<?php

namespace App\Filament\Tenant\Resources\EventCategoryResource\Pages;

use App\Filament\Tenant\Resources\EventCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventCategory extends CreateRecord
{
    protected static string $resource = EventCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant?->id;
        return $data;
    }
}
