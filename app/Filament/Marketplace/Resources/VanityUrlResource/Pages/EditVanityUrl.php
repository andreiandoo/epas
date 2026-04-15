<?php

namespace App\Filament\Marketplace\Resources\VanityUrlResource\Pages;

use App\Filament\Marketplace\Resources\VanityUrlResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVanityUrl extends EditRecord
{
    protected static string $resource = VanityUrlResource::class;

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
