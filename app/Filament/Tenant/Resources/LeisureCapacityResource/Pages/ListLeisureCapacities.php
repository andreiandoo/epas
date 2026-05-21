<?php

namespace App\Filament\Tenant\Resources\LeisureCapacityResource\Pages;

use App\Filament\Tenant\Resources\LeisureCapacityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeisureCapacities extends ListRecords
{
    protected static string $resource = LeisureCapacityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
