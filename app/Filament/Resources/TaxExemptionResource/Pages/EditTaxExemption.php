<?php

namespace App\Filament\Resources\TaxExemptionResource\Pages;

use App\Filament\Resources\TaxExemptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxExemption extends EditRecord
{
    protected static string $resource = TaxExemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Update exemptable_type based on exemption_type
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
