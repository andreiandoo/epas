<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarketplaceContactList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'description',
        'list_type',
        'rules',
        'last_synced_at',
        'is_active',
        'is_default',
        'subscriber_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'rules' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Available rule types for dynamic lists
     */
    public const RULE_TYPES = [
        'newsletter_subscribed' => 'Subscribed to newsletter',
        'newsletter_unsubscribed' => 'Unsubscribed from newsletter',
        'has_purchases' => 'Has made at least one purchase',
        'has_failed_purchase_attempt' => 'Has tried to buy but never succeeded (cancelled/failed/refunded, remarketing)',
        'has_no_account' => 'Has no registered account (guest only)',
        'has_account' => 'Has a registered account',
        'no_account_or_unsubscribed' => 'No account OR not subscribed (re-engagement)',
        'purchase_count' => 'Has made X purchases',
        'purchased_category' => 'Purchased from category',
        'purchased_genre' => 'Purchased from genre',
        'has_refund_request' => 'Has requested refund',
        'is_organizer' => 'Is an event organizer (email matches marketplace_organizers)',
        'is_artist' => 'Is an artist (email matches marketplace_artist_accounts)',
        'is_venue_contact' => 'Is a venue contact (email matches venues.email / email2)',
        'city' => 'Lives in city',
        'state' => 'Lives in state/region',
        'age_less_than' => 'Age less than',
        'age_equals' => 'Age equals',
        'age_greater_than' => 'Age greater than',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceCustomer::class,
            'marketplace_contact_list_members',
            'list_id',
            'marketplace_customer_id'
        )
            ->withPivot(['status', 'subscribed_at', 'unsubscribed_at'])
            ->withTimestamps();
    }

    public function activeSubscribers(): BelongsToMany
    {
        return $this->subscribers()->wherePivot('status', 'subscribed');
    }

    /**
     * Check if list is dynamic (rule-based)
     */
    public function isDynamic(): bool
    {
        return $this->list_type === 'dynamic';
    }

    /**
     * Check if list is manual
     */
    public function isManual(): bool
    {
        return $this->list_type === 'manual';
    }

    /**
     * Get rules array
     */
    public function getRules(): array
    {
        return $this->rules ?? [];
    }

    /**
     * Build query for customers matching the rules
     */
    public function buildMatchingCustomersQuery(): Builder
    {
        $query = MarketplaceCustomer::query()
            ->where('marketplace_client_id', $this->marketplace_client_id)
            ->where('status', 'active');

        $rules = $this->getRules();

        if (empty($rules)) {
            // Return empty results if no rules
            return $query->whereRaw('1 = 0');
        }

        foreach ($rules as $rule) {
            $this->applyRule($query, $rule);
        }

        return $query;
    }

    /**
     * Apply a single rule to the query
     */
    protected function applyRule(Builder $query, array $rule): void
    {
        $type = $rule['type'] ?? null;
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? null;

        switch ($type) {
            case 'newsletter_subscribed':
                $query->where('accepts_marketing', true);
                break;

            case 'newsletter_unsubscribed':
                $query->where('accepts_marketing', false);
                break;

            case 'has_purchases':
                // total_orders is denormalized and can drift (legacy imports,
                // background-job failures). OR-fall back to a real EXISTS on
                // the orders table so we don't lose buyers whose counter is
                // stale at 0 while real success-status orders exist.
                $query->where(function ($q) {
                    $q->where('total_orders', '>', 0)
                      ->orWhereHas('orders', function ($oq) {
                          $oq->whereIn('status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES);
                      });
                });
                break;

            case 'has_failed_purchase_attempt':
                // Remarketing cohort: customers who tried to buy (have
                // orders in cancelled/failed/refunded) but never closed
                // a successful one. Excludes both "real buyers who also
                // had an earlier failed attempt" (they did buy in the
                // end) and "never tried anything". On Ambilet this is
                // ~6200 customers — mostly card-decline / abandon-checkout
                // / full-refund cohorts.
                $query->whereHas('orders', function ($oq) {
                    $oq->whereIn('status', ['cancelled', 'failed', 'refunded']);
                })->whereDoesntHave('orders', function ($oq) {
                    $oq->whereIn('status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES);
                });
                break;

            case 'has_no_account':
                // Guest customer: never registered, only transactional data.
                // Both password (bcrypt) and wp_password_hash (legacy WP
                // phpass migration) must be empty for the customer to count
                // as "no account".
                $query->where(function ($q) {
                    $q->whereNull('password')->orWhere('password', '');
                })->where(function ($q) {
                    $q->whereNull('wp_password_hash')->orWhere('wp_password_hash', '');
                });
                break;

            case 'has_account':
                $query->where(function ($q) {
                    $q->whereNotNull('password')->where('password', '!=', '')
                      ->orWhere(function ($q2) {
                          $q2->whereNotNull('wp_password_hash')->where('wp_password_hash', '!=', '');
                      });
                });
                break;

            case 'no_account_or_unsubscribed':
                // OR combo for the re-engagement cohort: customers who are
                // either guest (no account yet) OR not opted into marketing.
                // The contact list system only AND-s rules, so this single
                // rule expresses the OR that the UI repeater can't otherwise
                // model.
                $query->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->where(function ($q3) {
                            $q3->whereNull('password')->orWhere('password', '');
                        })->where(function ($q3) {
                            $q3->whereNull('wp_password_hash')->orWhere('wp_password_hash', '');
                        });
                    })->orWhere('accepts_marketing', false)
                      ->orWhereNull('accepts_marketing');
                });
                break;

            case 'purchase_count':
                $this->applyNumericOperator($query, 'total_orders', $operator, (int) $value);
                break;

            case 'purchased_category':
                $query->whereHas('orders', function ($q) use ($value) {
                    $q->where('status', 'completed')
                      ->whereHas('marketplaceEvent', function ($eq) use ($value) {
                          $eq->where('marketplace_event_category_id', $value);
                      });
                });
                break;

            case 'purchased_genre':
                // Genre is typically on the event or through artists
                $query->whereHas('orders', function ($q) use ($value) {
                    $q->where('status', 'completed')
                      ->whereHas('marketplaceEvent', function ($eq) use ($value) {
                          $eq->whereJsonContains('tags', $value);
                      });
                });
                break;

            case 'has_refund_request':
                $query->whereHas('refundRequests');
                break;

            case 'is_organizer':
                $this->applyEmailInList(
                    $query,
                    $this->collectEmails(
                        MarketplaceOrganizer::query()
                            ->where('marketplace_client_id', $this->marketplace_client_id)
                            ->whereNotNull('email')
                            ->where('email', '!=', '')
                            ->pluck('email')
                    )
                );
                break;

            case 'is_artist':
                $this->applyEmailInList(
                    $query,
                    $this->collectEmails(
                        MarketplaceArtistAccount::query()
                            ->where('marketplace_client_id', $this->marketplace_client_id)
                            ->whereNotNull('email')
                            ->where('email', '!=', '')
                            ->pluck('email')
                    )
                );
                break;

            case 'is_venue_contact':
                $primary = Venue::query()
                    ->where('marketplace_client_id', $this->marketplace_client_id)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->pluck('email');
                $secondary = Venue::query()
                    ->where('marketplace_client_id', $this->marketplace_client_id)
                    ->whereNotNull('email2')
                    ->where('email2', '!=', '')
                    ->pluck('email2');
                $this->applyEmailInList($query, $this->collectEmails($primary->concat($secondary)));
                break;

            case 'city':
                $query->where('city', 'like', "%{$value}%");
                break;

            case 'state':
                $query->where('state', 'like', "%{$value}%");
                break;

            case 'age_less_than':
                $query->whereNotNull('birth_date')
                    ->whereRaw(
                        DB::getDriverName() === 'pgsql'
                            ? 'EXTRACT(YEAR FROM AGE(CURRENT_DATE, birth_date)) < ?'
                            : 'TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < ?',
                        [(int) $value]
                    );
                break;

            case 'age_equals':
                $query->whereNotNull('birth_date')
                    ->whereRaw(
                        DB::getDriverName() === 'pgsql'
                            ? 'EXTRACT(YEAR FROM AGE(CURRENT_DATE, birth_date)) = ?'
                            : 'TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) = ?',
                        [(int) $value]
                    );
                break;

            case 'age_greater_than':
                $query->whereNotNull('birth_date')
                    ->whereRaw(
                        DB::getDriverName() === 'pgsql'
                            ? 'EXTRACT(YEAR FROM AGE(CURRENT_DATE, birth_date)) > ?'
                            : 'TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) > ?',
                        [(int) $value]
                    );
                break;
        }
    }

    /**
     * Apply numeric operator to query
     */
    protected function applyNumericOperator(Builder $query, string $column, string $operator, $value): void
    {
        switch ($operator) {
            case 'equals':
                $query->where($column, '=', $value);
                break;
            case 'greater_than':
                $query->where($column, '>', $value);
                break;
            case 'less_than':
                $query->where($column, '<', $value);
                break;
            case 'greater_or_equal':
                $query->where($column, '>=', $value);
                break;
            case 'less_or_equal':
                $query->where($column, '<=', $value);
                break;
        }
    }

    /**
     * Find-or-create a MarketplaceCustomer row for every email exposed
     * by the picked external-source rules (is_organizer / is_artist /
     * is_venue_contact). Runs before the rule match so the WHERE
     * email IN (...) clause can actually find each source row. Inspects
     * the rules array and runs ONLY the materializers needed.
     */
    public function materializeExternalSourceCustomers(): void
    {
        $rules = collect($this->getRules())->pluck('type')->unique();
        $clientId = $this->marketplace_client_id;
        if (!$clientId) return;

        if ($rules->contains('is_organizer')) {
            $rows = MarketplaceOrganizer::where('marketplace_client_id', $clientId)
                ->whereNotNull('email')->where('email', '!=', '')
                ->get(['email', 'name', 'company_name'])
                ->map(fn ($o) => [
                    'email' => $o->email,
                    'first_name' => $o->name ?: ($o->company_name ?: 'Organizator'),
                    'last_name' => '',
                ]);
            $this->materializeRowsFromSource($rows);
        }

        if ($rules->contains('is_artist')) {
            $rows = MarketplaceArtistAccount::where('marketplace_client_id', $clientId)
                ->whereNotNull('email')->where('email', '!=', '')
                ->get(['email', 'first_name', 'last_name'])
                ->map(fn ($a) => [
                    'email' => $a->email,
                    'first_name' => $a->first_name ?: 'Artist',
                    'last_name' => $a->last_name ?: '',
                ]);
            $this->materializeRowsFromSource($rows);
        }

        if ($rules->contains('is_venue_contact')) {
            $venues = Venue::where('marketplace_client_id', $clientId)
                ->where(function ($w) { $w->whereNotNull('email')->orWhereNotNull('email2'); })
                ->get(['name', 'email', 'email2']);
            $rows = collect();
            foreach ($venues as $v) {
                $venueName = is_array($v->name)
                    ? ($v->name['ro'] ?? $v->name['en'] ?? reset($v->name) ?? 'Locatie')
                    : ($v->name ?? 'Locatie');
                if (!empty($v->email))  $rows->push(['email' => $v->email,  'first_name' => $venueName, 'last_name' => '']);
                if (!empty($v->email2)) $rows->push(['email' => $v->email2, 'first_name' => $venueName, 'last_name' => '']);
            }
            $this->materializeRowsFromSource($rows);
        }
    }

    /**
     * firstOrCreate a customer per row. accepts_marketing defaults to
     * false so these auto-created accounts never accidentally land in
     * an opt-in marketing blast — only in the specific external-source
     * list that materialized them.
     */
    protected function materializeRowsFromSource(\Illuminate\Support\Collection $rows): void
    {
        $clientId = $this->marketplace_client_id;
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            MarketplaceCustomer::firstOrCreate(
                [
                    'marketplace_client_id' => $clientId,
                    'email' => $email,
                ],
                [
                    'first_name' => $row['first_name'] ?? '',
                    'last_name' => $row['last_name'] ?? '',
                    'accepts_marketing' => false,
                    'status' => 'active',
                ]
            );
        }
    }

    /**
     * Normalize a Collection of emails (lowercase + trim + filter valid +
     * unique). Centralizes the cleanup so the three external-source rules
     * share a single canonical form.
     *
     * @return array<int,string>
     */
    protected function collectEmails(\Illuminate\Support\Collection $rawEmails): array
    {
        return $rawEmails
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Apply a "customer email is in this list" filter using a literal
     * array binding instead of a nested Eloquent Builder + DB::raw
     * subquery. The nested-Builder form silently misbehaves under
     * PostgreSQL: ->select(DB::raw('lower(trim(email))')) sometimes
     * generates a "SELECT *" projection in the IN subquery, which makes
     * the IN check trivially true and the rule resolves to "every
     * active customer in the marketplace" (the 14k-row Artisti list was
     * the symptom). A plain whereIn over an in-memory array has no such
     * ambiguity. Safe up to ~50k bound params; way more than the
     * organizer / artist / venue cohorts ever need.
     */
    protected function applyEmailInList(Builder $query, array $emails): void
    {
        if (empty($emails)) {
            $query->whereRaw('1 = 0');
            return;
        }
        $query->whereIn(
            DB::raw('lower(trim(' . $query->getModel()->getTable() . '.email))'),
            $emails
        );
    }

    /**
     * Sync subscribers based on rules (for dynamic lists).
     *
     * For external-source rules (is_organizer / is_artist /
     * is_venue_contact) we materialize a MarketplaceCustomer row for
     * every source email FIRST so the subsequent rule match doesn't
     * silently drop organizers/artists/venues that never registered
     * as customers. Without this step the Organizatori list ended at
     * 516 of 548 organizers because the 32 missing ones had no
     * customer row yet.
     *
     * $prune defaults to FALSE so the sync stays additive. The
     * Cumpărători list looked like it drifted ~6200 rows above the real
     * buyer pool, but most of those "stale" rows are actually beneficiar
     * emails (the attendee_email on tickets bought by someone else —
     * gift-card / family / friends scenarios) that were enrolled in the
     * list by a separate seed path and don't appear in
     * buildMatchingCustomersQuery. Pruning them would silently delete
     * legitimate audience the marketplace owner wants to keep.
     *
     * Pass $prune=true only when you truly want a hard rule-match
     * rebuild (e.g. a brand-new list, or after fixing a buggy rule).
     */
    public function syncSubscribers(bool $prune = false): int
    {
        if (!$this->isDynamic()) {
            return 0;
        }

        $this->materializeExternalSourceCustomers();

        $matchingCustomerIds = $this->buildMatchingCustomersQuery()->pluck('id');

        // Get current subscribers
        $currentSubscriberIds = $this->activeSubscribers()->pluck('marketplace_customers.id');

        // Customers to add
        $toAdd = $matchingCustomerIds->diff($currentSubscriberIds);
        // Customers to remove (currently subscribed but no longer matching).
        $toRemove = $prune ? $currentSubscriberIds->diff($matchingCustomerIds) : collect();

        // Bulk-upsert in chunks. The previous foreach + syncWithoutDetaching
        // form fired ~2 queries per customer (one EXISTS + one INSERT),
        // so a 22k-row "Cumpărători & Abonați" sync stalled the artisan
        // shell for several minutes. Chunked DB::upsert lets Postgres
        // handle ON CONFLICT in batches of 5000 and the same sync
        // completes in single-digit seconds.
        $now = now();
        $listId = $this->id;
        foreach ($toAdd->chunk(5000) as $chunk) {
            $payload = $chunk->map(fn ($id) => [
                'list_id' => $listId,
                'marketplace_customer_id' => $id,
                'status' => 'subscribed',
                'subscribed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('marketplace_contact_list_members')->upsert(
                $payload,
                ['list_id', 'marketplace_customer_id'],
                ['status', 'subscribed_at', 'updated_at']
            );
        }

        // Mark non-matching members as unsubscribed (don't delete — keeps
        // the audit trail of who used to be on the list and when they
        // left, same as a manual unsubscribe).
        foreach ($toRemove->chunk(5000) as $chunk) {
            DB::table('marketplace_contact_list_members')
                ->where('list_id', $listId)
                ->whereIn('marketplace_customer_id', $chunk->all())
                ->update([
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        // Update sync timestamp
        $this->update([
            'last_synced_at' => now(),
            'subscriber_count' => $this->activeSubscribers()->count(),
        ]);

        return $toAdd->count();
    }

    /**
     * Add subscriber to list
     */
    public function addSubscriber(int|MarketplaceCustomer $customer): void
    {
        $customerId = $customer instanceof MarketplaceCustomer ? $customer->id : $customer;

        $this->subscribers()->syncWithoutDetaching([
            $customerId => [
                'status' => 'subscribed',
                'subscribed_at' => now(),
            ],
        ]);

        $this->updateSubscriberCount();
    }

    /**
     * Remove subscriber from list
     */
    public function removeSubscriber(int|MarketplaceCustomer $customer): void
    {
        $customerId = $customer instanceof MarketplaceCustomer ? $customer->id : $customer;

        $this->subscribers()->updateExistingPivot($customerId, [
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        $this->updateSubscriberCount();
    }

    /**
     * Update subscriber count
     */
    public function updateSubscriberCount(): void
    {
        $this->update([
            'subscriber_count' => $this->activeSubscribers()->count(),
        ]);
    }

    /**
     * Get matching customers count (preview for dynamic lists)
     */
    public function getMatchingCustomersCount(): int
    {
        if (!$this->isDynamic() || empty($this->getRules())) {
            return 0;
        }

        return $this->buildMatchingCustomersQuery()->count();
    }
}
