<?php

namespace App\Console\Commands;

use App\Models\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ScanMediaLibrary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:scan
                            {--disk=public : The storage disk to scan}
                            {--directory= : Specific directory to scan (optional)}
                            {--marketplace= : Marketplace client ID to associate files with}
                            {--cleanup : Remove orphaned database records}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan storage for media files and sync with the media library database';

    /**
     * Supported media MIME types
     */
    protected array $supportedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/bmp',
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = $this->option('disk');
        $directory = $this->option('directory');
        $marketplaceId = $this->option('marketplace');
        $cleanup = $this->option('cleanup');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $storage = Storage::disk($disk);

        // Get files to scan
        $this->info("📂 Scanning disk: {$disk}");

        if ($directory) {
            $this->info("📁 Directory: {$directory}");
            if (!$storage->exists($directory)) {
                $this->error("Directory does not exist: {$directory}");
                return self::FAILURE;
            }
            $files = $storage->allFiles($directory);
        } else {
            $files = $storage->allFiles();
        }

        $this->info("📊 Found " . count($files) . " files to process");
        $this->newLine();

        // Get existing paths for efficiency
        $query = MediaLibrary::where('disk', $disk);
        if ($marketplaceId) {
            $query->where('marketplace_client_id', $marketplaceId);
        }
        $existingPaths = $query->pluck('path')->flip()->toArray();

        $added = 0;
        $skipped = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();

        foreach ($files as $filePath) {
            $progressBar->advance();

            // Skip hidden files
            if (str_starts_with(basename($filePath), '.')) {
                continue;
            }

            // Skip if already in library
            if (isset($existingPaths[$filePath])) {
                $skipped++;
                continue;
            }

            try {
                $mimeType = $storage->mimeType($filePath);

                // Check if it's a supported media type
                $isMedia = $this->isSupportedMediaType($mimeType);

                if (!$isMedia) {
                    continue;
                }

                if (!$dryRun) {
                    MediaLibrary::createFromPath(
                        path: $filePath,
                        disk: $disk,
                        marketplaceClientId: $marketplaceId ? (int) $marketplaceId : null
                    );
                }
                $added++;
            } catch (\Throwable $e) {
                $errors++;
                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->warn("⚠️  Error processing {$filePath}: " . $e->getMessage());
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("✅ Scan complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files scanned', count($files)],
                ['Added to library', $added],
                ['Already in library', $skipped],
                ['Errors', $errors],
            ]
        );

        // Cleanup orphaned records
        if ($cleanup) {
            $this->newLine();
            $this->info("🧹 Running cleanup...");

            $orphaned = $this->cleanupOrphanedRecords($disk, $marketplaceId, $dryRun);

            $this->info("Removed {$orphaned} orphaned record(s)");
        }

        return self::SUCCESS;
    }

    /**
     * Check if MIME type is supported
     */
    protected function isSupportedMediaType(?string $mimeType): bool
    {
        if (empty($mimeType)) {
            return false;
        }

        // Check exact match
        if (in_array($mimeType, $this->supportedMimeTypes)) {
            return true;
        }

        // Check by prefix
        return str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/');
    }

    /**
     * Remove database records for files that no longer exist
     */
    protected function cleanupOrphanedRecords(string $disk, ?string $marketplaceId, bool $dryRun): int
    {
        $query = MediaLibrary::where('disk', $disk);

        if ($marketplaceId) {
            $query->where('marketplace_client_id', $marketplaceId);
        }

        $orphaned = 0;
        $storage = Storage::disk($disk);

        $query->chunk(100, function ($records) use ($storage, $dryRun, &$orphaned) {
            foreach ($records as $record) {
                if (!$storage->exists($record->path)) {
                    if (!$dryRun) {
                        $record->delete();
                    }
                    $orphaned++;
                }
            }
        });

        return $orphaned;
    }
}
