<?php

namespace App\Filament\Marketplace\Resources\RegionResource\Pages;

use App\Filament\Marketplace\Resources\RegionResource;
use App\Filament\Marketplace\Concerns\MovesCreateButtonToTable;
use Filament\Resources\Pages\ListRecords;

class ListRegions extends ListRecords
{
    use MovesCreateButtonToTable;

    protected static string $resource = RegionResource::class;
}
