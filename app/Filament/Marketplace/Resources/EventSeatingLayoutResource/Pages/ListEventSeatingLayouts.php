<?php

namespace App\Filament\Marketplace\Resources\EventSeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\EventSeatingLayoutResource;
use App\Filament\Marketplace\Concerns\MovesCreateButtonToTable;
use Filament\Resources\Pages\ListRecords;

class ListEventSeatingLayouts extends ListRecords
{
    use MovesCreateButtonToTable;

    protected static string $resource = EventSeatingLayoutResource::class;
}
