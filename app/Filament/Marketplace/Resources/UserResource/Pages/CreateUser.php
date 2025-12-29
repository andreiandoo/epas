<?php

namespace App\Filament\Marketplace\Resources\UserResource\Pages;

use App\Filament\Marketplace\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateUser extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        $data['role'] = 'editor';
        return $data;
    }
}
