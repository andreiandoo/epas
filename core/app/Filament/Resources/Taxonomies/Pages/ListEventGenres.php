<?php

namespace App\Filament\Resources\Taxonomies\EventGenreResource\Pages;

use App\Filament\Resources\Taxonomies\EventGenreResource;
use Filament\Resources\Pages\ListRecords;

class ListEventGenres extends ListRecords
{
    protected static string $resource = EventGenreResource::class;
}

