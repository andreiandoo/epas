<?php

namespace App\Filament\Marketplace\Resources\RewardResource\Pages;

use App\Filament\Marketplace\Resources\RewardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRewards extends ListRecords
{
    protected static string $resource = RewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
