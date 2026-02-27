<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Exports\ArtistExporter;
use App\Filament\Resources\Artists\ArtistResource;
use App\Jobs\FetchArtistSocialStats;
use App\Models\Artist;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Bus;

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
                ->modalDescription('This will queue background jobs to fetch YouTube, Spotify, and Facebook stats for all artists with configured social IDs. You will receive a notification when the process is complete.')
                ->action(function () {
                    // Find artists that have at least one social profile and weren't updated in last 7 days
                    $artists = Artist::where(function ($q) {
                        $q->where(function ($sub) {
                            $sub->whereNotNull('youtube_id')->where('youtube_id', '!=', '');
                        })->orWhere(function ($sub) {
                            $sub->whereNotNull('spotify_id')->where('spotify_id', '!=', '');
                        })->orWhere(function ($sub) {
                            $sub->whereNotNull('facebook_url')->where('facebook_url', '!=', '');
                        });
                    })
                    ->where(function ($q) {
                        $q->whereNull('social_stats_updated_at')
                          ->orWhere('social_stats_updated_at', '<', now()->subDays(7));
                    })
                    ->pluck('id');

                    if ($artists->isEmpty()) {
                        Notification::make()
                            ->title('No artists to update')
                            ->body('All artists with social profiles were updated in the last 7 days.')
                            ->info()
                            ->send();
                        return;
                    }

                    $userId = auth()->id();

                    // Create jobs for each artist
                    $jobs = $artists->map(fn ($id) => new FetchArtistSocialStats($id))->all();

                    // Dispatch as a batch with completion notification
                    Bus::batch($jobs)
                        ->name('Fetch Artist Social Stats')
                        ->allowFailures()
                        ->onQueue('default')
                        ->then(function () use ($userId) {
                            // Send database notification on completion
                            $user = \App\Models\User::find($userId);
                            if ($user) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Social Stats Fetch Complete')
                                    ->body('All artist social stats have been fetched and updated.')
                                    ->success()
                                    ->sendToDatabase($user);
                            }
                        })
                        ->dispatch();

                    Notification::make()
                        ->title('Social stats fetch started')
                        ->body("Queued {$artists->count()} artists for background processing. You'll receive a notification when done.")
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
