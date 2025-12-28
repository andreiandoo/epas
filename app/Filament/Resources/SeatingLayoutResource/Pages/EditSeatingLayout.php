<?php

namespace App\Filament\Resources\SeatingLayoutResource\Pages;

use App\Filament\Resources\SeatingLayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeatingLayout extends EditRecord
{
    protected static string $resource = SeatingLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('designer')
                ->label('Open Designer')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => static::getResource()::getUrl('designer', ['record' => $this->getRecord()])),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Increment version on save
        $data['version'] = ($this->getRecord()->version ?? 0) + 1;

        return $data;
    }
}
