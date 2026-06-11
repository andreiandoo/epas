<?php

namespace App\Filament\Resources\PlatformAdAccountResource\Pages;

use App\Filament\Resources\PlatformAdAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformAdAccount extends CreateRecord
{
    protected static string $resource = PlatformAdAccountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
