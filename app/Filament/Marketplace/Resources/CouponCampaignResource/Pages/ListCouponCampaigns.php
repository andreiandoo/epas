<?php

namespace App\Filament\Marketplace\Resources\CouponCampaignResource\Pages;

use App\Filament\Marketplace\Resources\CouponCampaignResource;
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
