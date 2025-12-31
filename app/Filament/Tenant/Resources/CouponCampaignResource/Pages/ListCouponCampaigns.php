<?php

namespace App\Filament\Tenant\Resources\CouponCampaignResource\Pages;

use App\Filament\Tenant\Resources\CouponCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCouponCampaigns extends ListRecords
{
    protected static string $resource = CouponCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
