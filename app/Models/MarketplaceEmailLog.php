<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceEmailLog extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'marketplace_customer_id',
        'marketplace_event_id',
        'order_id',
        'template_slug',
        'from_email',
        'from_name',
        'to_email',
        'to_name',
        'subject',
        'body_html',
        'body_text',
        'status',
        'message_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class, 'marketplace_event_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Mark as sent
     */
    public function markSent(?string $messageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'message_id' => $messageId,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Mark as opened
     */
    public function markOpened(): void
    {
        if (!$this->opened_at) {
            $this->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        }
    }

    /**
     * Mark as clicked
     */
    public function markClicked(): void
    {
        $this->update([
            'status' => 'clicked',
            'clicked_at' => now(),
        ]);

        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }

    /**
     * Mark as bounced
     */
    public function markBounced(string $reason = null): void
    {
        $this->update([
            'status' => 'bounced',
            'bounced_at' => now(),
            'error_message' => $reason,
        ]);
    }

    /**
     * Scope: By status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Sent today
     */
    public function scopeSentToday($query)
    {
        return $query->whereDate('sent_at', today());
    }
}
