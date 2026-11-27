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
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = MediaLibraryResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->filename ?? 'Vizualizare Media';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label(__('Descarcă'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn () => $this->record->url)
                ->openUrlInNewTab(),

            Actions\Action::make('copy_url')
                ->label(__('Copiază URL'))
                ->icon('heroicon-o-clipboard-document')
                ->color('gray')
                ->action(function () {
                    Notification::make()
                        ->title(__('URL Copiat'))
                        ->body(__('URL-ul fișierului a fost copiat în clipboard.'))
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'x-data' => '{}',
                    'x-on:click' => "navigator.clipboard.writeText('" . ($this->record->url ?? '') . "')",
                ]),

            Actions\DeleteAction::make()
                ->label(__('Șterge'))
                ->requiresConfirmation()
                ->modalHeading(__('Șterge Media'))
                ->modalDescription(__('Sigur dorești să ștergi acest fișier media? Această acțiune nu poate fi anulată.'))
                ->modalIcon('heroicon-o-exclamation-triangle'),
        ];
    }
}
