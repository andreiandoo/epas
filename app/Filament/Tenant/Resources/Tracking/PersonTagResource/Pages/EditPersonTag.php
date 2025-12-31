<?php

namespace App\Filament\Tenant\Resources\Tracking\PersonTagResource\Pages;

use App\Filament\Tenant\Resources\Tracking\PersonTagResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersonTag extends EditRecord
{
    protected static string $resource = PersonTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->hidden(fn() => $this->record->is_system),
        ];
    }
}
