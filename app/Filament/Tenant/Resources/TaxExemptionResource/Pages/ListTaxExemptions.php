<?php

namespace App\Filament\Tenant\Resources\TaxExemptionResource\Pages;

use App\Filament\Tenant\Resources\TaxExemptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxExemptions extends ListRecords
{
    protected static string $resource = TaxExemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
