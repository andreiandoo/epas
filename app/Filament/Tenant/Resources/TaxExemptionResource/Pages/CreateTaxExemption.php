<?php

namespace App\Filament\Tenant\Resources\TaxExemptionResource\Pages;

use App\Filament\Tenant\Resources\TaxExemptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxExemption extends CreateRecord
{
    protected static string $resource = TaxExemptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set exemptable_type based on exemption_type
        $data['exemptable_type'] = match ($data['exemption_type'] ?? null) {
            'customer' => 'App\\Models\\Customer',
            'ticket_type' => 'App\\Models\\TicketType',
            'event' => 'App\\Models\\Event',
            'product' => 'App\\Models\\Product',
            'category' => 'App\\Models\\Category',
            default => null,
        };

        return $data;
    }
}
