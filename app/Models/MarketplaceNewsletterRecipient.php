<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceNewsletterRecipient extends Model
{
    protected $fillable = [
        'newsletter_id',
        'marketplace_customer_id',
        'email',
        'status',
        'sent_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'unsubscribed_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(MarketplaceNewsletter::class, 'newsletter_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    /**
     * Mark as sent
     */
    public function markSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $this->newsletter->incrementSent();
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
        $this->newsletter->incrementFailed();
    }

    /**
     * Mark as opened
     */
    public function markOpened(): void
    {
        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
            $this->newsletter->incrementOpened();
        }
    }

    /**
     * Mark as clicked
     */
    public function markClicked(): void
    {
        if (!$this->clicked_at) {
            $this->update(['clicked_at' => now()]);
            $this->newsletter->incrementClicked();
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
     * Mark as unsubscribed
     */
    public function markUnsubscribed(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
        $this->newsletter->increment('unsubscribed_count');
    }

    /**
     * Scope: Pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Sent
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}
