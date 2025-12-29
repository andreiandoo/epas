<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceNewsletter extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'subject',
        'preview_text',
        'from_name',
        'from_email',
        'reply_to',
        'body_html',
        'body_text',
        'target_lists',
        'target_tags',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'total_recipients',
        'sent_count',
        'failed_count',
        'opened_count',
        'clicked_count',
        'unsubscribed_count',
        'created_by',
    ];

    protected $casts = [
        'target_lists' => 'array',
        'target_tags' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MarketplaceNewsletterRecipient::class, 'newsletter_id');
    }

    /**
     * Get target lists
     */
    public function getTargetListsModels()
    {
        if (empty($this->target_lists)) {
            return collect();
        }

        return MarketplaceContactList::whereIn('id', $this->target_lists)->get();
    }

    /**
     * Get target tags
     */
    public function getTargetTagsModels()
    {
        if (empty($this->target_tags)) {
            return collect();
        }

        return MarketplaceContactTag::whereIn('id', $this->target_tags)->get();
    }

    /**
     * Build recipient list
     */
    public function buildRecipientList(): \Illuminate\Support\Collection
    {
        $marketplace = $this->marketplaceClient;

        $query = MarketplaceCustomer::where('marketplace_client_id', $marketplace->id)
            ->where('accepts_marketing', true);

        // Filter by lists
        if (!empty($this->target_lists)) {
            $query->whereHas('contactLists', function ($q) {
                $q->whereIn('marketplace_contact_lists.id', $this->target_lists)
                    ->wherePivot('status', 'subscribed');
            });
        }

        // Filter by tags
        if (!empty($this->target_tags)) {
            $query->whereHas('tags', function ($q) {
                $q->whereIn('marketplace_contact_tags.id', $this->target_tags);
            });
        }

        return $query->get();
    }

    /**
     * Create recipients from list
     */
    public function createRecipients(): int
    {
        $customers = $this->buildRecipientList();

        foreach ($customers as $customer) {
            $this->recipients()->updateOrCreate(
                ['marketplace_customer_id' => $customer->id],
                [
                    'email' => $customer->email,
                    'status' => 'pending',
                ]
            );
        }

        $count = $this->recipients()->count();
        $this->update(['total_recipients' => $count]);

        return $count;
    }

    /**
     * Schedule newsletter
     */
    public function schedule(\DateTime $dateTime): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_at' => $dateTime,
        ]);
    }

    /**
     * Start sending
     */
    public function startSending(): void
    {
        $this->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);
    }

    /**
     * Complete sending
     */
    public function completeSending(): void
    {
        $this->update([
            'status' => 'sent',
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancel newsletter
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Increment sent count
     */
    public function incrementSent(): void
    {
        $this->increment('sent_count');
    }

    /**
     * Increment failed count
     */
    public function incrementFailed(): void
    {
        $this->increment('failed_count');
    }

    /**
     * Increment opened count
     */
    public function incrementOpened(): void
    {
        $this->increment('opened_count');
    }

    /**
     * Increment clicked count
     */
    public function incrementClicked(): void
    {
        $this->increment('clicked_count');
    }

    /**
     * Get open rate
     */
    public function getOpenRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->opened_count / $this->sent_count) * 100, 2);
    }

    /**
     * Get click rate
     */
    public function getClickRateAttribute(): float
    {
        if ($this->opened_count === 0) {
            return 0;
        }

        return round(($this->clicked_count / $this->opened_count) * 100, 2);
    }

    /**
     * Scope: By status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Ready to send
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }
}
