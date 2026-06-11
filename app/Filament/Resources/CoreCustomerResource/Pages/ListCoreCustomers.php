<?php

namespace App\Filament\Resources\CoreCustomerResource\Pages;

use App\Filament\Resources\CoreCustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCoreCustomers extends ListRecords
{
    protected static string $resource = CoreCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - customers are created automatically via tracking
        ];
    }
}
