<?php

namespace App\Console\Commands;

use App\Models\MediaLibrary;
use App\Services\Media\ImageCompressionService;
use Illuminate\Console\Command;

class CompressMediaImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:compress
                            {--quality=80 : Compression quality (1-100)}
                            {--max-dimension= : Maximum width/height in pixels}
                            {--webp : Convert images to WebP format}
                            {--keep-original : Keep original files as backups}
                            {--min-size=100 : Minimum file size in KB to compress}
                            {--collection= : Only compress files in this collection}
                            {--marketplace= : Only compress files for this marketplace}
                            {--uncompressed : Only compress files that haven\'t been compressed yet}
                            {--ids= : Comma-separated list of media IDs to compress}
                            {--dry-run : Show what would be done without making changes}
                            {--limit= : Limit number of files to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compress images in the media library to reduce file sizes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $quality = (int) $this->option('quality');
        $maxDimension = $this->option('max-dimension') ? (int) $this->option('max-dimension') : null;
        $convertToWebp = $this->option('webp');
        $keepOriginal = $this->option('keep-original');
        $minSizeKb = (int) $this->option('min-size');
        $collection = $this->option('collection');
        $marketplaceId = $this->option('marketplace');
        $uncompressedOnly = $this->option('uncompressed');
        $ids = $this->option('ids');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // Display settings
        $this->info('🖼️  Media Image Compression');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->table(['Setting', 'Value'], [
            ['Quality', "{$quality}%"],
            ['Max Dimension', $maxDimension ? "{$maxDimension}px" : 'None'],
            ['Convert to WebP', $convertToWebp ? 'Yes' : 'No'],
            ['Keep Original', $keepOriginal ? 'Yes' : 'No'],
            ['Min Size', "{$minSizeKb} KB"],
        ]);

        $this->newLine();

        // Build query
        $query = MediaLibrary::query()
            ->where('mime_type', 'LIKE', 'image/%')
            ->where('size', '>=', $minSizeKb * 1024); // Convert KB to bytes

        if ($collection) {
            $query->where('collection', $collection);
            $this->info("📁 Collection: {$collection}");
        }

        if ($marketplaceId) {
            $query->where('marketplace_client_id', $marketplaceId);
            $this->info("🏪 Marketplace ID: {$marketplaceId}");
        }

        if ($uncompressedOnly) {
            $query->where(function ($q) {
                $q->whereNull('metadata')
                  ->orWhereRaw("JSON_EXTRACT(metadata, '$.compressed_at') IS NULL");
            });
            $this->info("📦 Only uncompressed files");
        }

        if ($ids) {
            $idList = array_map('intval', explode(',', $ids));
            $query->whereIn('id', $idList);
            $this->info("🔢 Specific IDs: " . implode(', ', $idList));
        }

        if ($limit) {
            $query->limit($limit);
            $this->info("📊 Limit: {$limit} files");
        }

        $query->orderBy('size', 'desc'); // Process largest files first

        $count = $query->count();

        if ($count === 0) {
            $this->info('✨ No images found matching the criteria.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("🔍 Found {$count} image(s) to process");

        if (!$dryRun && !$this->confirm('Do you want to proceed with compression?', true)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        // Initialize compression service
        $service = new ImageCompressionService();
        $service->quality($quality);

        if ($maxDimension) {
            $service->maxDimension($maxDimension);
        }

        if ($convertToWebp) {
            $service->convertToWebp();
        }

        if ($keepOriginal) {
            $service->keepOriginal();
        }

        $this->newLine();
        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $results = [];

        $query->chunk(50, function ($records) use ($service, $progressBar, &$results, $dryRun) {
            foreach ($records as $media) {
                if ($dryRun) {
                    // Simulate result for dry run
                    $result = new \App\Services\Media\CompressionResult();
                    $result->mediaId = $media->id;
                    $result->originalPath = $media->path;
                    $result->originalSize = $media->size;
                    $result->success = true;
                    $result->newSize = (int) ($media->size * 0.7); // Estimate 30% reduction
                    $result->savedBytes = $media->size - $result->newSize;
                    $result->savedPercentage = 30;
                    $results[] = $result;
                } else {
                    $results[] = $service->compress($media);
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Show results
        $stats = ImageCompressionService::getStatistics($results);

        $this->info('✅ Compression complete!');
        $this->newLine();

        $this->table(['Metric', 'Value'], [
            ['Total processed', $stats['total_processed']],
            ['Successful', $stats['successful']],
            ['Skipped', $stats['skipped']],
            ['Failed', $stats['failed']],
            ['Original total size', ImageCompressionService::formatBytes($stats['total_original_size'])],
            ['New total size', ImageCompressionService::formatBytes($stats['total_new_size'])],
            ['Space saved', $stats['total_saved_human']],
            ['Reduction', $stats['total_saved_percentage'] . '%'],
        ]);

        // Show errors if any
        $errors = array_filter($results, fn ($r) => !$r->success);
        if (count($errors) > 0 && $this->output->isVerbose()) {
            $this->newLine();
            $this->warn('⚠️  Errors:');
            foreach ($errors as $error) {
                $this->line("  - Media #{$error->mediaId}: {$error->error}");
            }
        }

        return self::SUCCESS;
    }
}
