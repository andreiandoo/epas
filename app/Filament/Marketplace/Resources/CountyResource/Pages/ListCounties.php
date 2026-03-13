<?php

namespace App\Filament\Marketplace\Resources\CountyResource\Pages;

use App\Filament\Marketplace\Resources\CountyResource;
use App\Filament\Marketplace\Concerns\MovesCreateButtonToTable;
use Filament\Resources\Pages\ListRecords;

class ListCounties extends ListRecords
{
    use MovesCreateButtonToTable;

    protected static string $resource = CountyResource::class;
}
