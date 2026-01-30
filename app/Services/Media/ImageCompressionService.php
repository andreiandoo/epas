<?php

namespace App\Services\Media;

use App\Models\MediaLibrary;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImageCompressionService
{
    /**
     * Default compression quality (0-100)
     */
    protected int $defaultQuality = 80;

    /**
     * Maximum dimension for resizing (null = no resize)
     */
    protected ?int $maxDimension = null;

    /**
     * Whether to convert to WebP format
     */
    protected bool $convertToWebp = false;

    /**
     * Whether to keep original file as backup
     */
    protected bool $keepOriginal = false;

    /**
     * Supported image types for compression
     */
    protected array $supportedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
    ];

    /**
     * Set compression quality
     */
    public function quality(int $quality): self
    {
        $this->defaultQuality = max(1, min(100, $quality));
        return $this;
    }

    /**
     * Set maximum dimension (will resize proportionally)
     */
    public function maxDimension(?int $dimension): self
    {
        $this->maxDimension = $dimension;
        return $this;
    }

    /**
     * Enable WebP conversion
     */
    public function convertToWebp(bool $convert = true): self
    {
        $this->convertToWebp = $convert;
        return $this;
    }

    /**
     * Keep original file as backup
     */
    public function keepOriginal(bool $keep = true): self
    {
        $this->keepOriginal = $keep;
        return $this;
    }

    /**
     * Compress a single MediaLibrary record
     */
    public function compress(MediaLibrary $media): CompressionResult
    {
        $result = new CompressionResult();
        $result->mediaId = $media->id;
        $result->originalPath = $media->path;
        $result->originalSize = $media->size;

        // Check if it's an image
        if (!$media->is_image) {
            $result->success = false;
            $result->error = 'Not an image file';
            return $result;
        }

        // Check if MIME type is supported
        if (!in_array($media->mime_type, $this->supportedTypes)) {
            $result->success = false;
            $result->error = "Unsupported image type: {$media->mime_type}";
            return $result;
        }

        $disk = Storage::disk($media->disk);

        // Check if file exists
        if (!$disk->exists($media->path)) {
            $result->success = false;
            $result->error = 'File not found on disk';
            return $result;
        }

        try {
            // Get full path
            $fullPath = $disk->path($media->path);

            // Create backup if needed
            if ($this->keepOriginal) {
                $backupPath = $this->createBackup($disk, $media->path);
                $result->backupPath = $backupPath;
            }

            // Load image
            $image = $this->loadImage($fullPath, $media->mime_type);

            if (!$image) {
                $result->success = false;
                $result->error = 'Failed to load image';
                return $result;
            }

            // Get original dimensions
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            // Resize if needed
            if ($this->maxDimension && ($originalWidth > $this->maxDimension || $originalHeight > $this->maxDimension)) {
                $image = $this->resizeImage($image, $originalWidth, $originalHeight);
                $result->wasResized = true;
                $result->newWidth = imagesx($image);
                $result->newHeight = imagesy($image);
            }

            // Determine output format and path
            $outputPath = $media->path;
            $outputMimeType = $media->mime_type;

            if ($this->convertToWebp && $media->mime_type !== 'image/webp') {
                $outputPath = $this->changeExtension($media->path, 'webp');
                $outputMimeType = 'image/webp';
                $result->wasConverted = true;
                $result->newFormat = 'webp';
            }

            // Save compressed image
            $tempPath = tempnam(sys_get_temp_dir(), 'img_compress_');
            $this->saveImage($image, $tempPath, $outputMimeType);

            // Free memory
            imagedestroy($image);

            // Get new file size
            $newSize = filesize($tempPath);
            $result->newSize = $newSize;
            $result->savedBytes = $media->size - $newSize;
            $result->savedPercentage = $media->size > 0
                ? round((($media->size - $newSize) / $media->size) * 100, 2)
                : 0;

            // Only update if we actually saved space (or converted format)
            if ($newSize < $media->size || $this->convertToWebp) {
                // Delete old file if path changed
                if ($outputPath !== $media->path && $disk->exists($media->path) && !$this->keepOriginal) {
                    $disk->delete($media->path);
                }

                // Move compressed file to storage
                $disk->put($outputPath, file_get_contents($tempPath));

                // Update database record
                $media->update([
                    'path' => $outputPath,
                    'size' => $newSize,
                    'mime_type' => $outputMimeType,
                    'extension' => pathinfo($outputPath, PATHINFO_EXTENSION),
                    'filename' => basename($outputPath),
                    'width' => $result->newWidth ?? $originalWidth,
                    'height' => $result->newHeight ?? $originalHeight,
                    'metadata' => array_merge($media->metadata ?? [], [
                        'compressed_at' => now()->toIso8601String(),
                        'original_size' => $result->originalSize,
                        'saved_bytes' => $result->savedBytes,
                        'saved_percentage' => $result->savedPercentage,
                        'compression_quality' => $this->defaultQuality,
                        'was_resized' => $result->wasResized,
                        'was_converted' => $result->wasConverted,
                    ]),
                ]);

                $result->newPath = $outputPath;
                $result->success = true;
            } else {
                $result->success = true;
                $result->skipped = true;
                $result->skipReason = 'Compressed size is larger than original';
            }

            // Clean up temp file
            @unlink($tempPath);

        } catch (\Throwable $e) {
            $result->success = false;
            $result->error = $e->getMessage();

            Log::error("Image compression failed for media #{$media->id}: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Compress multiple MediaLibrary records
     */
    public function compressMany(iterable $mediaRecords, ?callable $progressCallback = null): array
    {
        $results = [];
        $total = is_countable($mediaRecords) ? count($mediaRecords) : iterator_count($mediaRecords);
        $current = 0;

        foreach ($mediaRecords as $media) {
            $current++;
            $results[] = $this->compress($media);

            if ($progressCallback) {
                $progressCallback($current, $total, $results[count($results) - 1]);
            }
        }

        return $results;
    }

    /**
     * Get compression statistics from results
     */
    public static function getStatistics(array $results): array
    {
        $successful = array_filter($results, fn ($r) => $r->success && !$r->skipped);
        $skipped = array_filter($results, fn ($r) => $r->skipped);
        $failed = array_filter($results, fn ($r) => !$r->success);

        $totalOriginalSize = array_sum(array_map(fn ($r) => $r->originalSize, $results));
        $totalNewSize = array_sum(array_map(fn ($r) => $r->newSize ?? $r->originalSize, $results));
        $totalSaved = array_sum(array_map(fn ($r) => $r->savedBytes ?? 0, $successful));

        return [
            'total_processed' => count($results),
            'successful' => count($successful),
            'skipped' => count($skipped),
            'failed' => count($failed),
            'total_original_size' => $totalOriginalSize,
            'total_new_size' => $totalNewSize,
            'total_saved_bytes' => $totalSaved,
            'total_saved_percentage' => $totalOriginalSize > 0
                ? round(($totalSaved / $totalOriginalSize) * 100, 2)
                : 0,
            'total_saved_human' => self::formatBytes($totalSaved),
        ];
    }

    /**
     * Load image from file
     */
    protected function loadImage(string $path, string $mimeType): ?\GdImage
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/bmp' => @imagecreatefrombmp($path),
            default => null,
        };
    }

    /**
     * Save image to file
     */
    protected function saveImage(\GdImage $image, string $path, string $mimeType): bool
    {
        // Preserve transparency for PNG and WebP
        if (in_array($mimeType, ['image/png', 'image/webp'])) {
            imagesavealpha($image, true);
            imagealphablending($image, false);
        }

        return match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, $this->defaultQuality),
            'image/png' => imagepng($image, $path, (int) ((100 - $this->defaultQuality) / 10)),
            'image/gif' => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, $this->defaultQuality),
            'image/bmp' => imagebmp($image, $path),
            default => false,
        };
    }

    /**
     * Resize image proportionally
     */
    protected function resizeImage(\GdImage $image, int $width, int $height): \GdImage
    {
        $ratio = $width / $height;

        if ($width > $height) {
            $newWidth = $this->maxDimension;
            $newHeight = (int) ($this->maxDimension / $ratio);
        } else {
            $newHeight = $this->maxDimension;
            $newWidth = (int) ($this->maxDimension * $ratio);
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        imagedestroy($image);

        return $resized;
    }

    /**
     * Create backup of original file
     */
    protected function createBackup($disk, string $path): string
    {
        $info = pathinfo($path);
        $backupPath = ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '')
            . $info['filename'] . '_original.' . $info['extension'];

        $disk->copy($path, $backupPath);

        return $backupPath;
    }

    /**
     * Change file extension
     */
    protected function changeExtension(string $path, string $newExtension): string
    {
        $info = pathinfo($path);
        return ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '')
            . $info['filename'] . '.' . $newExtension;
    }

    /**
     * Format bytes to human readable
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string) abs($bytes)) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}

/**
 * Result of a compression operation
 */
class CompressionResult
{
    public int $mediaId;
    public string $originalPath;
    public int $originalSize;
    public ?string $newPath = null;
    public ?int $newSize = null;
    public ?int $savedBytes = null;
    public ?float $savedPercentage = null;
    public bool $success = false;
    public bool $skipped = false;
    public ?string $skipReason = null;
    public ?string $error = null;
    public bool $wasResized = false;
    public ?int $newWidth = null;
    public ?int $newHeight = null;
    public bool $wasConverted = false;
    public ?string $newFormat = null;
    public ?string $backupPath = null;

    public function getSavedHuman(): string
    {
        return ImageCompressionService::formatBytes($this->savedBytes ?? 0);
    }
}
