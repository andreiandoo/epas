<?php

namespace App\Filament\Marketplace\Resources\SystemUpdateResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\SystemUpdateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSystemUpdate extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = SystemUpdateResource::class;

    /**
     * Auto-inject the marketplace_client_id of the logged-in admin so the
     * update is scoped to the correct marketplace without exposing a
     * select in the form (the operator only ever posts for THEIR
     * marketplace — cross-marketplace management would need a super-admin
     * panel).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClientId();
        return $data;
    }
}
