<?php

namespace App\Filament\Marketplace\Resources\SystemUpdateResource\Pages;

use App\Filament\Marketplace\Resources\SystemUpdateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSystemUpdates extends ListRecords
{
    protected static string $resource = SystemUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Adaugă noutate'),
        ];
    }
}
