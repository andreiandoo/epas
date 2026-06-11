<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ResizeStorageImages extends Command
{
    protected $signature = 'storage:resize-images
                            {--max=1920 : Maximum width or height in pixels}
                            {--quality=85 : JPEG/WebP output quality (1-100)}
                            {--path= : Specific subdirectory to scan (e.g. events/hero)}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Downscale oversized images in storage/app/public to a max dimension (preserves aspect ratio)';

    public function handle(): int
    {
        $maxDim = (int) $this->option('max');
        $quality = (int) $this->option('quality');
        $subPath = $this->option('path');
        $dryRun = $this->option('dry-run');

        $disk = Storage::disk('public');
        $basePath = $subPath ?: '';

        $this->info('Storage Image Resizer');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->line("Max dimension: {$maxDim}px");
        $this->line("Quality: {$quality}");
        $this->line("Path: storage/app/public/" . ($basePath ?: '(all)'));
        $this->newLine();

        // Collect all image files
        $files = collect($disk->allFiles($basePath))
            ->filter(function ($file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                return in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
            })
            ->filter(function ($file) {
                return !str_contains($file, '_original.')
                    && !str_contains($file, 'livewire-tmp/');
            })
            ->values();

        $this->info("Found {$files->count()} image files to check.");
        $this->newLine();

        if ($files->isEmpty()) {
            $this->info('Nothing to process.');
            return 0;
        }

        $resized = 0;
        $skipped = 0;
        $failed = 0;
        $savedBytes = 0;

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        foreach ($files as $file) {
            try {
                $fullPath = $disk->path($file);
                $size = @getimagesize($fullPath);

                if (!$size) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                [$width, $height] = $size;

                // Skip if already within max dimension
                if ($width <= $maxDim && $height <= $maxDim) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if ($dryRun) {
                    $fileSize = filesize($fullPath);
                    $this->newLine();
                    $this->line("  Would resize: {$file} ({$width}x{$height} → max {$maxDim}px, " . $this->humanSize($fileSize) . ")");
                    $resized++;
                    $bar->advance();
                    continue;
                }

                $originalFileSize = filesize($fullPath);
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                // Load image
                $image = match ($ext) {
                    'jpg', 'jpeg' => @imagecreatefromjpeg($fullPath),
                    'png' => @imagecreatefrompng($fullPath),
                    'webp' => @imagecreatefromwebp($fullPath),
                    default => false,
                };

                if (!$image) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                // Calculate new dimensions preserving aspect ratio
                $ratio = $width / $height;
                if ($width > $height) {
                    $newWidth = $maxDim;
                    $newHeight = (int) round($maxDim / $ratio);
                } else {
                    $newHeight = $maxDim;
                    $newWidth = (int) round($maxDim * $ratio);
                }

                // Create resized image
                $resizedImg = imagecreatetruecolor($newWidth, $newHeight);

                // Preserve transparency for PNG/WebP
                if (in_array($ext, ['png', 'webp'])) {
                    imagealphablending($resizedImg, false);
                    imagesavealpha($resizedImg, true);
                    $transparent = imagecolorallocatealpha($resizedImg, 0, 0, 0, 127);
                    imagefill($resizedImg, 0, 0, $transparent);
                }

                imagecopyresampled($resizedImg, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);

                // Save back to same file
                $result = match ($ext) {
                    'jpg', 'jpeg' => imagejpeg($resizedImg, $fullPath, $quality),
                    'png' => imagepng($resizedImg, $fullPath, (int) ((100 - $quality) / 10)),
                    'webp' => imagewebp($resizedImg, $fullPath, $quality),
                    default => false,
                };
                imagedestroy($resizedImg);

                if ($result) {
                    clearstatcache(true, $fullPath);
                    $newFileSize = filesize($fullPath);
                    $saved = $originalFileSize - $newFileSize;
                    if ($saved > 0) {
                        $savedBytes += $saved;
                    }
                    $resized++;

                    // Also regenerate .webp sidecar if it exists
                    $webpSidecar = $fullPath . '.webp';
                    if (file_exists($webpSidecar)) {
                        $reloadedImg = match ($ext) {
                            'jpg', 'jpeg' => @imagecreatefromjpeg($fullPath),
                            'png' => @imagecreatefrompng($fullPath),
                            'webp' => @imagecreatefromwebp($fullPath),
                            default => false,
                        };
                        if ($reloadedImg) {
                            if (in_array($ext, ['png', 'webp'])) {
                                imagesavealpha($reloadedImg, true);
                            }
                            imagewebp($reloadedImg, $webpSidecar, $quality);
                            imagedestroy($reloadedImg);
                        }
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
        $this->line("  Resized: {$resized}");
        $this->line("  Skipped (already within {$maxDim}px): {$skipped}");
        if ($failed > 0) {
            $this->warn("  Failed: {$failed}");
        }
        $this->line("  Space saved: {$this->humanSize($savedBytes)}");

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
