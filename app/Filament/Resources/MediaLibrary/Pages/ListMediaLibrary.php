<?php

namespace App\Filament\Resources\MediaLibrary\Pages;

use App\Filament\Resources\MediaLibrary\MediaLibraryResource;
use App\Filament\Widgets\MediaLibraryStatsWidget;
use App\Models\MediaLibrary;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class ListMediaLibrary extends ListRecords
{
    protected static string $resource = MediaLibraryResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Media Library';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Scan Storage Action
            Actions\Action::make('scan_storage')
                ->label('Scan Storage')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Scan Storage for Media Files')
                ->modalDescription('This will scan the storage directory for media files and add any new files to the library. Existing files will be skipped.')
                ->modalIcon('heroicon-o-magnifying-glass')
                ->action(function () {
                    $this->scanStorageForMedia();
                }),

            // Upload Action
            Actions\Action::make('upload')
                ->label('Upload Files')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('files')
                        ->label('Select Files')
                        ->multiple()
                        ->disk('public')
                        ->directory('uploads/' . now()->format('Y/m'))
                        ->visibility('public')
                        ->maxSize(10240) // 10MB
                        ->acceptedFileTypes([
                            'image/*',
                            'video/*',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->helperText('Upload images, videos, PDFs, or documents (max 10MB each)')
                        ->required(),

                    \Filament\Forms\Components\Select::make('collection')
                        ->label('Collection')
                        ->options([
                            'uploads' => 'General Uploads',
                            'artists' => 'Artists',
                            'events' => 'Events',
                            'products' => 'Products',
                            'blog' => 'Blog',
                            'gallery' => 'Gallery',
                            'documents' => 'Documents',
                        ])
                        ->default('uploads'),
                ])
                ->action(function (array $data) {
                    $files = $data['files'] ?? [];
                    $collection = $data['collection'] ?? 'uploads';
                    $uploaded = 0;

                    foreach ($files as $filePath) {
                        try {
                            MediaLibrary::createFromPath(
                                path: $filePath,
                                disk: 'public',
                                collection: $collection,
                                uploadedBy: auth()->id()
                            );
                            $uploaded++;
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Failed to create media record for {$filePath}: " . $e->getMessage());
                        }
                    }

                    Notification::make()
                        ->title('Files Uploaded')
                        ->body("Successfully uploaded {$uploaded} file(s) to the media library.")
                        ->success()
                        ->send();
                }),

            // Cleanup Missing Files
            Actions\Action::make('cleanup')
                ->label('Cleanup')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cleanup Missing Files')
                ->modalDescription('This will remove database records for files that no longer exist on disk.')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->action(function () {
                    $deleted = 0;

                    MediaLibrary::query()->chunk(100, function ($records) use (&$deleted) {
                        foreach ($records as $record) {
                            if (!$record->existsOnDisk()) {
                                $record->delete();
                                $deleted++;
                            }
                        }
                    });

                    Notification::make()
                        ->title('Cleanup Complete')
                        ->body("Removed {$deleted} orphaned record(s).")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Scan storage directory for media files
     */
    protected function scanStorageForMedia(): void
    {
        $disk = Storage::disk('public');
        $files = $disk->allFiles();
        $added = 0;
        $skipped = 0;

        // Get all existing paths in a single query for efficiency
        $existingPaths = MediaLibrary::where('disk', 'public')
            ->pluck('path')
            ->flip()
            ->toArray();

        foreach ($files as $filePath) {
            // Skip hidden files and system files
            if (str_starts_with(basename($filePath), '.')) {
                continue;
            }

            // Skip if already in library
            if (isset($existingPaths[$filePath])) {
                $skipped++;
                continue;
            }

            // Check if it's a media file
            try {
                $mimeType = $disk->mimeType($filePath);

                // Only add images, videos, and documents
                $isMedia = str_starts_with($mimeType, 'image/')
                    || str_starts_with($mimeType, 'video/')
                    || in_array($mimeType, [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain',
                        'text/csv',
                    ]);

                if (!$isMedia) {
                    continue;
                }

                MediaLibrary::createFromPath($filePath, 'public');
                $added++;
            } catch (\Throwable $e) {
                // Skip files that can't be processed
                \Illuminate\Support\Facades\Log::warning("Could not process file {$filePath}: " . $e->getMessage());
            }
        }

        Notification::make()
            ->title('Storage Scan Complete')
            ->body("Added {$added} new file(s). Skipped {$skipped} existing file(s).")
            ->success()
            ->send();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MediaLibraryStatsWidget::class,
        ];
    }
}
