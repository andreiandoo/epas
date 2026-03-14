<?php

namespace App\Filament\Tenant\Resources\FestivalEditionResource\Pages;

use App\Filament\Tenant\Resources\FestivalEditionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFestivalEditions extends ListRecords
{
    protected static string $resource = FestivalEditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
