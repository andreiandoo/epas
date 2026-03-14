<?php

namespace App\Filament\Marketplace\Resources\SeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\SeatingLayoutResource;
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
