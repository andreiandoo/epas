<?php

namespace App\Models;

use App\Observers\MediaLibraryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([MediaLibraryObserver::class])]
class MediaLibrary extends Model
{
    use HasFactory;

    protected $table = 'media_library';

    protected $fillable = [
        'filename',
        'original_filename',
        'path',
        'disk',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'collection',
        'directory',
        'model_type',
        'model_id',
        'marketplace_client_id',
        'metadata',
        'alt_text',
        'title',
        'uploaded_by',
        'file_created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'file_created_at' => 'datetime',
    ];

    protected $appends = ['url', 'human_readable_size', 'is_image'];

    /**
     * Get the full URL for the media file
     */
    public function getUrlAttribute(): ?string
    {
        if (empty($this->path)) {
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        // Use storage URL
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get human-readable file size
     */
    public function getHumanReadableSizeAttribute(): string
    {
        $bytes = $this->size;

        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Check if file is an image
     */
    public function getIsImageAttribute(): bool
    {
        if (empty($this->mime_type)) {
            return false;
        }

        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if file is a video
     */
    public function getIsVideoAttribute(): bool
    {
        if (empty($this->mime_type)) {
            return false;
        }

        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if file is a document
     */
    public function getIsDocumentAttribute(): bool
    {
        if (empty($this->mime_type)) {
            return false;
        }

        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ];

        return in_array($this->mime_type, $documentTypes);
    }

    /**
     * Get the associated model
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded this media
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the marketplace client
     */
    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Scope for images only
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'LIKE', 'image/%');
    }

    /**
     * Scope for videos only
     */
    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'LIKE', 'video/%');
    }

    /**
     * Scope for documents only
     */
    public function scopeDocuments($query)
    {
        return $query->whereIn('mime_type', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ]);
    }

    /**
     * Scope by collection
     */
    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    /**
     * Scope by marketplace client
     */
    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    /**
     * Scope by year
     */
    public function scopeInYear($query, int $year)
    {
        return $query->whereYear('created_at', $year);
    }

    /**
     * Scope by month
     */
    public function scopeInMonth($query, int $month)
    {
        return $query->whereMonth('created_at', $month);
    }

    /**
     * Scope by year and month
     */
    public function scopeInYearMonth($query, int $year, int $month)
    {
        return $query->whereYear('created_at', $year)
                     ->whereMonth('created_at', $month);
    }

    /**
     * Create a media record from an uploaded file path
     */
    public static function createFromPath(
        string $path,
        string $disk = 'public',
        ?string $collection = null,
        ?int $marketplaceClientId = null,
        ?int $uploadedBy = null,
        ?string $modelType = null,
        ?int $modelId = null
    ): static {
        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) {
            throw new \RuntimeException("File does not exist: {$path}");
        }

        $filename = basename($path);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeType = $storage->mimeType($path);
        $size = $storage->size($path);
        $directory = dirname($path);

        // Get image dimensions if it's an image
        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/')) {
            try {
                $fullPath = $storage->path($path);
                if (file_exists($fullPath)) {
                    $imageInfo = getimagesize($fullPath);
                    if ($imageInfo !== false) {
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];
                    }
                }
            } catch (\Throwable) {
                // Ignore errors getting image dimensions
            }
        }

        // Try to get file modification time
        $fileCreatedAt = null;
        try {
            $lastModified = $storage->lastModified($path);
            $fileCreatedAt = \Carbon\Carbon::createFromTimestamp($lastModified);
        } catch (\Throwable) {
            // Ignore errors getting file time
        }

        return static::create([
            'filename' => $filename,
            'original_filename' => $filename,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'collection' => $collection ?? self::detectCollection($path),
            'directory' => $directory !== '.' ? $directory : null,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'marketplace_client_id' => $marketplaceClientId,
            'uploaded_by' => $uploadedBy,
            'file_created_at' => $fileCreatedAt,
        ]);
    }

    /**
     * Detect collection from file path
     */
    protected static function detectCollection(string $path): ?string
    {
        $parts = explode('/', $path);

        if (count($parts) < 2) {
            return null;
        }

        // Common collection patterns
        $collections = [
            'artists' => 'artists',
            'events' => 'events',
            'products' => 'products',
            'venues' => 'venues',
            'settings' => 'settings',
            'blog' => 'blog',
            'shop' => 'shop',
            'tickets' => 'tickets',
            'organizers' => 'organizers',
            'brands' => 'brands',
            'logos' => 'logos',
            'hero' => 'heroes',
            'posters' => 'posters',
            'portraits' => 'portraits',
            'banners' => 'banners',
            'gallery' => 'gallery',
            'documents' => 'documents',
        ];

        foreach ($parts as $part) {
            $lowerPart = strtolower($part);
            if (isset($collections[$lowerPart])) {
                return $collections[$lowerPart];
            }
        }

        return $parts[0] ?? null;
    }

    /**
     * Check if file still exists on disk
     */
    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Delete file from disk and database
     */
    public function deleteWithFile(): bool
    {
        try {
            if ($this->existsOnDisk()) {
                Storage::disk($this->disk)->delete($this->path);
            }
            return $this->delete();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get thumbnail URL (for images)
     * Note: This returns the original URL; implement actual thumbnail generation if needed
     */
    public function getThumbnailUrl(int $width = 150, int $height = 150): ?string
    {
        // For now, return the original URL
        // In production, you might want to integrate with image manipulation libraries
        return $this->url;
    }
}
