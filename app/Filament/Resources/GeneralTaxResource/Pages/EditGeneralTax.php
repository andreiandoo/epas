<?php

namespace App\Filament\Resources\GeneralTaxResource\Pages;

use App\Filament\Resources\GeneralTaxResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeneralTax extends EditRecord
{
    protected static string $resource = GeneralTaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Delete General Tax')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation(),
        ];
    }
}
