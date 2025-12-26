<?php
namespace App\Filament\Resources\Taxonomies\ArtistGenreResource\Pages;

use App\Filament\Resources\Taxonomies\ArtistGenreResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ListArtistGenres extends ListRecords
{
    protected static string $resource = ArtistGenreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add artist genre')
                ->icon('heroicon-m-plus'),

            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->action(function () {
                    Artisan::call('export:taxonomies', [
                        'type' => 'artist-genres',
                    ]);

                    $output = Artisan::output();
                    preg_match('/File: (.+)/', $output, $matches);
                    $filePath = $matches[1] ?? null;

                    if ($filePath && file_exists($filePath)) {
                        return response()->download($filePath)->deleteFileAfterSend();
                    }

                    Notification::make()
                        ->title('Export completed')
                        ->body($output)
                        ->success()
                        ->send();
                }),

            Actions\Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                        ->required()
                        ->helperText('Upload a CSV file with artist genre data. Format: name, slug, description, parent_slug')
                        ->disk('local')
                        ->directory('imports'),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/' . $data['file']);

                    Artisan::call('import:taxonomies', [
                        'file' => $filePath,
                        'type' => 'artist-genres',
                    ]);

                    $output = Artisan::output();

                    Notification::make()
                        ->title('Import completed')
                        ->body($output)
                        ->success()
                        ->send();

                    // Delete uploaded file after import
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }),
        ];
    }
}
