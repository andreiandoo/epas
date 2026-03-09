<?php

namespace App\Filament\Tenant\Resources\FestivalEditionResource\Pages;

use App\Filament\Tenant\Resources\FestivalEditionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFestivalEdition extends EditRecord
{
    protected static string $resource = FestivalEditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
