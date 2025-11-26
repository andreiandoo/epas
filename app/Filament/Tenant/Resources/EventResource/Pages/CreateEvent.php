<?php

namespace App\Filament\Tenant\Resources\EventResource\Pages;

use App\Filament\Tenant\Resources\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = auth()->user()->tenant;

        $data['tenant_id'] = $tenant?->id;

        // Auto-fill ticket_terms from tenant settings if empty
        if (empty($data['ticket_terms']) && $tenant?->ticket_terms) {
            $data['ticket_terms'] = $tenant->ticket_terms;
        }

        return $data;
    }
}
