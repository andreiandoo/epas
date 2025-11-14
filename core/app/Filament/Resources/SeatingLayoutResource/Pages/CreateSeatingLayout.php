<?php

namespace App\Filament\Resources\SeatingLayoutResource\Pages;

use App\Filament\Resources\SeatingLayoutResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeatingLayout extends CreateRecord
{
    protected static string $resource = SeatingLayoutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set from current context
        $data['tenant_id'] = auth()->user()?->tenant_id
            ?? session('tenant_id')
            ?? request()->input('tenant_id')
            ?? 1; // Default to tenant_id = 1 for development

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to designer after creation
        return $this->getResource()::getUrl('designer', ['record' => $this->getRecord()]);
    }
}
