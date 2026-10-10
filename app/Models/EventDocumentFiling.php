<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fiscal document of an event filed with the city hall — emailed, or
 * confirmed as filed through a third-party solution.
 *
 * Rows are never deleted: a mistaken one is voided, and a document
 * regenerated after its filing simply stops being covered by it.
 */
class EventDocumentFiling extends Model
{
    public const METHOD_EMAIL = 'email';

    public const METHOD_THIRD_PARTY = 'third_party';

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
        'filed_by_id',
        'filed_by_name',
        'filed_at',
        'voided_at',
        'voided_by_name',
    ];

    protected $casts = [
        'document_generated_at' => 'datetime',
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
        return $this->method === self::METHOD_THIRD_PARTY
            ? 'terț' . ($this->third_party_name ? ' (' . $this->third_party_name . ')' : '')
            : 'email';
    }
}
