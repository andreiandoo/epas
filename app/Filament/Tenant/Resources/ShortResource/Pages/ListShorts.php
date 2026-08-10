<?php

namespace App\Filament\Tenant\Resources\ShortResource\Pages;

use App\Filament\Tenant\Resources\ShortResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShorts extends ListRecords
{
    protected static string $resource = ShortResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
