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
     *   - customers in target "regular" contact lists (opt-in respected)
     *   - customers tagged with target tags (opt-in respected)
     *   - **organizer emails** when the targeted list has rule "is_organizer"
     *     (resolved directly from marketplace_organizers, opt-in ignored —
     *     these are operational, not marketing)
     *   - customers who bought a valid ticket for any of target_event_ids
     *     (opt-in ignored — transactional)
     *
     * For organizer-typed lists, missing customer rows are created on the
     * fly (firstOrCreate) so the recipient pivot stays FK-valid. Those
     * customers get accepts_marketing=false so they never leak into a
     * regular marketing blast.
     *
     * Result is dedup'd by customer id, so a single contact in both a list
     * and an event still receives only one email.
     */
    public function buildRecipientList(): \Illuminate\Support\Collection
    {
        $marketplace = $this->marketplaceClient;
        $clientId = $marketplace?->id;
        if (!$clientId) return collect();

        [$organizerListIds, $regularListIds] = $this->splitListsByType((array) $this->target_lists);

        $customerIds = collect();

        // ---- Regular lists / tags ----
        // The organizer's list pick IS the consent — every email carries an
        // unsubscribe link, and Filament's "Abonați" list is already the
        // pre-segmented opt-in cohort. Filtering by accepts_marketing here
        // double-filters: a "Clienți" list (designed to be the non-marketing
        // segment) would always come back empty, defeating the segmentation.
        if (!empty($regularListIds) || !empty($this->target_tags)) {
            $q = MarketplaceCustomer::where('marketplace_client_id', $clientId);

            if (!empty($regularListIds)) {
                $q->whereHas('contactLists', function ($qq) use ($regularListIds) {
                    $qq->whereIn('marketplace_contact_lists.id', $regularListIds)
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

        // ---- Organizer-typed lists: pull straight from marketplace_organizers
        // and ensure a MarketplaceCustomer row exists for each so the
        // recipient FK is satisfied. The customer rows are created with
        // accepts_marketing=false so they never accidentally get scooped up
        // by a future "Abonați" blast.
        if (!empty($organizerListIds)) {
            $customerIds = $customerIds->merge($this->materializeOrganizerCustomers($clientId));
        }

        // ---- Event ticket-buyers (transactional; ignores opt-in) ----
        if (!empty($this->target_event_ids)) {
            $eventBuyerIds = $this->getEventBuyerCustomerIds($this->target_event_ids);
            $customerIds = $customerIds->merge($eventBuyerIds);
        }

        $uniqueIds = $customerIds->unique()->values();
        if ($uniqueIds->isEmpty()) return collect();

        return MarketplaceCustomer::whereIn('id', $uniqueIds)->get();
    }

    /**
     * Split target_lists into [organizer_list_ids, regular_list_ids] based
     * on whether each list's rules include the "is_organizer" type. Used by
     * both the recipient build and the count breakdown.
     *
     * @return array{0:array<int>,1:array<int>}
     */
    protected function splitListsByType(array $listIds): array
    {
        if (empty($listIds)) return [[], []];

        $clientId = $this->marketplace_client_id ?? $this->marketplaceClient?->id;
        $lists = MarketplaceContactList::whereIn('id', $listIds)
            ->when($clientId, fn ($q) => $q->where('marketplace_client_id', $clientId))
            ->get(['id', 'rules']);

        $organizer = [];
        $regular = [];
        foreach ($lists as $list) {
            $isOrgList = collect($list->rules ?? [])
                ->contains(fn ($r) => is_array($r) && (($r['type'] ?? null) === 'is_organizer'));
            if ($isOrgList) {
                $organizer[] = $list->id;
            } else {
                $regular[] = $list->id;
            }
        }
        return [$organizer, $regular];
    }

    /**
     * For every organizer in this marketplace with a valid email, find or
     * create a MarketplaceCustomer with the same email and return the set
     * of customer IDs. This makes the existing recipient pivot work for
     * organizer-targeted blasts without changing the FK.
     */
    protected function materializeOrganizerCustomers(int $clientId): \Illuminate\Support\Collection
    {
        $organizers = MarketplaceOrganizer::where('marketplace_client_id', $clientId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'email', 'name', 'company_name']);

        $ids = collect();
        foreach ($organizers as $o) {
            $email = strtolower(trim($o->email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            $customer = MarketplaceCustomer::firstOrCreate(
                [
                    'marketplace_client_id' => $clientId,
                    'email' => $email,
                ],
                [
                    'first_name' => $o->name ?: ($o->company_name ?: 'Organizator'),
                    'last_name' => '',
                    'accepts_marketing' => false,
                    'status' => 'active',
                ]
            );
            $ids->push($customer->id);
        }
        return $ids;
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
     * Unique recipient email count for the current targeting. Read-only —
     * does NOT firstOrCreate organizer customer rows (that only happens at
     * actual send time).
     */
    public function getRecipientCount(): int
    {
        return $this->collectRecipientEmails()->count();
    }

    /**
     * Per-source recipient counts so the UI can explain where the total
     * comes from. Each bucket is the count of unique emails contributed
     * by that source; `total` is the deduped union across all sources.
     *
     * @return array{lists:int, tags:int, organizers:int, events:int, total:int}
     */
    public function getRecipientBreakdown(): array
    {
        $clientId = $this->marketplace_client_id ?? $this->marketplaceClient?->id;
        if (!$clientId) {
            return ['lists' => 0, 'tags' => 0, 'organizers' => 0, 'events' => 0, 'total' => 0];
        }

        [$organizerListIds, $regularListIds] = $this->splitListsByType((array) $this->target_lists);

        $listsCount = 0;
        if (!empty($regularListIds)) {
            $listsCount = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                ->whereHas('contactLists', function ($qq) use ($regularListIds) {
                    $qq->whereIn('marketplace_contact_lists.id', $regularListIds)
                        ->where('marketplace_contact_list_members.status', 'subscribed');
                })
                ->count('id');
        }

        $tagsCount = 0;
        if (!empty($this->target_tags)) {
            $tagsCount = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                ->whereHas('tags', function ($qq) {
                    $qq->whereIn('marketplace_contact_tags.id', $this->target_tags);
                })
                ->count('id');
        }

        $organizersCount = 0;
        if (!empty($organizerListIds)) {
            $organizersCount = $this->collectOrganizerEmails($clientId)->count();
        }

        $eventsCount = 0;
        if (!empty($this->target_event_ids)) {
            $eventsCount = $this->getEventBuyerCustomerIds($this->target_event_ids)->count();
        }

        return [
            'lists' => $listsCount,
            'tags' => $tagsCount,
            'organizers' => $organizersCount,
            'events' => $eventsCount,
            'total' => $this->getRecipientCount(),
        ];
    }

    /**
     * Read-only union of all recipient emails (lowercased, deduped) without
     * touching the customers table. Used for count + preview.
     */
    protected function collectRecipientEmails(): \Illuminate\Support\Collection
    {
        $clientId = $this->marketplace_client_id ?? $this->marketplaceClient?->id;
        if (!$clientId) return collect();

        [$organizerListIds, $regularListIds] = $this->splitListsByType((array) $this->target_lists);

        $emails = collect();

        // Regular lists / tags branch
        if (!empty($regularListIds) || !empty($this->target_tags)) {
            $q = MarketplaceCustomer::where('marketplace_client_id', $clientId);
            if (!empty($regularListIds)) {
                $q->whereHas('contactLists', function ($qq) use ($regularListIds) {
                    $qq->whereIn('marketplace_contact_lists.id', $regularListIds)
                        ->where('marketplace_contact_list_members.status', 'subscribed');
                });
            }
            if (!empty($this->target_tags)) {
                $q->whereHas('tags', function ($qq) {
                    $qq->whereIn('marketplace_contact_tags.id', $this->target_tags);
                });
            }
            $emails = $emails->merge($q->pluck('email'));
        }

        if (!empty($organizerListIds)) {
            $emails = $emails->merge($this->collectOrganizerEmails($clientId));
        }

        if (!empty($this->target_event_ids)) {
            $buyerIds = $this->getEventBuyerCustomerIds($this->target_event_ids);
            if (!$buyerIds->isEmpty()) {
                $emails = $emails->merge(
                    MarketplaceCustomer::whereIn('id', $buyerIds)->pluck('email')
                );
            }
        }

        return $emails
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    /**
     * Lowercased, deduped, validated emails of every organizer in the
     * marketplace. Drives both the count and the actual send for
     * organizer-typed lists.
     */
    protected function collectOrganizerEmails(int $clientId): \Illuminate\Support\Collection
    {
        return MarketplaceOrganizer::where('marketplace_client_id', $clientId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
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
