<?php

namespace App\Filament\Marketplace\Resources\SeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\SeatingLayoutResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Resources\Pages\CreateRecord;

class CreateSeatingLayout extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = SeatingLayoutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set marketplace_client_id from current context
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to designer after creation
        return $this->getResource()::getUrl('designer', ['record' => $this->getRecord()]);
    }
}
