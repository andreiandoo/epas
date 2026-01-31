<?php

namespace App\Filament\Marketplace\Resources\ServiceTypeResource\Pages;

use App\Filament\Marketplace\Resources\ServiceTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceTypes extends ListRecords
{
    protected static string $resource = ServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - service types are auto-created
        ];
    }
}
