<?php
namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use Filament\Resources\Pages\ListRecords;

class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;
}
