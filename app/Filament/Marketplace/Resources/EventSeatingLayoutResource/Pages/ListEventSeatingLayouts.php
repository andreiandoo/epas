<?php

namespace App\Filament\Marketplace\Resources\EventSeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\EventSeatingLayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventSeatingLayouts extends ListRecords
{
    protected static string $resource = EventSeatingLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
