<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = auth()->user()->tenant;

        $data['tenant_id'] = $tenant?->id;

        // Auto-fill ticket_terms from tenant settings if empty
        // The form uses translatable format: ticket_terms.{language}
        // If ticket_terms array is empty but tenant has default terms, populate it
        if ($tenant?->ticket_terms) {
            $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';
            if (empty($data['ticket_terms'][$tenantLanguage])) {
                $data['ticket_terms'][$tenantLanguage] = $tenant->ticket_terms;
            }
        }

        return $data;
    }
}
