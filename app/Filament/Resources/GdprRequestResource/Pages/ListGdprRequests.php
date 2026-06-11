<?php

namespace App\Filament\Resources\GdprRequestResource\Pages;

use App\Filament\Resources\GdprRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGdprRequests extends ListRecords
{
    protected static string $resource = GdprRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
