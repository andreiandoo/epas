<?php 
namespace App\Filament\Resources\Taxonomies\ArtistGenreResource\Pages;

use App\Filament\Resources\Taxonomies\ArtistGenreResource;
use Filament\Resources\Pages\ListRecords;

class ListArtistGenres extends ListRecords
{
    protected static string $resource = ArtistGenreResource::class;
}
