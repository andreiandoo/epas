<?php

namespace App\Filament\Marketplace\Resources\EventCategoryResource\Pages;

use App\Filament\Marketplace\Resources\EventCategoryResource;
use App\Filament\Marketplace\Concerns\MovesCreateButtonToTable;
use Filament\Resources\Pages\ListRecords;

class ListEventCategories extends ListRecords
{
    use MovesCreateButtonToTable;

    protected static string $resource = EventCategoryResource::class;
}
