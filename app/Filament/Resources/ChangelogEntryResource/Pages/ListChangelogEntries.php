<?php

namespace App\Filament\Resources\ChangelogEntryResource\Pages;

use App\Filament\Resources\ChangelogEntryResource;
use App\Models\ChangelogEntry;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Illuminate\Support\Facades\Artisan;

class ListChangelogEntries extends ListRecords
{
    protected static string $resource = ChangelogEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync')
                ->label('Sincronizează din Git')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    Artisan::call('changelog:update');
                    $this->notify('success', 'Changelog sincronizat cu succes!');
                })
                ->requiresConfirmation()
                ->modalHeading('Sincronizare Changelog')
                ->modalDescription('Aceasta va parsa commit-urile noi din Git și va actualiza baza de date.')
                ->modalSubmitActionLabel('Sincronizează'),

            Actions\Action::make('sync_full')
                ->label('Resincronizare Completă')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->action(function () {
                    Artisan::call('changelog:update', ['--full' => true]);
                    $this->notify('success', 'Changelog resincronizat complet!');
                })
                ->requiresConfirmation()
                ->modalHeading('Resincronizare Completă')
                ->modalDescription('Aceasta va parsa TOATE commit-urile din Git. Poate dura câteva minute.')
                ->modalSubmitActionLabel('Resincronizează'),

            Actions\Action::make('generate_md')
                ->label('Generează CHANGELOG.md')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    Artisan::call('changelog:generate-md');
                    $this->notify('success', 'CHANGELOG.md generat cu succes!');
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Stats widgets could go here
        ];
    }

    public function getSubheading(): ?string
    {
        $total = ChangelogEntry::count();
        $visible = ChangelogEntry::visible()->count();
        $lastWeek = ChangelogEntry::where('committed_at', '>=', now()->subDays(7))->count();

        return "Total: {$total} | Vizibile: {$visible} | Ultima săptămână: {$lastWeek}";
    }
}
