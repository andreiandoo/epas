<?php

namespace App\Filament\Marketplace\Resources\EmailTemplateResource\Pages;

use App\Filament\Marketplace\Resources\EmailTemplateResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = EmailTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();
        $data['marketplace_client_id'] = $marketplace?->id;

        return $data;
    }
}
