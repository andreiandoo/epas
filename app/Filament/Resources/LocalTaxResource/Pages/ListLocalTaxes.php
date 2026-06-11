<?php

namespace App\Filament\Resources\LocalTaxResource\Pages;

use App\Filament\Resources\LocalTaxResource;
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
