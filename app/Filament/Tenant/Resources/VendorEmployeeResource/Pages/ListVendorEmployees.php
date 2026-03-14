<?php

namespace App\Filament\Tenant\Resources\VendorEmployeeResource\Pages;

use App\Filament\Tenant\Resources\VendorEmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendorEmployees extends ListRecords
{
    protected static string $resource = VendorEmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
