<?php

namespace App\Filament\Resources\LocalTaxResource\Pages;

use App\Filament\Resources\LocalTaxResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocalTax extends EditRecord
{
    protected static string $resource = LocalTaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Delete Local Tax')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation(),
        ];
    }
}
