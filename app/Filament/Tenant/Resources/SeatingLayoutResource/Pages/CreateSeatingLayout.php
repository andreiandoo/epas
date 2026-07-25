<?php

namespace App\Filament\Tenant\Resources\SeatingLayoutResource\Pages;

use App\Filament\Tenant\Resources\SeatingLayoutResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeatingLayout extends CreateRecord
{
    protected static string $resource = SeatingLayoutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // After creating a layout, jump straight into the designer.
        return SeatingLayoutResource::getUrl('designer', ['record' => $this->record]);
    }
}
