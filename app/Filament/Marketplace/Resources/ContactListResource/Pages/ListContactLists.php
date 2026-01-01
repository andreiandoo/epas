<?php

namespace App\Filament\Marketplace\Resources\ContactListResource\Pages;

use App\Filament\Marketplace\Resources\ContactListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContactLists extends ListRecords
{
    protected static string $resource = ContactListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
