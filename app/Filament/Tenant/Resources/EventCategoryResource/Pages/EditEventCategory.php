<?php

namespace App\Filament\Tenant\Resources\EventCategoryResource\Pages;

use App\Filament\Tenant\Resources\EventCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventCategory extends EditRecord
{
    protected static string $resource = EventCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
