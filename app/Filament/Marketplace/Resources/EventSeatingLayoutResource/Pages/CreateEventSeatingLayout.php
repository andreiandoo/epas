<?php

namespace App\Filament\Marketplace\Resources\EventSeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\EventSeatingLayoutResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Resources\Pages\CreateRecord;

class CreateEventSeatingLayout extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = EventSeatingLayoutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set marketplace_client_id from current context
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to edit after creation
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
