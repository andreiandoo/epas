<?php

namespace App\Filament\Tenant\Resources\LocalTaxResource\Pages;

use App\Filament\Tenant\Resources\LocalTaxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocalTaxes extends ListRecords
{
    protected static string $resource = LocalTaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
