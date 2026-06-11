<?php

namespace App\Filament\Marketplace\Resources\TravelerTypeResource\Pages;

use App\Filament\Marketplace\Resources\TravelerTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTravelerType extends EditRecord
{
    protected static string $resource = TravelerTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
