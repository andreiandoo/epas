<?php

namespace App\Filament\Resources\GeneralTaxResource\Pages;

use App\Filament\Resources\GeneralTaxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGeneralTaxes extends ListRecords
{
    protected static string $resource = GeneralTaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
