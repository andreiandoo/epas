<?php

namespace App\Filament\Marketplace\Resources\NewsletterResource\Pages;

use App\Filament\Marketplace\Resources\NewsletterResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletter extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = NewsletterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();
        $data['marketplace_client_id'] = $marketplace?->id;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
