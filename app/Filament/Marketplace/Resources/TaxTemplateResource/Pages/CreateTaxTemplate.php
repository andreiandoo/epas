<?php

namespace App\Filament\Marketplace\Resources\TaxTemplateResource\Pages;

use App\Filament\Marketplace\Resources\TaxTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTaxTemplate extends CreateRecord
{
    protected static string $resource = TaxTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        $data['marketplace_client_id'] = $marketplaceAdmin?->marketplace_client_id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
