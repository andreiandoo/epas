<?php

namespace App\Filament\Resources\PlatformAudienceResource\Pages;

use App\Filament\Resources\PlatformAudienceResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformAudience extends CreateRecord
{
    protected static string $resource = PlatformAudienceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
