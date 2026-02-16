<?php

namespace App\Filament\Marketplace\Resources\ArtistResource\Pages;

use App\Filament\Marketplace\Resources\ArtistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArtist extends EditRecord
{
    protected static string $resource = ArtistResource::class;

    protected bool $shouldClose = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('saveAndClose')
                ->label('Salvează și închide')
                ->action(function () {
                    $this->shouldClose = true;
                    $this->save();
                })
                ->color('gray')
                ->icon('heroicon-o-check'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->shouldClose) {
            return $this->getResource()::getUrl('index');
        }

        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
