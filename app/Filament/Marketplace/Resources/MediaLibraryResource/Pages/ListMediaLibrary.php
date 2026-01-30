<?php

namespace App\Filament\Marketplace\Resources\MediaLibraryResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\MediaLibraryResource;
use App\Filament\Marketplace\Widgets\MediaLibraryStatsWidget;
use App\Models\MediaLibrary;
use App\Services\Media\ImageCompressionService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class ListMediaLibrary extends ListRecords
{
    use HasMarketplaceContext;

    protected static string $resource = MediaLibraryResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Media Library';
    }

    protected function getHeaderActions(): array
    {
        $marketplace = static::getMarketplaceClient();

        return [
            // Upload Action
            Actions\Action::make('upload')
                ->label('Încarcă Fișiere')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('files')
                        ->label('Selectează Fișiere')
                        ->multiple()
                        ->disk('public')
                        ->directory('marketplace/' . ($marketplace?->id ?? 'default') . '/uploads/' . now()->format('Y/m'))
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
                        ->helperText('Încarcă imagini, video-uri, PDF-uri sau documente (max 10MB fiecare)')
                        ->required(),

                    \Filament\Forms\Components\Select::make('collection')
                        ->label('Colecție')
                        ->options([
                            'uploads' => 'Încărcări Generale',
                            'artists' => 'Artiști',
                            'events' => 'Evenimente',
                            'products' => 'Produse',
                            'blog' => 'Blog',
                            'gallery' => 'Galerie',
                            'documents' => 'Documente',
                        ])
                        ->default('uploads'),
                ])
                ->action(function (array $data) use ($marketplace) {
                    $files = $data['files'] ?? [];
                    $collection = $data['collection'] ?? 'uploads';
                    $uploaded = 0;

                    foreach ($files as $filePath) {
                        try {
                            MediaLibrary::createFromPath(
                                path: $filePath,
                                disk: 'public',
                                collection: $collection,
                                marketplaceClientId: $marketplace?->id,
                                uploadedBy: auth()->id()
                            );
                            $uploaded++;
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Failed to create media record for {$filePath}: " . $e->getMessage());
                        }
                    }

                    Notification::make()
                        ->title('Fișiere Încărcate')
                        ->body("S-au încărcat cu succes {$uploaded} fișier(e) în biblioteca media.")
                        ->success()
                        ->send();
                }),

            // Compress Images Action
            Actions\Action::make('compress_images')
                ->label('Comprimă Imagini')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Comprimă Toate Imaginile Necompresate')
                ->modalDescription('Aceasta va comprima toate imaginile care nu au fost încă comprimate. Acest lucru poate dura ceva timp în funcție de numărul de imagini.')
                ->modalIcon('heroicon-o-bolt')
                ->form([
                    \Filament\Forms\Components\TextInput::make('quality')
                        ->label('Calitate Compresie')
                        ->numeric()
                        ->default(80)
                        ->minValue(1)
                        ->maxValue(100)
                        ->suffix('%')
                        ->helperText('O calitate mai mare înseamnă fișiere mai mari dar imagini mai clare.'),

                    \Filament\Forms\Components\TextInput::make('max_dimension')
                        ->label('Dimensiune Maximă (opțional)')
                        ->numeric()
                        ->placeholder('ex: 1920')
                        ->suffix('px')
                        ->helperText('Redimensionează imaginile mai mari decât această dimensiune.'),
                ])
                ->action(function (array $data) use ($marketplace) {
                    $this->compressImages($marketplace?->id, $data);
                }),

            // Scan Storage Action
            Actions\Action::make('scan_storage')
                ->label('Scanează')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Scanează Storage pentru Fișiere Media')
                ->modalDescription('Aceasta va scana directorul de storage pentru fișiere media și va adăuga fișierele noi în bibliotecă. Fișierele existente vor fi ignorate.')
                ->modalIcon('heroicon-o-magnifying-glass')
                ->action(function () use ($marketplace) {
                    $this->scanStorageForMedia($marketplace?->id);
                }),

            // Cleanup Missing Files
            Actions\Action::make('cleanup')
                ->label('Curăță')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Curăță Fișierele Lipsă')
                ->modalDescription('Aceasta va elimina înregistrările din baza de date pentru fișierele care nu mai există pe disc.')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->action(function () use ($marketplace) {
                    $deleted = 0;

                    MediaLibrary::query()
                        ->where('marketplace_client_id', $marketplace?->id)
                        ->chunk(100, function ($records) use (&$deleted) {
                            foreach ($records as $record) {
                                if (!$record->existsOnDisk()) {
                                    $record->delete();
                                    $deleted++;
                                }
                            }
                        });

                    Notification::make()
                        ->title('Curățare Completă')
                        ->body("S-au eliminat {$deleted} înregistrări orfane.")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Compress uncompressed images in the library
     */
    protected function compressImages(?int $marketplaceId, array $options): void
    {
        $quality = (int) ($options['quality'] ?? 80);
        $maxDimension = !empty($options['max_dimension']) ? (int) $options['max_dimension'] : null;

        $service = new ImageCompressionService();
        $service->quality($quality);

        if ($maxDimension) {
            $service->maxDimension($maxDimension);
        }

        // Get uncompressed images
        $images = MediaLibrary::query()
            ->where('marketplace_client_id', $marketplaceId)
            ->where('mime_type', 'LIKE', 'image/%')
            ->where(function ($q) {
                $q->whereNull('metadata')
                    ->orWhereRaw("JSON_EXTRACT(metadata, '$.compressed_at') IS NULL");
            })
            ->get();

        if ($images->isEmpty()) {
            Notification::make()
                ->title('Fără Imagini de Comprimat')
                ->body('Toate imaginile au fost deja compresate.')
                ->info()
                ->send();
            return;
        }

        $results = $service->compressMany($images);
        $stats = ImageCompressionService::getStatistics($results);

        Notification::make()
            ->title('Compresie Completă')
            ->body("Procesate: {$stats['successful']}/{$stats['total_processed']}. Spațiu salvat: {$stats['total_saved_human']} ({$stats['total_saved_percentage']}%)")
            ->success()
            ->send();
    }

    /**
     * Scan storage directory for media files
     */
    protected function scanStorageForMedia(?int $marketplaceId): void
    {
        $disk = Storage::disk('public');

        // Scan marketplace-specific directory
        $baseDir = 'marketplace/' . ($marketplaceId ?? 'default');
        $files = [];

        if ($disk->exists($baseDir)) {
            $files = $disk->allFiles($baseDir);
        }

        $added = 0;
        $skipped = 0;

        // Get all existing paths in a single query for efficiency
        $existingPaths = MediaLibrary::where('disk', 'public')
            ->where('marketplace_client_id', $marketplaceId)
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

                MediaLibrary::createFromPath(
                    path: $filePath,
                    disk: 'public',
                    marketplaceClientId: $marketplaceId
                );
                $added++;
            } catch (\Throwable $e) {
                // Skip files that can't be processed
                \Illuminate\Support\Facades\Log::warning("Could not process file {$filePath}: " . $e->getMessage());
            }
        }

        Notification::make()
            ->title('Scanare Completă')
            ->body("S-au adăugat {$added} fișier(e) noi. S-au ignorat {$skipped} fișier(e) existente.")
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
