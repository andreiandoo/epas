<?php

namespace App\Filament\Tenant\Resources\LocalTaxResource\Pages;

use App\Filament\Tenant\Resources\LocalTaxResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocalTax extends EditRecord
{
    protected static string $resource = LocalTaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
