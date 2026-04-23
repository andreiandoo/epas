<?php

namespace App\Filament\Marketplace\Resources\VenueResource\Pages;

use App\Filament\Marketplace\Resources\VenueResource;
use Filament\Resources\Pages\EditRecord;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        // No delete action for marketplace users - they cannot delete venues
        return [];
    }

    protected function getRedirectUrl(): string
    {
        // Stay on the edit page after save instead of bouncing back to the list.
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
