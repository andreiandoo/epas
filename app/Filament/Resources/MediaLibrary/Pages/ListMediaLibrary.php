<?php

namespace App\Filament\Resources\MediaLibrary\Pages;

use App\Filament\Resources\MediaLibrary\MediaLibraryResource;
use App\Filament\Widgets\MediaLibraryStatsWidget;
use App\Models\MediaLibrary;
use App\Services\Media\ImageCompressionService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;

class ListMediaLibrary extends ListRecords
{
    protected static string $resource = MediaLibraryResource::class;

    #[Url]
    public string $viewMode = 'table';

    public function getTitle(): string|Htmlable
    {
        return 'Media Library';
    }

    protected function getHeaderActions(): array
    {
        return [
            // View Mode Toggle
            Actions\ActionGroup::make([
                Actions\Action::make('table_view')
                    ->label('Table View')
                    ->icon('heroicon-o-table-cells')
                    ->color($this->viewMode === 'table' ? 'primary' : 'gray')
                    ->action(fn () => $this->viewMode = 'table'),
                Actions\Action::make('grid_view')
                    ->label('Grid View')
                    ->icon('heroicon-o-squares-2x2')
                    ->color($this->viewMode === 'grid' ? 'primary' : 'gray')
                    ->action(fn () => $this->viewMode = 'grid'),
            ])
                ->label('View')
                ->icon('heroicon-o-eye')
                ->button(),

            // Compress Images Action
            Actions\Action::make('compress_images')
                ->label('Compress Images')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('quality')
                        ->label('Compression Quality')
                        ->options([
                            90 => 'High (90%) - Minimal compression',
                            80 => 'Medium (80%) - Recommended',
                            70 => 'Standard (70%) - Good balance',
                            60 => 'Low (60%) - Maximum compression',
                        ])
                        ->default(80)
                        ->required()
                        ->helperText('Lower quality = smaller file size'),

                    \Filament\Forms\Components\TextInput::make('max_dimension')
                        ->label('Max Dimension (px)')
                        ->numeric()
                        ->placeholder('Leave empty to keep original size')
                        ->helperText('Images larger than this will be resized proportionally'),

                    \Filament\Forms\Components\Toggle::make('convert_webp')
                        ->label('Convert to WebP')
                        ->helperText('WebP format provides better compression')
                        ->default(false),

                    \Filament\Forms\Components\Toggle::make('keep_original')
                        ->label('Keep Original Files')
                        ->helperText('Save original files with _original suffix')
                        ->default(false),

                    \Filament\Forms\Components\TextInput::make('min_size')
                        ->label('Minimum Size (KB)')
                        ->numeric()
                        ->default(100)
                        ->helperText('Only compress files larger than this'),

                    \Filament\Forms\Components\Select::make('scope')
                        ->label('Which Images')
                        ->options([
                            'uncompressed' => 'Only uncompressed images',
                            'all' => 'All images',
                        ])
                        ->default('uncompressed')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading('Compress Images')
                ->modalDescription('This will compress images to reduce file sizes. This operation cannot be undone unless you keep original files.')
                ->modalIcon('heroicon-o-arrow-down-on-square-stack')
                ->action(function (array $data) {
                    $this->compressImages($data);
                }),

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

                    \Filament\Forms\Components\Toggle::make('auto_compress')
                        ->label('Auto-compress images')
                        ->helperText('Automatically compress uploaded images')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $files = $data['files'] ?? [];
                    $collection = $data['collection'] ?? 'uploads';
                    $autoCompress = $data['auto_compress'] ?? false;
                    $uploaded = 0;
                    $compressed = 0;

                    $compressionService = new ImageCompressionService();
                    $compressionService->quality(80);

                    foreach ($files as $filePath) {
                        try {
                            $media = MediaLibrary::createFromPath(
                                path: $filePath,
                                disk: 'public',
                                collection: $collection,
                                uploadedBy: auth()->id()
                            );
                            $uploaded++;

                            // Auto-compress if enabled and is an image
                            if ($autoCompress && $media->is_image && $media->size > 100 * 1024) {
                                $result = $compressionService->compress($media);
                                if ($result->success && !$result->skipped) {
                                    $compressed++;
                                }
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Failed to create media record for {$filePath}: " . $e->getMessage());
                        }
                    }

                    $message = "Successfully uploaded {$uploaded} file(s) to the media library.";
                    if ($compressed > 0) {
                        $message .= " Compressed {$compressed} image(s).";
                    }

                    Notification::make()
                        ->title('Files Uploaded')
                        ->body($message)
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
     * Compress images based on settings
     */
    protected function compressImages(array $data): void
    {
        $quality = (int) $data['quality'];
        $maxDimension = !empty($data['max_dimension']) ? (int) $data['max_dimension'] : null;
        $convertWebp = $data['convert_webp'] ?? false;
        $keepOriginal = $data['keep_original'] ?? false;
        $minSize = ((int) ($data['min_size'] ?? 100)) * 1024; // Convert to bytes
        $scope = $data['scope'] ?? 'uncompressed';

        $query = MediaLibrary::query()
            ->where('mime_type', 'LIKE', 'image/%')
            ->where('size', '>=', $minSize);

        if ($scope === 'uncompressed') {
            $query->where(function ($q) {
                $q->whereNull('metadata')
                  ->orWhereRaw("JSON_EXTRACT(metadata, '$.compressed_at') IS NULL");
            });
        }

        $count = $query->count();

        if ($count === 0) {
            Notification::make()
                ->title('No Images to Compress')
                ->body('No images found matching the criteria.')
                ->info()
                ->send();
            return;
        }

        $service = new ImageCompressionService();
        $service->quality($quality);

        if ($maxDimension) {
            $service->maxDimension($maxDimension);
        }

        if ($convertWebp) {
            $service->convertToWebp();
        }

        if ($keepOriginal) {
            $service->keepOriginal();
        }

        $results = [];
        $query->chunk(50, function ($records) use ($service, &$results) {
            foreach ($records as $media) {
                $results[] = $service->compress($media);
            }
        });

        $stats = ImageCompressionService::getStatistics($results);

        Notification::make()
            ->title('Compression Complete')
            ->body(sprintf(
                'Processed %d images. Saved %s (%s%% reduction).',
                $stats['successful'],
                $stats['total_saved_human'],
                $stats['total_saved_percentage']
            ))
            ->success()
            ->send();
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

    /**
     * Get the view for the page based on view mode
     */
    public function getView(): string
    {
        if ($this->viewMode === 'grid') {
            return 'filament.resources.media-library.pages.list-media-library-grid';
        }

        return parent::getView();
    }
}
