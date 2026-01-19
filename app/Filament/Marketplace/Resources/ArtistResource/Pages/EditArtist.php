<?php

namespace App\Filament\Marketplace\Resources\ArtistResource\Pages;

use App\Filament\Marketplace\Resources\ArtistResource;
use Filament\Resources\Pages\EditRecord;

class EditArtist extends EditRecord
{
    protected static string $resource = ArtistResource::class;

    protected function getHeaderActions(): array
    {
        // No delete action for marketplace users - they cannot delete artists
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
