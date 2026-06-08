<?php

namespace App\Filament\Marketplace\Resources\TravelerTypeResource\Pages;

use App\Filament\Marketplace\Resources\TravelerTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelerTypes extends ListRecords
{
    protected static string $resource = TravelerTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
