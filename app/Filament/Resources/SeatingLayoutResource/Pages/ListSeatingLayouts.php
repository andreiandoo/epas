<?php

namespace App\Filament\Resources\SeatingLayoutResource\Pages;

use App\Filament\Resources\SeatingLayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSeatingLayouts extends ListRecords
{
    protected static string $resource = SeatingLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
