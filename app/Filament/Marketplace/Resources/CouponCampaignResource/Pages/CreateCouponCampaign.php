<?php

namespace App\Filament\Marketplace\Resources\CouponCampaignResource\Pages;

use App\Filament\Marketplace\Resources\CouponCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCouponCampaign extends CreateRecord
{
    protected static string $resource = CouponCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
