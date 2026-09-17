<?php

namespace App\Filament\Marketplace\Resources\PartnerAdResource\Pages;

use App\Filament\Marketplace\Resources\PartnerAdResource;
use App\Services\Partners\PartnerAds;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePartnerAd extends CreateRecord
{
    protected static string $resource = PartnerAdResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = PartnerAdResource::prepareForSave($data);
        $data['marketplace_client_id'] = PartnerAdResource::getMarketplaceClient()?->id;
        $data['created_by_marketplace_admin_id'] = Auth::guard('marketplace_admin')->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(PartnerAds::class)->sync($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
