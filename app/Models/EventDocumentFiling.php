<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fiscal document of an event filed with the city hall — emailed, or
 * confirmed as filed through a third-party solution or by hand.
 *
 * Rows are never deleted: a mistaken one is voided, and a document
 * regenerated after its filing simply stops being covered by it.
 */
class EventDocumentFiling extends Model
{
    public const METHOD_EMAIL = 'email';

    public const METHOD_THIRD_PARTY = 'third_party';

    /** The organizer filed it themselves and confirmed it from their account. */
    public const METHOD_ORGANIZER = 'organizer';

    /** An operator filed it outside the system and confirmed it here. */
    public const METHOD_MANUAL = 'manual';

    /** Registered copy: our PDF completed with the number and date. */
    public const REGISTERED_COMPLETED = 'completed';

    /** Registered copy: the PDF the city hall sent back. */
    public const REGISTERED_UPLOADED = 'uploaded';

    /** organizer_documents type of a document's registered copy. */
    public static function registeredType(string $type): string
    {
        return $type . '_inregistrat';
    }

    protected $fillable = [
        'marketplace_client_id',
        'event_id',
        'marketplace_tax_registry_id',
        'document_type',
        'document_generated_at',
        'file_name',
        'method',
        'sent_to',
        'third_party_name',
        'email_log_id',
        'registration_number',
        'registration_number_at',
        'registration_number_by_name',
        'registration_date',
        'registered_file_path',
        'registered_file_name',
        'registered_source',
        'registered_at',
        'registered_by_name',
        'registered_organizer_document_id',
        'filed_by_id',
        'filed_by_name',
        'filed_at',
        'voided_at',
        'voided_by_name',
    ];

    protected $casts = [
        'document_generated_at' => 'datetime',
        'registration_number_at' => 'datetime',
        'registration_date' => 'date',
        'registered_at' => 'datetime',
        'filed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registry(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTaxRegistry::class, 'marketplace_tax_registry_id');
    }

    public function scopeValid($query)
    {
        return $query->whereNull('voided_at');
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            self::METHOD_THIRD_PARTY => 'terț' . ($this->third_party_name ? ' (' . $this->third_party_name . ')' : ''),
            self::METHOD_ORGANIZER => 'confirmare organizator',
            self::METHOD_MANUAL => 'depunere manuală',
            default => 'email',
        };
    }
}
