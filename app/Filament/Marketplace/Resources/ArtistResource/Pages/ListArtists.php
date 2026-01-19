<?php

namespace App\Filament\Marketplace\Resources\ArtistResource\Pages;

use App\Filament\Marketplace\Resources\ArtistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArtists extends ListRecords
{
    protected static string $resource = ArtistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
