<?php

namespace App\Filament\Marketplace\Resources\SupportProblemTypeResource\Pages;

use App\Filament\Marketplace\Resources\SupportProblemTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupportProblemTypes extends ListRecords
{
    protected static string $resource = SupportProblemTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
