<?php

namespace App\Filament\Resources\Gamification\RewardResource\Pages;

use App\Filament\Resources\Gamification\RewardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReward extends EditRecord
{
    protected static string $resource = RewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
