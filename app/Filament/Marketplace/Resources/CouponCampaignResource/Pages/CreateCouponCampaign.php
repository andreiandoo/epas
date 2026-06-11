<?php

namespace App\Filament\Marketplace\Resources\CouponCampaignResource\Pages;

use App\Filament\Marketplace\Resources\CouponCampaignResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateCouponCampaign extends CreateRecord
{
    protected static string $resource = CouponCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userId = auth()->id();
        // Only set created_by if the user actually exists in the database
        if ($userId && User::where('id', $userId)->exists()) {
            $data['created_by'] = $userId;
        }
        return $data;
    }
}
