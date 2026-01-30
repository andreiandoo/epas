<?php

namespace App\Models;

use App\Services\OrganizerNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class OrganizerDocument extends Model
{
    protected $table = 'organizer_documents';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'event_id',
        'tax_template_id',
        'title',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'document_data',
        'html_content',
        'issued_at',
    ];

    protected $casts = [
        'document_data' => 'array',
        'issued_at' => 'datetime',
        'file_size' => 'integer',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($document) {
            try {
                OrganizerNotificationService::notifyDocumentGenerated($document);
            } catch (\Exception $e) {
                Log::warning('Failed to send document notification', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Document types
     */
    public const TYPES = [
        'cerere_avizare' => 'Cerere avizare',
        'declaratie_impozite' => 'Declaratie impozite',
        'organizer_contract' => 'Contract organizator',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function taxTemplate(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTaxTemplate::class, 'tax_template_id');
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeForOrganizer($query, int $organizerId)
    {
        return $query->where('marketplace_organizer_id', $organizerId);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Get the document type label
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return self::TYPES[$this->document_type] ?? $this->document_type;
    }

    /**
     * Get the download URL
     */
    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return url('storage/' . $this->file_path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}
