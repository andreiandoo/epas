<?php

namespace App\Filament\Resources\AudienceTargeting\AudienceCampaignResource\Pages;

use App\Filament\Resources\AudienceTargeting\AudienceCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAudienceCampaigns extends ListRecords
{
    protected static string $resource = AudienceCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
