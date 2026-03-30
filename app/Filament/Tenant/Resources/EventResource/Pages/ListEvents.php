<?php

namespace App\Filament\Tenant\Resources\EventResource\Pages;

use App\Filament\Tenant\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Import Events')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('import')),
            Actions\CreateAction::make(),
        ];
    }
}
