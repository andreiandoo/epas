<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ConvertStorageToWebp extends Command
{
    protected $signature = 'storage:convert-webp
                            {--quality=80 : WebP compression quality (1-100)}
                            {--path= : Specific subdirectory to scan (e.g. events/hero)}
                            {--dry-run : Show what would be done without making changes}
                            {--force : Re-convert even if .webp version already exists}';

    protected $description = 'Convert JPG/PNG images in storage/app/public to WebP format for nginx auto-serve';

    public function handle(): int
    {
        $quality = (int) $this->option('quality');
        $subPath = $this->option('path');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $disk = Storage::disk('public');
        $basePath = $subPath ?: '';

        $this->info('WebP Storage Converter');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->line("Quality: {$quality}");
        $this->line("Path: storage/app/public/" . ($basePath ?: '(all)'));
        $this->newLine();

        // Collect all JPG/PNG files
        $files = collect($disk->allFiles($basePath))
            ->filter(function ($file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                return in_array($ext, ['jpg', 'jpeg', 'png']);
            })
            ->filter(function ($file) {
                // Skip files that are backups (contain _original)
                return !str_contains($file, '_original.');
            })
            ->values();

        $this->info("Found {$files->count()} JPG/PNG files to process.");
        $this->newLine();

        if ($files->isEmpty()) {
            $this->info('Nothing to convert.');
            return 0;
        }

        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $savedBytes = 0;

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        foreach ($files as $file) {
            // Create .webp alongside original: image.jpg → image.jpg.webp
            // This matches nginx try_files pattern: $request_filename.webp
            $webpPath = $file . '.webp';

            // Skip if .webp already exists (unless --force)
            if (!$force && $disk->exists($webpPath)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $size = $disk->size($file);
                $this->newLine();
                $this->line("  Would convert: {$file} (" . $this->humanSize($size) . ")");
                $converted++;
                $bar->advance();
                continue;
            }

            try {
                $fullPath = $disk->path($file);
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                // Create GD image from source
                $image = match ($ext) {
                    'jpg', 'jpeg' => @imagecreatefromjpeg($fullPath),
                    'png' => @imagecreatefrompng($fullPath),
                    default => false,
                };

                if (!$image) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                // Preserve alpha for PNG
                if ($ext === 'png') {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }

                // Save as WebP
                $webpFullPath = $disk->path($webpPath);

                // Ensure directory exists
                $dir = dirname($webpFullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $result = imagewebp($image, $webpFullPath, $quality);
                imagedestroy($image);

                if ($result && file_exists($webpFullPath)) {
                    $originalSize = filesize($fullPath);
                    $webpSize = filesize($webpFullPath);

                    // Only keep WebP if it's actually smaller
                    if ($webpSize < $originalSize) {
                        $savedBytes += ($originalSize - $webpSize);
                        $converted++;
                    } else {
                        // WebP is larger — delete it, keep original
                        unlink($webpFullPath);
                        $skipped++;
                    }
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("  Failed: {$file} - {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Results:");
        $this->line("  Converted: {$converted}");
        $this->line("  Skipped (already exists or larger): {$skipped}");
        if ($failed > 0) {
            $this->warn("  Failed: {$failed}");
        }
        $this->line("  Space saved: {$this->humanSize($savedBytes)}");
        $this->newLine();

        if ($converted > 0 && !$dryRun) {
            $this->info('WebP files created alongside originals.');
            $this->line('Configure nginx to auto-serve WebP when browser supports it.');
            $this->line('See: epas/nginx-storage-cache.conf (WebP auto-serve section)');
        }

        return 0;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
