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
        'target_category_ids',
        'target_artist_ids',
        'exclude_recent_recipients',
        'recent_recipient_window_hours',
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
        'target_category_ids' => 'array',
        'target_artist_ids' => 'array',
        'exclude_recent_recipients' => 'boolean',
        'recent_recipient_window_hours' => 'integer',
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

        return $this->fetchCustomersChunked($resolved);
    }

    /**
     * Hydrate MarketplaceCustomers from an id collection without ever
     * binding more than ~10k params at once — Postgres caps a single
     * prepared statement at 65535 bound parameters, and "Clienți" on
     * Ambilet is already 70k+ rows. We chunk + concat instead of doing
     * one giant whereIn.
     */
    protected function fetchCustomersChunked(\Illuminate\Support\Collection $ids): \Illuminate\Support\Collection
    {
        $all = collect();
        foreach ($ids->chunk(10000) as $chunk) {
            $all = $all->concat(MarketplaceCustomer::whereIn('id', $chunk->values()->all())->get());
        }
        return $all;
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

        // ---- Narrowing filter: events directly OR derived from
        // city/organizer/category/artist picks. When target_event_ids
        // is non-empty those exact events drive the filter and the
        // other pickers are UI pre-filters only. Otherwise we resolve
        // the AND-combined event set from city + organizer + category
        // + artist; any of them being non-empty triggers the filter.
        $filterIds = null;
        $eventIds = (array) ($this->target_event_ids ?? []);
        $hasDerivedFilter = !empty($this->target_organizer_ids)
            || !empty($this->target_city_ids)
            || !empty($this->target_category_ids)
            || !empty($this->target_artist_ids);

        if (empty($eventIds) && $hasDerivedFilter) {
            $eventIds = $this->resolveFilteredEventIds(
                (array) ($this->target_organizer_ids ?? []),
                (array) ($this->target_city_ids ?? []),
                (array) ($this->target_category_ids ?? []),
                (array) ($this->target_artist_ids ?? []),
            );
        }

        if (!empty($eventIds)) {
            // Send-time runs materialize orphan buyers (orders without a
            // marketplace_customer row) so the recipient pivot stays
            // FK-valid; read-only count runs don't write.
            $filterIds = $this->getEventBuyerCustomerIds($eventIds, $materializeOrganizers);
        } elseif ($hasDerivedFilter) {
            // Filter was requested but resolves to zero events — the AND
            // semantics yield zero recipients, never widen back to base.
            $filterIds = collect();
        }

        // ---- Combination semantics.
        if (!$hasBase && $filterIds === null) {
            return collect(); // nothing selected
        }
        if ($filterIds === null) {
            $resolved = $baseIds->unique()->values();
        } elseif (!$hasBase) {
            $resolved = $filterIds->unique()->values();
        } else {
            // Both → intersect.
            $resolved = $baseIds->unique()->intersect($filterIds->unique())->values();
        }

        // Apply the optional "skip recipients who already received another
        // newsletter from this marketplace in the last N hours" filter at
        // the very end so it never widens the audience and works the same
        // for base-only / filter-only / intersected paths. Called by both
        // the read-only count and the send-time build, so the displayed
        // number always matches what'll go out.
        return $this->applyRecentRecipientFilter($resolved, $clientId);
    }

    /**
     * Strip customer ids whose email already received a newsletter from this
     * marketplace within `recent_recipient_window_hours`. Compares on the
     * recipients.email column (already lowercased at send time) — that's
     * authoritative even if a customer record was later renamed/merged.
     */
    protected function applyRecentRecipientFilter(\Illuminate\Support\Collection $ids, int $clientId): \Illuminate\Support\Collection
    {
        if (!$this->exclude_recent_recipients || $ids->isEmpty()) {
            return $ids;
        }
        $recentEmails = $this->getRecentRecipientEmails($clientId);
        if ($recentEmails->isEmpty()) {
            return $ids;
        }
        // Pull just the email column for the candidate ids and drop matches.
        $candidates = MarketplaceCustomer::whereIn('id', $ids->all())
            ->pluck('email', 'id')
            ->map(fn ($e) => mb_strtolower((string) $e));
        $skip = $recentEmails->flip(); // O(1) lookup
        return $candidates->reject(fn ($email) => isset($skip[$email]))->keys()->values();
    }

    /**
     * Lowercased emails that received a `sent` newsletter from this
     * marketplace within the dedup window. Excludes the current draft so
     * editing this newsletter doesn't filter itself.
     */
    public function getRecentRecipientEmails(?int $clientId = null): \Illuminate\Support\Collection
    {
        $clientId = $clientId ?? ($this->marketplace_client_id ?? $this->marketplaceClient?->id);
        if (!$clientId) return collect();

        $hours = max(1, (int) ($this->recent_recipient_window_hours ?: 48));
        $cutoff = now()->subHours($hours);

        return MarketplaceNewsletterRecipient::query()
            ->where('marketplace_newsletter_recipients.status', 'sent')
            ->where('marketplace_newsletter_recipients.sent_at', '>=', $cutoff)
            ->when($this->exists && $this->id, fn ($q) => $q->where('newsletter_id', '!=', $this->id))
            ->join('marketplace_newsletters', 'marketplace_newsletter_recipients.newsletter_id', '=', 'marketplace_newsletters.id')
            ->where('marketplace_newsletters.marketplace_client_id', $clientId)
            ->pluck('marketplace_newsletter_recipients.email')
            ->map(fn ($e) => mb_strtolower((string) $e))
            ->unique()
            ->values();
    }

    /**
     * How many of the current targeting's email-space recipients overlap the
     * "recently mailed" set. Used by the Filament placeholder to show "X of Y
     * will be skipped" before the admin saves the toggle.
     */
    public function getRecentRecipientOverlapCount(): int
    {
        $clientId = $this->marketplace_client_id ?? $this->marketplaceClient?->id;
        if (!$clientId) return 0;

        $recent = $this->getRecentRecipientEmails($clientId);
        if ($recent->isEmpty()) return 0;

        // Cheaper than re-running collectRecipientEmails when caller already
        // has them; for the placeholder we compute fresh.
        $audience = $this->collectRecipientEmails();
        if ($audience->isEmpty()) return 0;

        $audience = $audience->map(fn ($e) => mb_strtolower((string) $e));
        $skip = $recent->flip();
        return $audience->filter(fn ($e) => isset($skip[$e]))->count();
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

        // "External-source" rules: the customer table isn't authoritative;
        // we need to materialize from organizers / artists / venues
        // tables. These lists get full materialization at send time so
        // we don't lose emails that don't yet have a customer row.
        $externalRuleTypes = ['is_organizer', 'is_artist', 'is_venue_contact'];

        $organizer = [];
        $regular = [];
        foreach ($lists as $list) {
            $isExternal = collect($list->rules ?? [])
                ->contains(fn ($r) => is_array($r) && in_array($r['type'] ?? null, $externalRuleTypes, true));
            if ($isExternal) {
                $organizer[] = $list->id;
            } else {
                $regular[] = $list->id;
            }
        }
        return [$organizer, $regular];
    }

    /**
     * For every organizer / artist / venue in this marketplace with a
     * valid email, find or create a MarketplaceCustomer with the same
     * email and return the union of customer IDs. Inspects which rule
     * types appear across the picked external-source lists and runs
     * exactly the materializers needed (so a campaign that only targets
     * "Locații" doesn't fan out organizer rows it'll never email).
     */
    protected function materializeOrganizerCustomers(int $clientId): \Illuminate\Support\Collection
    {
        [$organizerListIds] = $this->splitListsByType((array) $this->target_lists);
        if (empty($organizerListIds)) return collect();

        $rules = MarketplaceContactList::whereIn('id', $organizerListIds)
            ->where('marketplace_client_id', $clientId)
            ->get(['id', 'rules'])
            ->flatMap(fn ($l) => collect($l->rules ?? [])->pluck('type'))
            ->unique();

        $ids = collect();

        if ($rules->contains('is_organizer')) {
            $ids = $ids->concat($this->materializeFromSource(
                $clientId,
                MarketplaceOrganizer::where('marketplace_client_id', $clientId)
                    ->whereNotNull('email')->where('email', '!=', '')
                    ->get(['email', 'name', 'company_name'])
                    ->map(fn ($o) => ['email' => $o->email, 'name' => $o->name ?: ($o->company_name ?: 'Organizator')])
            ));
        }

        if ($rules->contains('is_artist')) {
            $ids = $ids->concat($this->materializeFromSource(
                $clientId,
                MarketplaceArtistAccount::where('marketplace_client_id', $clientId)
                    ->whereNotNull('email')->where('email', '!=', '')
                    ->get(['email', 'name'])
                    ->map(fn ($a) => ['email' => $a->email, 'name' => $a->name ?: 'Artist'])
            ));
        }

        if ($rules->contains('is_venue_contact')) {
            $venues = Venue::where('marketplace_client_id', $clientId)
                ->where(function ($w) { $w->whereNotNull('email')->orWhereNotNull('email2'); })
                ->get(['name', 'email', 'email2']);
            $rows = collect();
            foreach ($venues as $v) {
                $venueName = is_array($v->name) ? ($v->name['ro'] ?? $v->name['en'] ?? reset($v->name) ?? 'Locatie') : ($v->name ?? 'Locatie');
                if (!empty($v->email))  $rows->push(['email' => $v->email,  'name' => $venueName]);
                if (!empty($v->email2)) $rows->push(['email' => $v->email2, 'name' => $venueName]);
            }
            $ids = $ids->concat($this->materializeFromSource($clientId, $rows));
        }

        return $ids->unique()->values();
    }

    /**
     * Find-or-create customer rows for a collection of [email, name]
     * pairs, with accepts_marketing=false so they never accidentally
     * land in a future opt-in blast.
     */
    protected function materializeFromSource(int $clientId, \Illuminate\Support\Collection $rows): \Illuminate\Support\Collection
    {
        $ids = collect();
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            $customer = MarketplaceCustomer::firstOrCreate(
                [
                    'marketplace_client_id' => $clientId,
                    'email' => $email,
                ],
                [
                    'first_name' => $row['name'] ?? '',
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
     * Event IDs matching ALL of the picked narrowing filters. Each
     * non-empty argument intersects the result set; an empty argument
     * is ignored. Used by the recipient resolver to translate
     * city/organizer/category/artist picks into the actual event set
     * whose buyers should receive the newsletter.
     *
     * Filters applied (all AND'd together when non-empty):
     *   $organizerIds  -> events.marketplace_organizer_id IN (...)
     *   $cityIds       -> events.marketplace_city_id IN (...)
     *   $categoryIds   -> events.marketplace_event_category_id IN (...)
     *   $artistIds     -> exists row in event_artist with artist_id IN (...)
     *
     * Backwards-compatible: callers that pass only the first two
     * arguments behave exactly like before.
     */
    public function getOrganizerEventIds(array $organizerIds, array $cityIds = [], array $categoryIds = [], array $artistIds = []): array
    {
        return $this->resolveFilteredEventIds($organizerIds, $cityIds, $categoryIds, $artistIds);
    }

    /**
     * Generalized event-filter resolver. Returns [] when nothing was
     * picked (so the caller can decide between "no filter" and "filter
     * set is empty"). Live cancellation status is not enforced here —
     * past blasts and refund follow-ups stay possible.
     */
    public function resolveFilteredEventIds(array $organizerIds, array $cityIds, array $categoryIds, array $artistIds): array
    {
        if (empty($organizerIds) && empty($cityIds) && empty($categoryIds) && empty($artistIds)) {
            return [];
        }
        $clientId = $this->marketplace_client_id ?? $this->marketplaceClient?->id;
        if (!$clientId) return [];

        $q = \App\Models\Event::query()->where('marketplace_client_id', $clientId);

        if (!empty($organizerIds)) {
            $q->whereIn('marketplace_organizer_id', $organizerIds);
        }
        if (!empty($cityIds)) {
            $q->whereIn('marketplace_city_id', $cityIds);
        }
        if (!empty($categoryIds)) {
            $q->whereIn('marketplace_event_category_id', $categoryIds);
        }
        if (!empty($artistIds)) {
            $q->whereHas('artists', function ($w) use ($artistIds) {
                $w->whereIn('artists.id', $artistIds);
            });
        }

        return $q->pluck('id')->all();
    }

    /**
     * Customer IDs who own at least one valid ticket for any of $eventIds.
     *
     * The schema has THREE flavours of buyer for a marketplace event:
     *   1. Linked: orders.marketplace_customer_id is set (modern checkout
     *      flow always populates this).
     *   2. Guest order: only orders.customer_email is set. Guest checkouts
     *      never created a marketplace_customer row.
     *   3. External / legacy import: rows live in external_tickets with
     *      attendee_email per ticket. Old WP / iabilet imports for
     *      QFeel-style theater shows have NO matching orders row at all —
     *      each imported ticket is the only record of that buyer.
     *
     * Returning only flavour #1 makes legacy campaigns wildly undercount
     * — a 15-event QFeel blast (~200 tickets each) was reporting 1 unique
     * recipient because the only "modern" order was a POS catch-all sale.
     *
     * The fix unions all three sources, then:
     *   - matches orphan emails against existing customer rows (buyers who
     *     later registered an account use the same email)
     *   - optionally firstOrCreate a customer row for any still-unresolved
     *     email when `$materializeOrphans=true` (set by send-time callers
     *     so the recipient pivot stays FK-valid).
     *
     * Materialized buyers get `accepts_marketing=false` to keep them out
     * of future general blasts — they bought a ticket, never opted in.
     */
    public function getEventBuyerCustomerIds(array $eventIds, bool $materializeOrphans = false): \Illuminate\Support\Collection
    {
        if (empty($eventIds)) return collect();

        $clientId = $this->marketplace_client_id;

        $linkedIds = \DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->whereIn('ticket_types.event_id', $eventIds)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereNotNull('orders.marketplace_customer_id')
            ->where('orders.marketplace_client_id', $clientId)
            ->pluck('orders.marketplace_customer_id')
            ->unique();

        // All unique emails from orders + external_tickets — both legacy
        // sources that don't have customer FKs.
        $allEmails = $this->getEventBuyerEmails($eventIds);

        if ($allEmails->isEmpty()) {
            return $linkedIds->values();
        }

        $existingByEmail = MarketplaceCustomer::where('marketplace_client_id', $clientId)
            ->whereIn('email', $allEmails->all())
            ->pluck('id', 'email');

        $resolved = $linkedIds->concat($existingByEmail->values());
        $stillUnresolved = $allEmails->diff(
            $existingByEmail->keys()->map(fn ($e) => strtolower($e))
        )->values();

        if ($materializeOrphans && $stillUnresolved->isNotEmpty()) {
            $nameByEmail = $this->buildBuyerNameLookup($eventIds);

            foreach ($stillUnresolved as $email) {
                $names = $nameByEmail[$email] ?? ['first' => '', 'last' => ''];
                $customer = MarketplaceCustomer::firstOrCreate(
                    [
                        'marketplace_client_id' => $clientId,
                        'email' => $email,
                    ],
                    [
                        'first_name' => $names['first'],
                        'last_name' => $names['last'],
                        'accepts_marketing' => false,
                        'status' => 'active',
                    ]
                );
                $resolved = $resolved->push($customer->id);
            }
        }

        return $resolved->unique()->values();
    }

    /**
     * Unique lowercased buyer emails for any of $eventIds — UNION across
     * ALL three ticket sources:
     *   - tickets.attendee_email per-row (POS / QFeel-style flows where
     *     orders.customer_email is a catch-all like pos@ambilet.ro and
     *     the actual buyer's address lives on the ticket itself)
     *   - orders.customer_email (modern + guest checkout, single buyer
     *     per order)
     *   - external_tickets.attendee_email (legacy WP / iabilet imports
     *     with no orders rows at all)
     *
     * Used by the read-only count path so guest + import + POS buyers
     * without a marketplace_customers row still show up in the total.
     */
    public function getEventBuyerEmails(array $eventIds): \Illuminate\Support\Collection
    {
        if (empty($eventIds)) return collect();

        $clientId = $this->marketplace_client_id;

        $ticketEmails = \DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->whereIn('ticket_types.event_id', $eventIds)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereNotNull('tickets.attendee_email')
            ->where('tickets.attendee_email', '!=', '')
            ->pluck('tickets.attendee_email');

        $orderEmails = \DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->whereIn('ticket_types.event_id', $eventIds)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->where('orders.marketplace_client_id', $clientId)
            ->whereNotNull('orders.customer_email')
            ->where('orders.customer_email', '!=', '')
            ->pluck('orders.customer_email');

        $externalEmails = collect();
        if (\Schema::hasTable('external_tickets')) {
            $externalEmails = \DB::table('external_tickets')
                ->whereIn('event_id', $eventIds)
                ->where('marketplace_client_id', $clientId)
                ->whereIn('status', ['valid', 'used'])
                ->whereNotNull('attendee_email')
                ->where('attendee_email', '!=', '')
                ->pluck('attendee_email');
        }

        return $ticketEmails
            ->concat($orderEmails)
            ->concat($externalEmails)
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    /**
     * email → ['first' => …, 'last' => …] lookup pulled from BOTH sources
     * so firstOrCreate'd customer rows carry a sensible label rather than
     * an empty name. Modern orders only have a single `customer_name`
     * field, so it lands as `first` with `last` empty.
     */
    protected function buildBuyerNameLookup(array $eventIds): array
    {
        $clientId = $this->marketplace_client_id;
        $names = [];

        // tickets.attendee_name — most specific (per-ticket override).
        \DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->whereIn('ticket_types.event_id', $eventIds)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereNotNull('tickets.attendee_email')
            ->where('tickets.attendee_email', '!=', '')
            ->select('tickets.attendee_email as email', 'tickets.attendee_name as name')
            ->distinct()
            ->get()
            ->each(function ($r) use (&$names) {
                $em = strtolower(trim((string) $r->email));
                if ($em !== '' && !isset($names[$em]) && !empty($r->name)) {
                    $names[$em] = ['first' => (string) $r->name, 'last' => ''];
                }
            });

        // orders.customer_name — broader fallback for guest checkouts.
        \DB::table('tickets')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->whereIn('ticket_types.event_id', $eventIds)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereNull('orders.marketplace_customer_id')
            ->where('orders.marketplace_client_id', $clientId)
            ->whereNotNull('orders.customer_email')
            ->where('orders.customer_email', '!=', '')
            ->select('orders.customer_email as email', 'orders.customer_name as name')
            ->distinct()
            ->get()
            ->each(function ($r) use (&$names) {
                $em = strtolower(trim((string) $r->email));
                if ($em !== '' && !isset($names[$em]) && !empty($r->name)) {
                    $names[$em] = ['first' => (string) $r->name, 'last' => ''];
                }
            });

        if (\Schema::hasTable('external_tickets')) {
            \DB::table('external_tickets')
                ->whereIn('event_id', $eventIds)
                ->where('marketplace_client_id', $clientId)
                ->whereIn('status', ['valid', 'used'])
                ->whereNotNull('attendee_email')
                ->where('attendee_email', '!=', '')
                ->select('attendee_email as email', 'attendee_first_name as first_name', 'attendee_last_name as last_name')
                ->distinct()
                ->get()
                ->each(function ($r) use (&$names) {
                    $em = strtolower(trim((string) $r->email));
                    if ($em !== '' && !isset($names[$em]) && (!empty($r->first_name) || !empty($r->last_name))) {
                        $names[$em] = [
                            'first' => (string) ($r->first_name ?? ''),
                            'last' => (string) ($r->last_name ?? ''),
                        ];
                    }
                });
        }

        return $names;
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
        // Count in email-space — getEventBuyerCustomerIds() undercounts
        // because guest + legacy orders have NULL marketplace_customer_id.
        // getEventBuyerEmails() walks orders.customer_email directly and
        // matches what'll actually go out at send time (where orphan
        // customer rows get materialized).
        if (!empty($this->target_event_ids)) {
            $eventsCount = $this->getEventBuyerEmails($this->target_event_ids)->count();
        }
        $hasDerivedFilter = empty($this->target_event_ids) && (
            !empty($this->target_organizer_ids)
            || !empty($this->target_city_ids)
            || !empty($this->target_category_ids)
            || !empty($this->target_artist_ids)
        );
        if ($hasDerivedFilter) {
            $derivedEventIds = $this->resolveFilteredEventIds(
                (array) ($this->target_organizer_ids ?? []),
                (array) ($this->target_city_ids ?? []),
                (array) ($this->target_category_ids ?? []),
                (array) ($this->target_artist_ids ?? []),
            );
            if (!empty($derivedEventIds)) {
                $eventsCount += $this->getEventBuyerEmails($derivedEventIds)->count();
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

        [$organizerListIds, $regularListIds] = $this->splitListsByType((array) $this->target_lists);

        // ---- Base audience emails (lists + tags + organizer-typed lists).
        $baseEmails = collect();
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
            $baseEmails = $baseEmails->concat($q->pluck('email'));
        }

        if (!empty($organizerListIds)) {
            $hasBase = true;
            $baseEmails = $baseEmails->concat($this->collectOrganizerEmails($clientId));
        }

        $baseEmails = $baseEmails
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique();

        // ---- Filter audience emails (events / city / org / cat / artist).
        // Uses getEventBuyerEmails() so guest + legacy orders count too —
        // see the comment on that method for context.
        $eventIds = (array) ($this->target_event_ids ?? []);
        $hasDerivedFilter = !empty($this->target_organizer_ids)
            || !empty($this->target_city_ids)
            || !empty($this->target_category_ids)
            || !empty($this->target_artist_ids);

        if (empty($eventIds) && $hasDerivedFilter) {
            $eventIds = $this->resolveFilteredEventIds(
                (array) ($this->target_organizer_ids ?? []),
                (array) ($this->target_city_ids ?? []),
                (array) ($this->target_category_ids ?? []),
                (array) ($this->target_artist_ids ?? []),
            );
        }

        $filterEmails = null;
        if (!empty($eventIds)) {
            $filterEmails = $this->getEventBuyerEmails($eventIds);
        } elseif ($hasDerivedFilter) {
            // Filter requested but resolved to zero events — AND semantics
            // yield zero recipients.
            $filterEmails = collect();
        }

        // ---- Combine using the same semantics as resolveRecipientCustomerIds.
        if (!$hasBase && $filterEmails === null) return collect();
        if ($filterEmails === null) {
            $combined = $baseEmails->values();
        } elseif (!$hasBase) {
            $combined = $filterEmails->values();
        } else {
            $combined = $baseEmails->intersect($filterEmails)->values();
        }

        // Recent-recipient dedup (parity with resolveRecipientCustomerIds)
        // so the surfaced count matches what'll actually be mailed.
        if ($this->exclude_recent_recipients) {
            $recent = $this->getRecentRecipientEmails($clientId);
            if ($recent->isNotEmpty()) {
                $skip = $recent->flip();
                $combined = $combined->reject(fn ($email) => isset($skip[$email]))->values();
            }
        }

        return $combined;
    }

    /**
     * Lowercased, deduped, validated emails contributed by external-source
     * lists (organizers / artists / venue contacts). Inspects which rule
     * types appear on the picked target_lists and pulls from the right
     * tables. Drives the read-only count + breakdown (no firstOrCreate
     * here — that's reserved for materialize at send time).
     */
    protected function collectOrganizerEmails(int $clientId): \Illuminate\Support\Collection
    {
        [$organizerListIds] = $this->splitListsByType((array) $this->target_lists);
        if (empty($organizerListIds)) return collect();

        $rules = MarketplaceContactList::whereIn('id', $organizerListIds)
            ->where('marketplace_client_id', $clientId)
            ->get(['id', 'rules'])
            ->flatMap(fn ($l) => collect($l->rules ?? [])->pluck('type'))
            ->unique();

        $emails = collect();

        if ($rules->contains('is_organizer')) {
            $emails = $emails->concat(
                MarketplaceOrganizer::where('marketplace_client_id', $clientId)
                    ->whereNotNull('email')->where('email', '!=', '')
                    ->pluck('email')
            );
        }

        if ($rules->contains('is_artist')) {
            $emails = $emails->concat(
                MarketplaceArtistAccount::where('marketplace_client_id', $clientId)
                    ->whereNotNull('email')->where('email', '!=', '')
                    ->pluck('email')
            );
        }

        if ($rules->contains('is_venue_contact')) {
            $emails = $emails->concat(
                Venue::where('marketplace_client_id', $clientId)
                    ->whereNotNull('email')->where('email', '!=', '')
                    ->pluck('email')
            );
            $emails = $emails->concat(
                Venue::where('marketplace_client_id', $clientId)
                    ->whereNotNull('email2')->where('email2', '!=', '')
                    ->pluck('email2')
            );
        }

        return $emails
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
