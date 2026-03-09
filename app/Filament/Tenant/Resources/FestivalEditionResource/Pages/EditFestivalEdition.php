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
            Actions\Action::make('import_external_tickets')
                ->label('Import Bilete Externe')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->url(fn () => FestivalEditionResource::getUrl('external-tickets', ['record' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }
}
