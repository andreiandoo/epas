<?php

namespace App\Filament\Tenant\Resources\MerchandiseSupplierResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseSuppliers extends ListRecords
{
    protected static string $resource = MerchandiseSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
