<?php

namespace App\Filament\Marketplace\Resources\SeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\SeatingLayoutResource;
use App\Filament\Marketplace\Concerns\MovesCreateButtonToTable;
use Filament\Resources\Pages\ListRecords;

class ListSeatingLayouts extends ListRecords
{
    use MovesCreateButtonToTable;

    protected static string $resource = SeatingLayoutResource::class;
}
