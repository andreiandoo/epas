<?php

namespace App\Filament\Resources\Taxonomies\EventTagResource\Pages;

use App\Filament\Resources\Taxonomies\EventTagResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ListEventTags extends ListRecords
{
    protected static string $resource = EventTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add event tag')
                ->icon('heroicon-m-plus'),

            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->action(function () {
                    Artisan::call('export:taxonomies', [
                        'type' => 'event-tags',
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
                        ->helperText('Upload a CSV file with event tag data. Format: name, slug, description')
                        ->disk('local')
                        ->directory('imports'),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/' . $data['file']);

                    Artisan::call('import:taxonomies', [
                        'file' => $filePath,
                        'type' => 'event-tags',
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

