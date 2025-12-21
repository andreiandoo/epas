<?php

namespace App\Filament\Organizer\Resources\TeamResource\Pages;

use App\Filament\Organizer\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeam extends ListRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Team Member'),
        ];
    }
}
