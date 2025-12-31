<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use Filament\Resources\Pages\EditRecord;

class EditArtist extends EditRecord
{
    protected static string $resource = ArtistResource::class;
}
