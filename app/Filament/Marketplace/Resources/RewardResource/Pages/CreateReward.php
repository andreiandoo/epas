<?php

namespace App\Filament\Marketplace\Resources\RewardResource\Pages;

use App\Filament\Marketplace\Resources\RewardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReward extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = RewardResource::class;
}
