<?php

namespace App\Traits;

use App\Models\MediaLibrary;
use Illuminate\Support\Facades\Storage;

/**
 * Trait TracksMediaLibrary
 *
 * Add this trait to models that have media fields to automatically
 * track uploads in the MediaLibrary.
 *
 * Usage:
 * 1. Add the trait to your model: use TracksMediaLibrary;
 * 2. Define the $mediaFields property with field names to track:
 *    protected array $mediaFields = ['poster_url', 'hero_image_url'];
 * 3. Optionally define $mediaCollection for categorization:
 *    protected string $mediaCollection = 'events';
 */
trait TracksMediaLibrary
{
    /**
     * Boot the trait
     */
    public static function bootTracksMediaLibrary(): void
    {
        // Track media on create
        static::created(function ($model) {
            $model->syncMediaToLibrary();
        });

        // Track media on update
        static::updated(function ($model) {
            $model->syncMediaToLibrary();
        });

        // Remove media associations on delete
        static::deleted(function ($model) {
            $model->removeMediaFromLibrary();
        });
    }

    /**
     * Get the media fields to track
     */
    public function getMediaFields(): array
    {
        return $this->mediaFields ?? [];
    }

    /**
     * Get the media collection name for this model
     */
    public function getMediaCollection(): string
    {
        return $this->mediaCollection ?? strtolower(class_basename($this));
    }

    /**
     * Get the storage disk for media
     */
    public function getMediaDisk(): string
    {
        return $this->mediaDisk ?? 'public';
    }

    /**
     * Get marketplace client ID if applicable
     */
    public function getMediaMarketplaceClientId(): ?int
    {
        // Check common column names for marketplace association
        if (isset($this->marketplace_client_id)) {
            return $this->marketplace_client_id;
        }

        if (isset($this->marketplaceClient) && $this->marketplaceClient) {
            return $this->marketplaceClient->id;
        }

        return null;
    }

    /**
     * Sync all media fields to the library
     */
    public function syncMediaToLibrary(): void
    {
        $mediaFields = $this->getMediaFields();

        if (empty($mediaFields)) {
            return;
        }

        $collection = $this->getMediaCollection();
        $disk = $this->getMediaDisk();
        $marketplaceId = $this->getMediaMarketplaceClientId();

        foreach ($mediaFields as $field) {
            $path = $this->getAttribute($field);

            if (empty($path)) {
                continue;
            }

            // Skip if it's a full URL (external)
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                continue;
            }

            // Check if already tracked
            $exists = MediaLibrary::where('path', $path)
                ->where('disk', $disk)
                ->exists();

            if ($exists) {
                // Update association if needed
                MediaLibrary::where('path', $path)
                    ->where('disk', $disk)
                    ->whereNull('model_type')
                    ->update([
                        'model_type' => get_class($this),
                        'model_id' => $this->getKey(),
                        'collection' => $collection,
                    ]);
                continue;
            }

            // Create new record if file exists
            if (Storage::disk($disk)->exists($path)) {
                try {
                    MediaLibrary::createFromPath(
                        path: $path,
                        disk: $disk,
                        collection: $collection,
                        marketplaceClientId: $marketplaceId,
                        modelType: get_class($this),
                        modelId: $this->getKey()
                    );
                } catch (\Throwable $e) {
                    // Log but don't fail the model save
                    \Illuminate\Support\Facades\Log::warning(
                        "Failed to track media for " . get_class($this) . "#{$this->getKey()}: " . $e->getMessage()
                    );
                }
            }
        }
    }

    /**
     * Remove media associations when model is deleted
     * Note: This doesn't delete the actual files, just removes the association
     */
    public function removeMediaFromLibrary(): void
    {
        MediaLibrary::where('model_type', get_class($this))
            ->where('model_id', $this->getKey())
            ->update([
                'model_type' => null,
                'model_id' => null,
            ]);
    }

    /**
     * Get all media library records associated with this model
     */
    public function mediaLibrary()
    {
        return $this->morphMany(MediaLibrary::class, 'model');
    }

    /**
     * Manually track a specific file path
     */
    public function trackMedia(string $path, ?string $collection = null): ?MediaLibrary
    {
        $disk = $this->getMediaDisk();

        if (!Storage::disk($disk)->exists($path)) {
            return null;
        }

        // Check if already tracked
        $existing = MediaLibrary::where('path', $path)
            ->where('disk', $disk)
            ->first();

        if ($existing) {
            $existing->update([
                'model_type' => get_class($this),
                'model_id' => $this->getKey(),
                'collection' => $collection ?? $this->getMediaCollection(),
            ]);
            return $existing;
        }

        return MediaLibrary::createFromPath(
            path: $path,
            disk: $disk,
            collection: $collection ?? $this->getMediaCollection(),
            marketplaceClientId: $this->getMediaMarketplaceClientId(),
            modelType: get_class($this),
            modelId: $this->getKey()
        );
    }
}
