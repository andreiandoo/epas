<?php

namespace App\Filament\Tenant\Resources\PhysicalResourceTypeResource\Pages;

use App\Filament\Tenant\Resources\PhysicalResourceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhysicalResourceTypes extends ListRecords
{
    protected static string $resource = PhysicalResourceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
