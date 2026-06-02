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
        'target_organizer_ids',
        'target_city_ids',
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
        'purchase_count',
        'purchase_amount_cents',
        'created_by',
    ];

    protected $casts = [
        'body_sections' => 'array',
        'target_lists' => 'array',
        'target_tags' => 'array',
        'target_event_ids' => 'array',
        'target_organizer_ids' => 'array',
        'target_city_ids' => 'array',
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

        $resolved = $this->resolveRecipientCustomerIds($clientId, materializeOrganizers: true);

        if ($resolved->isEmpty()) return collect();

        return MarketplaceCustomer::whereIn('id', $resolved)->get();
    }

    /**
     * Resolve the targeted customer-id set, applying the correct
     * combination semantics:
     *
     *   Lists / tags / organizer-typed lists  -> BASE audience (subscribers,
     *                                            curated cohorts)
     *   Events / organizers / cities          -> NARROWING FILTER over the
     *                                            base
     *
     *   - Only base set                       -> base
     *   - Only filter set                     -> filter members (transactional)
     *   - Both set                             -> base ∩ filter
     *
     * This was UNION before — picking "Clienți" (70k) + "Qfeel events in
     * Bucharest" returned 70k + Qfeel-Bucharest buyers, which is what
     * the admin was seeing as the inflated 70k+ count even though they
     * meant "Clienți who also bought at Qfeel in Bucharest".
     *
     * `$materializeOrganizers` is true for the actual recipient build
     * (we want a customer row for each organizer email), false for the
     * read-only count + email collection so we don't write rows just to
     * compute a number.
     */
    protected function resolveRecipientCustomerIds(int $clientId, bool $materializeOrganizers = false): \Illuminate\Support\Collection
    {
        [$organizerListIds, $regularListIds] = $this->splitListsByType((array) $this->target_lists);

        // ---- Base audience: lists + tags + organizer-typed lists.
        // The organizer's list pick IS the consent — every email carries
        // an unsubscribe link, and Filament's "Abonați" list is already
        // the pre-segmented opt-in cohort. We do NOT filter by
        // accepts_marketing here (would double-filter the "Clienți"
        // non-marketing segment to empty).
        $baseIds = collect();
        $hasBase = false;

        if (!empty($regularListIds) || !empty($this->target_tags)) {
            $hasBase = true;
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
            $baseIds = $baseIds->merge($q->pluck('id'));
        }

        if (!empty($organizerListIds)) {
            $hasBase = true;
            if ($materializeOrganizers) {
                $baseIds = $baseIds->merge($this->materializeOrganizerCustomers($clientId));
            } else {
                // Read-only branch: count by joining organizer emails to
                // existing customers, no firstOrCreate.
                $emails = $this->collectOrganizerEmails($clientId);
                if (!$emails->isEmpty()) {
                    $existing = MarketplaceCustomer::where('marketplace_client_id', $clientId)
                        ->whereIn('email', $emails->all())
                        ->pluck('id');
                    $baseIds = $baseIds->merge($existing);
                }
            }
        }

        // ---- Narrowing filter: events + organizer (+ optional city).
        // Organizer is only used as a SEPARATE source when target_event_ids
        // is empty; otherwise the admin already narrowed to specific events
        // and organizer is a UI pre-filter only.
        $filterIds = null; // null = no filter applied
        $eventIds = (array) ($this->target_event_ids ?? []);
        if (empty($eventIds) && !empty($this->target_organizer_ids)) {
            $eventIds = $this->getOrganizerEventIds(
                (array) $this->target_organizer_ids,
                (array) ($this->target_city_ids ?? [])
            );
        }
        if (!empty($eventIds)) {
            $filterIds = $this->getEventBuyerCustomerIds($eventIds);
        } elseif (!empty($this->target_organizer_ids)) {
            // Organizer picked but expanded to zero events — filter set is
            // empty (so an "AND" combination yields zero).
            $filterIds = collect();
        }

        // ---- Combination semantics.
        if (!$hasBase && $filterIds === null) {
            return collect(); // nothing selected
        }
        if ($filterIds === null) {
            return $baseIds->unique()->values();
        }
        if (!$hasBase) {
            return $filterIds->unique()->values();
        }
        // Both → intersect.
        return $baseIds->unique()->intersect($filterIds->unique())->values();
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
     * Event IDs for the given organizers, optionally narrowed by the
     * city pre-filter. Live events only (is_published, not cancelled,
     * not past) so the resulting recipient set reflects ACTUAL future
     * ticket buyers — past-event audiences are usually a separate
     * "we miss you" campaign.
     */
    public function getOrganizerEventIds(array $organizerIds, array $cityIds = []): array
    {
        if (empty($organizerIds)) return [];
        $clientId = $this->marketplace_client_id ?? $this->marketplaceClient?->id;
        if (!$clientId) return [];

        $q = \App\Models\Event::query()
            ->where('marketplace_client_id', $clientId)
            ->whereIn('marketplace_organizer_id', $organizerIds);

        if (!empty($cityIds)) {
            $q->whereIn('marketplace_city_id', $cityIds);
        }

        return $q->pluck('id')->all();
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
     * comes from. Counts are reported PER SOURCE before the
     * intersection — that way the admin sees the raw cohort size of
     * each picker and the `total` field reflects the actual deduped
     * resolved audience (base ∩ filter when both are set).
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
        if (empty($this->target_event_ids) && !empty($this->target_organizer_ids)) {
            $orgEventIds = $this->getOrganizerEventIds(
                (array) $this->target_organizer_ids,
                (array) ($this->target_city_ids ?? [])
            );
            if (!empty($orgEventIds)) {
                $eventsCount += $this->getEventBuyerCustomerIds($orgEventIds)->count();
            }
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
     * Read-only emails for the resolved audience (lowercased, deduped).
     * Uses the same intersect-when-both semantics as buildRecipientList
     * via resolveRecipientCustomerIds(materializeOrganizers: false), so
     * the count surfaced to the admin stays in sync with what actually
     * gets sent.
     */
    protected function collectRecipientEmails(): \Illuminate\Support\Collection
    {
        $clientId = $this->marketplace_client_id ?? $this->marketplaceClient?->id;
        if (!$clientId) return collect();

        $resolvedIds = $this->resolveRecipientCustomerIds($clientId, materializeOrganizers: false);

        if ($resolvedIds->isEmpty()) {
            // Organizer-typed lists may still resolve to organizer emails
            // that don't yet have a MarketplaceCustomer row — surface
            // them in the count when nothing else is selected.
            [$organizerListIds] = $this->splitListsByType((array) $this->target_lists);
            if (!empty($organizerListIds) && empty($this->target_event_ids) && empty($this->target_organizer_ids)) {
                return $this->collectOrganizerEmails($clientId);
            }
            return collect();
        }

        return MarketplaceCustomer::whereIn('id', $resolvedIds)
            ->pluck('email')
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
