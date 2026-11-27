<?php

namespace App\Filament\Marketplace\Resources\RewardRedemptionResource\Pages;

use App\Filament\Marketplace\Resources\RewardRedemptionResource;
use Filament\Resources\Pages\ListRecords;

class ListRewardRedemptions extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = RewardRedemptionResource::class;
}
