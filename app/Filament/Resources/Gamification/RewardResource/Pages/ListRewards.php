<?php

namespace App\Filament\Resources\Gamification\RewardResource\Pages;

use App\Filament\Resources\Gamification\RewardResource;
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
