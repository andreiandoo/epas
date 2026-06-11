<?php

namespace App\Filament\Marketplace\Resources\ServiceTypeResource\Pages;

use App\Filament\Marketplace\Resources\ServiceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceType extends EditRecord
{
    protected static string $resource = ServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No delete action - service types should not be deleted
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure the marketplace_client_id is preserved
        $data['marketplace_client_id'] = $this->record->marketplace_client_id;

        // Merge pricing with existing record so legacy keys not present in form aren't dropped.
        if (isset($data['pricing']) && is_array($data['pricing'])) {
            $existing = $this->record->pricing ?? [];
            $data['pricing'] = array_merge($existing, $data['pricing']);
        }

        return $data;
    }
}
