<?php

namespace App\Filament\Marketplace\Resources\CityResource\Pages;

use App\Filament\Marketplace\Resources\CityResource;
use App\Filament\Marketplace\Concerns\MovesCreateButtonToTable;
use Filament\Resources\Pages\ListRecords;

class ListCities extends ListRecords
{
    use MovesCreateButtonToTable;

    protected static string $resource = CityResource::class;
}
