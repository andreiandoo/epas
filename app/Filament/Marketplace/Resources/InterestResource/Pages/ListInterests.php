<?php

namespace App\Filament\Marketplace\Resources\InterestResource\Pages;

use App\Filament\Marketplace\Resources\InterestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInterests extends ListRecords
{
    protected static string $resource = InterestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
