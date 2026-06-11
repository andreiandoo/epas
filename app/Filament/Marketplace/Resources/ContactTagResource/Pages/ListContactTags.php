<?php

namespace App\Filament\Marketplace\Resources\ContactTagResource\Pages;

use App\Filament\Marketplace\Resources\ContactTagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContactTags extends ListRecords
{
    protected static string $resource = ContactTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
