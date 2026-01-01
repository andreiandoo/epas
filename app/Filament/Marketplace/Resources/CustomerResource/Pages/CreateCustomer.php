<?php

namespace App\Filament\Marketplace\Resources\CustomerResource\Pages;

use App\Filament\Marketplace\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateCustomer extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        return $data;
    }
}
