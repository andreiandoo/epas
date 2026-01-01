<?php

namespace App\Filament\Marketplace\Resources\ContactTagResource\Pages;

use App\Filament\Marketplace\Resources\ContactTagResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Resources\Pages\CreateRecord;

class CreateContactTag extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ContactTagResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();
        $data['marketplace_client_id'] = $marketplace?->id;

        return $data;
    }
}
