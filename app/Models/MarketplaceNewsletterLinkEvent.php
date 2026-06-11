<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per open (1x1 pixel hit) or click (redirect through the
 * /newsletter/click/{token} endpoint). Used for per-link analytics in
 * the EditNewsletter stats panel and for joining against orders so we
 * can credit newsletter-driven purchases.
 */
class MarketplaceNewsletterLinkEvent extends Model
{
    public const TYPE_OPEN = 'open';
    public const TYPE_CLICK = 'click';
    public const TYPE_PURCHASE = 'purchase';

    protected $fillable = [
        'newsletter_id',
        'event_type',
        'link_key',
        'dest_url',
        'recipient_id',
        'ip',
        'user_agent',
        'referer',
    ];

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(MarketplaceNewsletter::class, 'newsletter_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceNewsletterRecipient::class, 'recipient_id');
    }
}
