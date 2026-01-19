<?php

namespace App\Filament\Marketplace\Resources\ContactListResource\Pages;

use App\Filament\Marketplace\Resources\ContactListResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Resources\Pages\CreateRecord;

class CreateContactList extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ContactListResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();
        $data['marketplace_client_id'] = $marketplace?->id;

        return $data;
    }
}
