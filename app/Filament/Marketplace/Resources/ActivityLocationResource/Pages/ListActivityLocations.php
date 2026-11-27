<?php

namespace App\Filament\Marketplace\Resources\ActivityLocationResource\Pages;

use App\Filament\Marketplace\Resources\ActivityLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActivityLocations extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ActivityLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Locație nouă'),
        ];
    }
}
