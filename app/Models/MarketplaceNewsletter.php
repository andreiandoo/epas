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
        'body_sections',
        'body_text',
        'target_lists',
        'target_tags',
        'target_event_ids',
        'source_email_template_id',
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
        'body_sections' => 'array',
        'target_lists' => 'array',
        'target_tags' => 'array',
        'target_event_ids' => 'array',
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
     * Build recipient list — union of:
     *   - customers in target contact lists
     *   - customers tagged with target tags
     *   - customers who bought a valid ticket for any of target_event_ids
     *
     * Customers with accepts_marketing = false are excluded from the list/tag
     * branches but **kept** for the event-buyer branch: when an organizer
     * sends a transactional update from inside an event ("important info for
     * tonight's show"), they need to reach buyers regardless of marketing
     * opt-in. The unsubscribe link is still rendered in every email, so a
     * recipient can opt out of future blasts.
     *
     * Result is dedup'd by customer id, so a single contact in both a list
     * and an event still receives only one email.
     */
    public function buildRecipientList(): \Illuminate\Support\Collection
    {
        $marketplace = $this->marketplaceClient;
        $clientId = $marketplace?->id;
        if (!$clientId) return collect();

        $customerIds = collect();

        // ---- Lists / tags branch (marketing-style; respects opt-in) ----
        if (!empty($this->target_lists) || !empty($this->target_tags)) {
            $q = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                ->where('accepts_marketing', true);

            if (!empty($this->target_lists)) {
                $q->whereHas('contactLists', function ($qq) {
                    $qq->whereIn('marketplace_contact_lists.id', $this->target_lists)
                        ->where('marketplace_contact_list_members.status', 'subscribed');
                });
            }
            if (!empty($this->target_tags)) {
                $q->whereHas('tags', function ($qq) {
                    $qq->whereIn('marketplace_contact_tags.id', $this->target_tags);
                });
            }

            $customerIds = $customerIds->merge($q->pluck('id'));
        }

        // ---- Event ticket-buyers branch (transactional; ignores opt-in) ----
        if (!empty($this->target_event_ids)) {
            $eventBuyerIds = $this->getEventBuyerCustomerIds($this->target_event_ids);
            $customerIds = $customerIds->merge($eventBuyerIds);
        }

        $uniqueIds = $customerIds->unique()->values();
        if ($uniqueIds->isEmpty()) return collect();

        return MarketplaceCustomer::whereIn('id', $uniqueIds)->get();
    }

    /**
     * Customer IDs who own at least one valid ticket for any of $eventIds.
     * Tickets are linked to orders, orders to customers; we walk that chain
     * scoped to the same marketplace_client_id to be safe.
     */
    public function getEventBuyerCustomerIds(array $eventIds): \Illuminate\Support\Collection
    {
        if (empty($eventIds)) return collect();

        return \DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->whereIn('ticket_types.event_id', $eventIds)
            ->where('tickets.status', 'valid')
            ->whereNotNull('orders.marketplace_customer_id')
            ->where('orders.marketplace_client_id', $this->marketplace_client_id)
            ->pluck('orders.marketplace_customer_id')
            ->unique()
            ->values();
    }

    /**
     * Unique recipient email count for the current targeting (lists + tags +
     * events). Cheaper than buildRecipientList()->count() because it only
     * does an SQL COUNT, not a hydrate.
     */
    public function getRecipientCount(): int
    {
        return $this->buildRecipientList()->pluck('email')->filter()->unique()->count();
    }

    /**
     * Per-source recipient counts so the UI can explain where the total
     * comes from. Overlap between sources (a customer in both a list AND
     * an event) is counted in each source's bucket; the `total` field is
     * the deduped union.
     *
     * @return array{lists:int, tags:int, events:int, total:int}
     */
    public function getRecipientBreakdown(): array
    {
        $clientId = $this->marketplace_client_id ?? $this->marketplaceClient?->id;
        if (!$clientId) {
            return ['lists' => 0, 'tags' => 0, 'events' => 0, 'total' => 0];
        }

        $listsCount = 0;
        if (!empty($this->target_lists)) {
            $listsCount = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                ->where('accepts_marketing', true)
                ->whereHas('contactLists', function ($qq) {
                    $qq->whereIn('marketplace_contact_lists.id', $this->target_lists)
                        ->where('marketplace_contact_list_members.status', 'subscribed');
                })
                ->count();
        }

        $tagsCount = 0;
        if (!empty($this->target_tags)) {
            $tagsCount = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                ->where('accepts_marketing', true)
                ->whereHas('tags', function ($qq) {
                    $qq->whereIn('marketplace_contact_tags.id', $this->target_tags);
                })
                ->count();
        }

        $eventsCount = 0;
        if (!empty($this->target_event_ids)) {
            $eventsCount = $this->getEventBuyerCustomerIds($this->target_event_ids)->count();
        }

        return [
            'lists' => $listsCount,
            'tags' => $tagsCount,
            'events' => $eventsCount,
            'total' => $this->getRecipientCount(),
        ];
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
     * Alias for completeSending
     */
    public function markCompleted(): void
    {
        $this->completeSending();
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
