<?php

namespace App\Filament\Resources\TaxExemptionResource\Pages;

use App\Filament\Resources\TaxExemptionResource;
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
