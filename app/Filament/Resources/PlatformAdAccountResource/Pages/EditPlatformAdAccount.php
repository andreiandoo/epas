<?php

namespace App\Filament\Resources\PlatformAdAccountResource\Pages;

use App\Filament\Resources\PlatformAdAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformAdAccount extends EditRecord
{
    protected static string $resource = PlatformAdAccountResource::class;

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
