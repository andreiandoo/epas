<?php

namespace App\Filament\Marketplace\Resources\ContactTagResource\Pages;

use App\Filament\Marketplace\Resources\ContactTagResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactTag extends EditRecord
{
    protected static string $resource = ContactTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
