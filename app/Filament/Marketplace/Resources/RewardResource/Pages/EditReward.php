<?php

namespace App\Filament\Marketplace\Resources\RewardResource\Pages;

use App\Filament\Marketplace\Resources\RewardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReward extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = RewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
