<?php

namespace App\Filament\Resources\AdsCampaignResource\Pages;

use App\Filament\Resources\AdsCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdsCampaign extends EditRecord
{
    protected static string $resource = AdsCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
