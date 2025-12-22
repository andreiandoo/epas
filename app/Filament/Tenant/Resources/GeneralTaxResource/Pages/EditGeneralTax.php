<?php

namespace App\Filament\Tenant\Resources\GeneralTaxResource\Pages;

use App\Filament\Tenant\Resources\GeneralTaxResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeneralTax extends EditRecord
{
    protected static string $resource = GeneralTaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
