<?php

namespace App\Filament\Resources\PlatformAudienceResource\Pages;

use App\Filament\Resources\PlatformAudienceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformAudience extends EditRecord
{
    protected static string $resource = PlatformAudienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
