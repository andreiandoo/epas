<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Exports\ArtistExporter;
use App\Filament\Resources\Artists\ArtistResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;

class ListArtists extends ListRecords
{
    protected static string $resource = ArtistResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Artists';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add artist')
                ->icon('heroicon-m-plus')
                ->outlined()
                ->modalHeading('Add artist')
                ->slideOver(), // opțional; dacă preferi redirect la /create, șterge ->slideOver()

            Actions\Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn () => ArtistResource::getUrl('import')),

            Actions\Action::make('fetch_stats')
                ->label('Fetch Social Stats')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Fetch Social Stats')
                ->modalDescription('This will fetch YouTube and Spotify stats for all artists with configured IDs. This may take a few minutes.')
                ->action(function () {
                    Artisan::call('artists:update-social-stats');
                    Notification::make()
                        ->title('Social stats updated')
                        ->body('YouTube and Spotify stats have been fetched for all artists.')
                        ->success()
                        ->send();
                }),

            ExportAction::make()
                ->exporter(ArtistExporter::class)
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->chunkSize(500)
                ->columnMapping(false)
                ->formats([ExportFormat::Csv]),
        ];
    }
}
