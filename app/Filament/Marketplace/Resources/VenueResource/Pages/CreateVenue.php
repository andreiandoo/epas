<?php

namespace App\Filament\Marketplace\Resources\VenueResource\Pages;

use App\Filament\Marketplace\Resources\VenueResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateVenue extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = VenueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        return $data;
    }
}
