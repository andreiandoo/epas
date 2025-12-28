<?php
namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add venue')
                ->icon('heroicon-m-plus'),

            Actions\Action::make('import')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                        ->required()
                        ->helperText('CSV columns: name (required), slug, address, city, state, country, website_url, phone, phone2, email, email2, facebook_url, instagram_url, tiktok_url, image_url, video_type (youtube/vimeo), video_url, capacity, capacity_total, capacity_standing, capacity_seated, lat, lng, google_maps_url, established_at (YYYY-MM-DD), description')
                        ->disk('local')
                        ->directory('imports')
                        ->storeFileNamesIn('original_filename')
                        ->visibility('private'),
                ])
                ->action(function (array $data) {
                    // Get the actual file path from storage
                    $filePath = Storage::disk('local')->path($data['file']);

                    // Check if file exists
                    if (!Storage::disk('local')->exists($data['file'])) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('File not found: ' . $data['file'])
                            ->danger()
                            ->send();
                        return;
                    }

                    Artisan::call('import:venues', [
                        'file' => $filePath,
                    ]);

                    $output = Artisan::output();

                    Notification::make()
                        ->title('Import completed')
                        ->body($output)
                        ->success()
                        ->send();

                    // Delete uploaded file after import
                    Storage::disk('local')->delete($data['file']);
                }),
        ];
    }
}
