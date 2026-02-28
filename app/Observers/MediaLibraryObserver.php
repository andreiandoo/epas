<?php

namespace App\Observers;

use App\Models\MediaLibrary;
use App\Services\Media\ImageCompressionService;
use Illuminate\Support\Facades\Log;

class MediaLibraryObserver
{
    /**
     * Default compression quality for auto-compression
     */
    protected int $defaultQuality = 80;

    /**
     * Maximum dimension for auto-resize (null = no resize)
     */
    protected ?int $maxDimension = 1920;

    /**
     * Minimum file size (in bytes) to trigger compression
     * Files smaller than this won't be compressed
     */
    protected int $minSizeForCompression = 100 * 1024; // 100KB

    /**
     * Handle the MediaLibrary "created" event.
     * Automatically compress images when they are added to the library.
     */
    public function created(MediaLibrary $mediaLibrary): void
    {
        // Only process images
        if (!$mediaLibrary->is_image) {
            return;
        }

        // Skip if already compressed (shouldn't happen on create, but just in case)
        if (isset($mediaLibrary->metadata['compressed_at'])) {
            return;
        }

        // Skip small files - compression won't save much
        if ($mediaLibrary->size < $this->minSizeForCompression) {
            Log::info("MediaLibrary: Skipping compression for {$mediaLibrary->filename} - file too small ({$mediaLibrary->human_readable_size})");
            return;
        }

        // Skip certain formats that don't compress well
        $skipFormats = ['image/svg+xml', 'image/x-icon', 'image/ico'];
        if (in_array($mediaLibrary->mime_type, $skipFormats)) {
            return;
        }

        try {
            $service = new ImageCompressionService();
            $service->quality($this->defaultQuality);
            $service->convertToWebp(true);

            if ($this->maxDimension) {
                $service->maxDimension($this->maxDimension);
            }

            $result = $service->compress($mediaLibrary);

            if ($result->success) {
                Log::info("MediaLibrary: Auto-compressed {$mediaLibrary->filename} - saved {$result->savedPercentage}% ({$result->savedHuman})");
            } else {
                Log::warning("MediaLibrary: Failed to auto-compress {$mediaLibrary->filename}: {$result->error}");
            }
        } catch (\Throwable $e) {
            Log::error("MediaLibrary: Error during auto-compression of {$mediaLibrary->filename}: " . $e->getMessage());
        }
    }

    /**
     * Handle the MediaLibrary "updated" event.
     */
    public function updated(MediaLibrary $mediaLibrary): void
    {
        // If the path changed (file was replaced), compress the new file
        if ($mediaLibrary->isDirty('path') && $mediaLibrary->is_image) {
            // Reset compression metadata since this is a new file
            $metadata = $mediaLibrary->metadata ?? [];
            unset($metadata['compressed_at']);
            unset($metadata['original_size']);
            unset($metadata['saved_bytes']);
            unset($metadata['saved_percentage']);

            // Update without triggering observer again
            MediaLibrary::withoutEvents(function () use ($mediaLibrary, $metadata) {
                $mediaLibrary->update(['metadata' => $metadata]);
            });

            // Trigger compression for the new file
            $this->created($mediaLibrary);
        }
    }

    /**
     * Handle the MediaLibrary "deleted" event.
     */
    public function deleted(MediaLibrary $mediaLibrary): void
    {
        // Optionally delete the actual file from storage
        // Uncomment if you want files to be deleted when records are removed
        /*
        if ($mediaLibrary->existsOnDisk()) {
            try {
                Storage::disk($mediaLibrary->disk)->delete($mediaLibrary->path);
                Log::info("MediaLibrary: Deleted file {$mediaLibrary->path} from disk");
            } catch (\Throwable $e) {
                Log::warning("MediaLibrary: Failed to delete file {$mediaLibrary->path}: " . $e->getMessage());
            }
        }
        */
    }
}
