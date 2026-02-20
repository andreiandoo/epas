<?php

namespace App\Filament\Marketplace\Resources\PartnerArtistResource\Pages;

use App\Filament\Marketplace\Resources\PartnerArtistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartnerArtists extends ListRecords
{
    protected static string $resource = PartnerArtistResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
