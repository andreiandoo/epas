<?php

namespace App\Filament\Marketplace\Resources\ActivityResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ActivityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateActivity extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ActivityResource::class;

    /**
     * Always enforce marketplace_client_id from session context, never from
     * the form payload. Even if a malicious admin POSTs a different ID, we
     * overwrite it before save.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
