<?php

namespace App\Filament\Resources\ContractCustomVariables\Pages;

use App\Filament\Resources\ContractCustomVariables\ContractCustomVariableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContractCustomVariable extends EditRecord
{
    protected static string $resource = ContractCustomVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
