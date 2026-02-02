<?php

namespace App\Filament\Marketplace\Resources\MediaLibraryResource\Pages;

use App\Filament\Marketplace\Resources\MediaLibraryResource;
use App\Models\MediaLibrary;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class ViewMedia extends ViewRecord
{
    protected static string $resource = MediaLibraryResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->filename ?? 'Vizualizare Media';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Descarcă')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn () => $this->record->url)
                ->openUrlInNewTab(),

            Actions\Action::make('copy_url')
                ->label('Copiază URL')
                ->icon('heroicon-o-clipboard-document')
                ->color('gray')
                ->action(function () {
                    Notification::make()
                        ->title('URL Copiat')
                        ->body('URL-ul fișierului a fost copiat în clipboard.')
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'x-data' => '{}',
                    'x-on:click' => "navigator.clipboard.writeText('" . ($this->record->url ?? '') . "')",
                ]),

            Actions\DeleteAction::make()
                ->label('Șterge')
                ->requiresConfirmation()
                ->modalHeading('Șterge Media')
                ->modalDescription('Sigur dorești să ștergi acest fișier media? Această acțiune nu poate fi anulată.')
                ->modalIcon('heroicon-o-exclamation-triangle'),
        ];
    }
}
