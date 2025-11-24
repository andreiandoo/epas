<?php

namespace App\Filament\Resources\ContractCustomVariables\Pages;

use App\Filament\Resources\ContractCustomVariables\ContractCustomVariableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContractCustomVariables extends ListRecords
{
    protected static string $resource = ContractCustomVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
