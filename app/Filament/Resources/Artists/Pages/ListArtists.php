<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use Filament\Actions;
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
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                        ->required()
                        ->helperText('Upload a CSV file with artist data. See documentation for format.')
                        ->disk('local')
                        ->directory('imports'),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/' . $data['file']);

                    Artisan::call('import:artists', [
                        'file' => $filePath,
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
