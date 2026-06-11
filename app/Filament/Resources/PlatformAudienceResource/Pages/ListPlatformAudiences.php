<?php

namespace App\Filament\Resources\PlatformAudienceResource\Pages;

use App\Filament\Resources\PlatformAudienceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAudiences extends ListRecords
{
    protected static string $resource = PlatformAudienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
