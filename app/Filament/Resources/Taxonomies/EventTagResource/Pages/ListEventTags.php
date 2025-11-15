<?php

namespace App\Filament\Resources\Taxonomies\EventTagResource\Pages;

use App\Filament\Resources\Taxonomies\EventTagResource;
use Filament\Resources\Pages\ListRecords;

class ListEventTags extends ListRecords
{
    protected static string $resource = EventTagResource::class;
}

