<?php

namespace App\Filament\Resources\Taxonomies\EventTypeResource\Pages;

use App\Filament\Resources\Taxonomies\EventTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListEventTypes extends ListRecords
{
    protected static string $resource = EventTypeResource::class;
}
