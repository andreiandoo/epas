<?php

namespace App\Filament\Tenant\Resources\SeatingLayoutResource\Pages;

use App\Filament\Tenant\Resources\SeatingLayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeatingLayout extends EditRecord
{
    protected static string $resource = SeatingLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('designer')
                ->label('Deschide Designer')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => SeatingLayoutResource::getUrl('designer', ['record' => $this->record])),
        ];
    }
}
