<?php

namespace App\Filament\Tenant\Resources\MerchandiseAllocationResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseAllocations extends ListRecords
{
    protected static string $resource = MerchandiseAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
