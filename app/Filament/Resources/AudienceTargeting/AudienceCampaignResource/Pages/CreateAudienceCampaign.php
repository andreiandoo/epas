<?php

namespace App\Filament\Resources\AudienceTargeting\AudienceCampaignResource\Pages;

use App\Filament\Resources\AudienceTargeting\AudienceCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAudienceCampaign extends CreateRecord
{
    protected static string $resource = AudienceCampaignResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
