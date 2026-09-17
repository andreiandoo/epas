<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One signed request to a media partner, with the outcome of its last attempt.
 */
class MarketplacePartnerDelivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'marketplace_partner_id',
        'type',
        'subject_id',
        'method',
        'url',
        'body',
        'status',
        'attempts',
        'http_status',
        'response_excerpt',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'body' => 'array',
        'attempts' => 'integer',
        'http_status' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(MarketplacePartner::class, 'marketplace_partner_id');
    }
}
