<?php // ListArtistTypes.php
namespace App\Filament\Resources\Taxonomies\ArtistTypeResource\Pages;

use App\Filament\Resources\Taxonomies\ArtistTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListArtistTypes extends ListRecords
{
    protected static string $resource = ArtistTypeResource::class;
}