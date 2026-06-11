<?php

namespace App\Filament\Marketplace\Resources\TourResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TourResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = TourResource::class;

    /**
     * Force-stamp the marketplace_client_id (and tenant_id when available)
     * from the active marketplace context so the new tour scopes correctly.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();
        if ($marketplace) {
            $data['marketplace_client_id'] = $marketplace->id;
            if (!empty($marketplace->tenant_id)) {
                $data['tenant_id'] = $marketplace->tenant_id;
            }
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
