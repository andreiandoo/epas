<?php

namespace App\Filament\Marketplace\Resources\TaxRegistryResource\Pages;

use App\Filament\Marketplace\Resources\TaxRegistryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxRegistries extends ListRecords
{
    protected static string $resource = TaxRegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
