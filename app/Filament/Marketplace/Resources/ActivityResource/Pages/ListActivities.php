<?php

namespace App\Filament\Marketplace\Resources\ActivityResource\Pages;

use App\Filament\Marketplace\Resources\ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Activitate nouă'),
        ];
    }
}
