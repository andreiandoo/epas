<?php

namespace App\Filament\Marketplace\Resources\AffiliateEventSourceResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateEventSourceResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateAffiliateEventSource extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = AffiliateEventSourceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();
        $data['marketplace_client_id'] = $marketplace?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
