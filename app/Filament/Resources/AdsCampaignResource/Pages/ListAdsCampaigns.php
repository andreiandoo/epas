<?php

namespace App\Filament\Resources\AdsCampaignResource\Pages;

use App\Filament\Resources\AdsCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdsCampaigns extends ListRecords
{
    protected static string $resource = AdsCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
