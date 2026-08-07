<?php

namespace App\Filament\Resources\Shorts\Pages;

use App\Filament\Resources\Shorts\ShortResource;
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
