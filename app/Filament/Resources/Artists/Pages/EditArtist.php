<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArtist extends EditRecord
{
    protected static string $resource = ArtistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_performance')
                ->label('View Performance')
                ->icon('heroicon-o-presentation-chart-line')
                ->color('info')
                ->url(fn () => ArtistResource::getUrl('view', ['record' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }
}
