<?php

namespace App\Models;

use App\Notifications\MarketplaceAdminPayoutRequestNotification;
use App\Notifications\MarketplacePayoutNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplacePayout extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'event_id',
        'reference',
        'decont_series',
        'amount',
        'currency',
        'period_start',
        'period_end',
        'gross_amount',
        'commission_amount',
        'discount_amount',
        'refund_amount',
        'fees_amount',
        'adjustments_amount',
        'adjustments_note',
        'status',
        'source',
        'payout_method',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
        'completed_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'payment_reference',
        'payment_method',
        'payment_notes',
        'admin_notes',
        'organizer_notes',
        'ticket_breakdown',
        'commission_mode',
        'invoice_recipient_type',
    ];

    public function isCommissionAddedOnTop(): bool
    {
        return $this->commission_mode === 'added_on_top';
    }

    /**
     * Build a ticket_breakdown JSON from an operator-selected list of
     * (ticket_type_id, qty) rows — the same shape the manual decont modal's
     * "Bilete pentru decont" repeater produces. This is the authoritative
     * write path: the breakdown reflects EXACTLY what the operator chose,
     * not a service-computed slice of the event.
     *
     * If $targetNet is supplied and differs from the natural net (sum of
     * row nets), qtys are scaled proportionally so the breakdown sum
     * matches $targetNet. This lets the operator type a custom amount
     * (e.g. "decont 24,480 RON" out of an available 44,000 RON of tickets)
     * without manually adjusting every row.
     *
     * Returns ['rows' => array, 'totals' => ['gross', 'commission', 'net'],
     * 'commission_mode' => string|null].
     *
     * @param iterable $payoutTicketsInput each item: {ticket_type_id, qty,
     *   unit_price, commission_per_ticket, ticket_type_name?}
     * @param \App\Models\Event $event used to look up commission_mode/type
     *   per ticket type
     * @param float|null $targetNet operator-entered net to align breakdown to
     */
    public static function buildBreakdownFromSelection(iterable $payoutTicketsInput, \App\Models\Event $event, ?float $targetNet = null): array
    {
        $ttMap = $event->ticketTypes->keyBy('id');
        $eventCommissionMode = $event->getEffectiveCommissionMode() ?: 'included';

        // Pass 1: enrich rows with ticket type metadata, drop qty<=0
        $rows = [];
        foreach ($payoutTicketsInput as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty <= 0) continue;

            $ttId = (int) ($item['ticket_type_id'] ?? 0);
            $tt = $ttMap->get($ttId);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $commPerTicket = (float) ($item['commission_per_ticket'] ?? 0);
            $ttMode = $tt?->commission_mode ?: $eventCommissionMode;

            $name = $item['ticket_type_name'] ?? null;
            if (!$name) {
                $name = $tt ? (is_array($tt->name) ? ($tt->name['ro'] ?? $tt->name['en'] ?? 'Unknown') : (string) $tt->name) : 'Unknown';
            }

            $rows[] = [
                'ticket_type_id' => $ttId,
                'ticket_type_name' => $name,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'commission_per_ticket' => $commPerTicket,
                'commission_mode' => $ttMode,
                'commission_type' => $tt?->commission_type,
                'commission_rate' => $tt?->commission_rate !== null ? (float) $tt->commission_rate : null,
                'commission_fixed' => $tt?->commission_fixed !== null ? (float) $tt->commission_fixed : null,
                // Per-type promo discount surfaced by buildRemainingTicketsItems.
                // Travels through every pass so the saved breakdown rows carry
                // the real discount and the per-row Net final reflects it.
                'discount' => (float) ($item['discount'] ?? 0),
                // Per-price tier breakdown — list of {price, qty} sub-rows.
                // Lets the PDF render "50lei*2+40lei*2" when a type was sold
                // at mixed catalog/promo prices. Scaled with qty in Pass 3.
                'tiers' => is_array($item['tiers'] ?? null) ? $item['tiers'] : [],
            ];
        }

        // Pass 2: compute initial sums
        [$sumGross, $sumCommission, $sumNet, $sumDiscount] = self::sumBreakdownRows($rows);

        // Pass 3: proportional scaling if targetNet differs noticeably
        if ($targetNet !== null && $sumNet > 0.01 && abs($targetNet - $sumNet) > 0.5) {
            $scale = $targetNet / $sumNet;
            foreach ($rows as &$r) {
                $oldQty = (int) $r['qty'];
                $r['qty'] = max(0, (int) round($oldQty * $scale));
                // Scale the per-row discount alongside qty so the proportion
                // of promo reduction tracks the slice the operator is keeping.
                $r['discount'] = round((float) ($r['discount'] ?? 0) * $scale, 2);
                // Scale tiers too — convert {price, qty} list to a price-keyed
                // bucket, run it through the same scaler, and persist the
                // sum-corrected result back on the row.
                if (!empty($r['tiers'])) {
                    $tierQty = [];
                    foreach ($r['tiers'] as $tier) {
                        $price = (string) round((float) ($tier['price'] ?? 0), 2);
                        $tierQty[$price] = ($tierQty[$price] ?? 0) + (int) ($tier['qty'] ?? 0);
                    }
                    $r['tiers'] = self::scaleTiers($tierQty, $oldQty, $r['qty']);
                }
            }
            unset($r);
            $rows = array_values(array_filter($rows, fn ($r) => $r['qty'] > 0));
            [$sumGross, $sumCommission, $sumNet, $sumDiscount] = self::sumBreakdownRows($rows);
        }

        // Pass 4: build final breakdown rows in the canonical JSON shape
        $breakdown = [];
        foreach ($rows as $r) {
            $isOnTop = in_array($r['commission_mode'], ['added_on_top', 'on_top'], true);
            $rowGross = $r['qty'] * $r['unit_price'] + ($isOnTop ? $r['qty'] * $r['commission_per_ticket'] : 0);
            $rowComm = $r['qty'] * $r['commission_per_ticket'];
            $rowDiscount = (float) ($r['discount'] ?? 0);
            $rowNet = $rowGross - $rowComm - $rowDiscount;

            $breakdown[] = [
                'ticket_type_id' => $r['ticket_type_id'],
                'ticket_type_name' => $r['ticket_type_name'],
                'qty' => $r['qty'],
                'quantity' => $r['qty'],
                'price' => $r['unit_price'],
                'unit_price' => $r['unit_price'],
                'gross' => round($rowGross, 2),
                'commission_per_ticket' => $r['commission_per_ticket'],
                'commission_amount' => round($rowComm, 2),
                'commission_mode' => $r['commission_mode'],
                'commission_type' => $r['commission_type'],
                'commission_rate' => $r['commission_rate'],
                'commission_fixed' => $r['commission_fixed'],
                'discount' => round($rowDiscount, 2),
                'extras' => 0.0,
                'net' => round($rowNet, 2),
                'tiers' => $r['tiers'] ?? [],
            ];
        }

        // Derive commission_mode for the payout from the rows: dominant mode,
        // with added_on_top winning ties (it has different downstream effects).
        $modes = collect($rows)->pluck('commission_mode')->filter()->unique()->values();
        if ($modes->count() === 1) {
            $commissionMode = $modes->first();
        } elseif ($modes->contains('added_on_top') || $modes->contains('on_top')) {
            $commissionMode = 'added_on_top';
        } else {
            $commissionMode = $eventCommissionMode;
        }

        return [
            'rows' => $breakdown,
            'totals' => [
                'gross' => round($sumGross, 2),
                'commission' => round($sumCommission, 2),
                'discount' => round($sumDiscount, 2),
                'net' => round($sumNet, 2),
            ],
            'commission_mode' => $commissionMode,
        ];
    }

    /**
     * Sum gross/commission/discount/net across enriched breakdown rows.
     * Shared between buildBreakdownFromSelection passes. net = gross −
     * commission − discount (extras handled elsewhere).
     */
    protected static function sumBreakdownRows(array $rows): array
    {
        $sumGross = 0.0;
        $sumCommission = 0.0;
        $sumDiscount = 0.0;
        foreach ($rows as $r) {
            $isOnTop = in_array($r['commission_mode'] ?? null, ['added_on_top', 'on_top'], true);
            $rowGross = $r['qty'] * $r['unit_price'] + ($isOnTop ? $r['qty'] * $r['commission_per_ticket'] : 0);
            $rowComm = $r['qty'] * $r['commission_per_ticket'];
            $sumGross += $rowGross;
            $sumCommission += $rowComm;
            $sumDiscount += (float) ($r['discount'] ?? 0);
        }
        return [$sumGross, $sumCommission, $sumGross - $sumCommission - $sumDiscount, $sumDiscount];
    }

    /**
     * Compute the datetime where a new payout's slice should begin so it
     * does NOT overlap with any prior payout for the same event/organizer.
     *
     * Returns the latest active payout's created_at if one exists, else the
     * event's created_at. The caller passes this to SalesBreakdownService
     * with exactBounds=true so the ">" comparison is strict — orders whose
     * created_at equals the previous payout's created_at belong to that
     * payout, not this new one.
     *
     * Skips cancelled/rejected payouts so their slice is recoverable by the
     * next valid payout.
     */
    /**
     * Build the repeater-shape items for every ticket on an event that has NOT
     * yet been included in another active payout's ticket_breakdown.
     * Mirrors ListPayouts::populatePayoutTicketsFromEvent but is reusable from
     * the edit-payout modal (which needs to exclude its own ticket_breakdown
     * from the "already paid" subtraction — otherwise its own current rows
     * would cancel themselves out and report zero remaining).
     *
     * Excludes POS / test_order / external_import sources, same as the
     * manual-create flow, so the count matches "Sold disponibil".
     *
     * @return array<int, array{ticket_type_id:int, ticket_type_name:string, unit_price:float, commission_per_ticket:float, qty:int}>
     */
    public static function buildRemainingTicketsItems(\App\Models\Event $event, ?int $excludePayoutId = null, ?\Carbon\Carbon $cutoffDate = null): array
    {
        $defaultRate = $event->getEffectiveCommissionRate();
        $defaultMode = $event->getEffectiveCommissionMode() ?? 'included';
        $cutoffEnd = $cutoffDate?->copy()->endOfDay();

        // Pull the actual tickets + their orders. Ticket::getEffectivePrice()
        // reads tickets.meta.discount_amount (the per-ticket discount written
        // by CheckoutController at the time of sale) and falls back to a
        // proportional allocation of order.discount_amount when the precise
        // metadata isn't there. This gives us the REAL paid price per ticket
        // — including ticket-type-specific promo codes — rather than the
        // catalog price stored in tickets.price.
        $tickets = \App\Models\Ticket::with('order')
            ->whereHas('ticketType', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', ['valid', 'used'])
            ->where(function ($q) use ($cutoffEnd) {
                $q->whereHas('order', function ($q2) use ($cutoffEnd) {
                    $q2->whereIn('status', ['paid', 'confirmed', 'completed'])
                        ->where('source', '!=', 'external_import')
                        ->where('source', '!=', 'pos_app')
                        ->where('source', '!=', 'test_order');
                    if ($cutoffEnd) {
                        $q2->where('created_at', '<=', $cutoffEnd);
                    }
                })->orWhere(function ($q2) use ($cutoffEnd) {
                    $q2->whereNull('order_id');
                    if ($cutoffEnd) {
                        $q2->where('created_at', '<=', $cutoffEnd);
                    }
                });
            })
            ->get(['id', 'order_id', 'ticket_type_id', 'price', 'meta']);

        // Aggregate per type: count + sum of effective per-ticket prices +
        // qty PER unique effective price (tier_qty). The tier bucket lets us
        // emit one row per distinct paid price in the PDF — catalog 50 and
        // promo 40 stay as separate "50lei*2", "40lei*2" parts instead of
        // averaging into "45lei*4".
        $perType = [];
        foreach ($tickets as $t) {
            $ttId = (int) $t->ticket_type_id;
            if (!$ttId) continue;
            if (!isset($perType[$ttId])) {
                $perType[$ttId] = ['qty' => 0, 'effective_total' => 0.0, 'tier_qty' => []];
            }
            $perType[$ttId]['qty']++;
            $effective = round((float) $t->getEffectivePrice(), 2);
            $perType[$ttId]['effective_total'] += $effective;
            $tierKey = (string) $effective;
            $perType[$ttId]['tier_qty'][$tierKey] = ($perType[$ttId]['tier_qty'][$tierKey] ?? 0) + 1;
        }

        // Already-paid qty per type from OTHER active deconturi on the same
        // event — so the operator's "remaining" excludes what's already in
        // another decont's breakdown.
        $alreadyPaid = [];
        $organizerId = $event->marketplace_organizer_id;
        if ($organizerId) {
            $q = static::query()
                ->where('marketplace_organizer_id', $organizerId)
                ->where('event_id', $event->id)
                ->whereIn('status', ['completed', 'processing', 'approved', 'pending'])
                ->whereNotNull('ticket_breakdown');
            if ($excludePayoutId) {
                $q->where('id', '!=', $excludePayoutId);
            }
            foreach ($q->get() as $pp) {
                foreach ($pp->ticket_breakdown ?? [] as $tb) {
                    $ttId = $tb['ticket_type_id'] ?? null;
                    if ($ttId) {
                        $alreadyPaid[$ttId] = ($alreadyPaid[$ttId] ?? 0) + (int) ($tb['qty'] ?? 0);
                    }
                }
            }
        }

        $items = [];
        foreach ($event->ticketTypes as $tt) {
            $agg = $perType[$tt->id] ?? null;
            if (!$agg) continue;
            $totalSold = (int) $agg['qty'];
            if ($totalSold <= 0) continue;

            $paid = (int) ($alreadyPaid[$tt->id] ?? 0);
            $remaining = max(0, $totalSold - $paid);
            if ($remaining <= 0) continue;

            // unit_price stays CATALOG so the operator sees the real ticket
            // price in the repeater. The discount actually applied across
            // all sold tickets of this type lives in its own 'discount' field
            // — surfaced to the form's discount_amount input by the action
            // that fills the repeater. Sum of catalog × qty − discount =
            // effective collected.
            $catalogPrice = (float) ($tt->sale_price_cents
                ? $tt->sale_price_cents / 100
                : ($tt->price_cents ? $tt->price_cents / 100 : 0));

            // Allocate the type's total discount proportionally when only a
            // slice of the qty is in this payout (the rest is already in
            // another active decont).
            $totalDiscountAllType = max(0.0, ($catalogPrice * $totalSold) - (float) $agg['effective_total']);
            $discountForRemaining = $totalSold > 0
                ? round($totalDiscountAllType * ($remaining / $totalSold), 2)
                : 0.0;

            // Commission on CATALOG — marketplace earns its full fee even
            // when the promo cuts the customer-paying side; the discount
            // comes out of the organizer's share.
            $eff = $tt->getEffectiveCommission($defaultRate, $defaultMode);
            $commPer = round($tt->calculateCommission($catalogPrice, $defaultRate, $defaultMode), 4);

            // Tier breakdown — one entry per distinct paid price, scaled
            // proportionally when only part of the qty is remaining. For the
            // common case (no prior decont so remaining = totalSold) tiers
            // come through unchanged; the PDF expands them into separate
            // "50lei*2 + 40lei*2" line items so the promo discount shows.
            $tiers = self::scaleTiers((array) $agg['tier_qty'], (int) $totalSold, $remaining);

            $items[] = [
                'ticket_type_id' => $tt->id,
                'ticket_type_name' => is_array($tt->name) ? ($tt->name['ro'] ?? $tt->name['en'] ?? '') : $tt->name,
                'unit_price' => $catalogPrice,
                'commission_per_ticket' => $commPer,
                'commission_mode' => $eff['mode'] ?? 'included',
                'qty' => $remaining,
                'discount' => $discountForRemaining,
                'tiers' => $tiers,
            ];
        }

        return $items;
    }

    /**
     * Scale a per-effective-price qty bucket (price-string => count) down
     * to `$remaining` of `$totalSold`. Proportional with a final adjustment
     * so the sum hits exactly `$remaining`. Returns a numerically-indexed
     * array of {price, qty} sorted by descending price.
     *
     * @param array<string, int> $tierQty
     * @return array<int, array{price: float, qty: int}>
     */
    protected static function scaleTiers(array $tierQty, int $totalSold, int $remaining): array
    {
        if ($remaining <= 0 || $totalSold <= 0 || empty($tierQty)) {
            return [];
        }

        $tiers = [];
        // Sort by price descending so we deduct any rounding adjustment from
        // the largest bucket — keeps small promo tiers intact when possible.
        krsort($tierQty, SORT_NUMERIC);

        if ($remaining === $totalSold) {
            foreach ($tierQty as $priceKey => $qty) {
                if ($qty > 0) {
                    $tiers[] = ['price' => (float) $priceKey, 'qty' => (int) $qty];
                }
            }
            return $tiers;
        }

        $scale = $remaining / $totalSold;
        $allocated = 0;
        foreach ($tierQty as $priceKey => $qty) {
            $scaled = (int) round($qty * $scale);
            if ($scaled > 0) {
                $tiers[] = ['price' => (float) $priceKey, 'qty' => $scaled];
                $allocated += $scaled;
            }
        }

        // Adjust the first (largest-price) tier to make the sum exact.
        if (!empty($tiers) && $allocated !== $remaining) {
            $delta = $remaining - $allocated;
            $tiers[0]['qty'] += $delta;
            if ($tiers[0]['qty'] <= 0) {
                array_shift($tiers);
            }
        }

        return $tiers;
    }

    /**
     * Sum the per-type `discount` field across items returned by
     * buildRemainingTicketsItems. Action handlers use it in one shot to
     * fill the form's hidden discount_amount input.
     */
    public static function sumDiscountFromItems(iterable $items): float
    {
        $total = 0.0;
        foreach ($items as $it) {
            $total += (float) ($it['discount'] ?? 0);
        }
        return round($total, 2);
    }

    /**
     * Return the IDs of refund_requests that landed on or before a cutoff
     * date for an event, and are still available to attach to a payout —
     * i.e. either not linked to anyone yet, or already linked to the payout
     * being edited (so the operator can re-tick them). Mirrors the picker
     * filter from the "Editează bilete decontate" modal but with an explicit
     * date cap so back-dated deconturi can pick up only the refunds that
     * existed at that point in time.
     *
     * @return array<int> refund_request IDs
     */
    public static function getRefundIdsAsOfDate(\App\Models\Event $event, \Carbon\Carbon $cutoffDate, ?int $excludePayoutId = null): array
    {
        $cutoffEnd = $cutoffDate->copy()->endOfDay();

        return \App\Models\MarketplaceRefundRequest::query()
            ->where('marketplace_event_id', $event->id)
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->where('created_at', '<=', $cutoffEnd)
            ->where(function ($q) use ($excludePayoutId) {
                $q->whereNull('marketplace_payout_id');
                if ($excludePayoutId) {
                    $q->orWhere('marketplace_payout_id', $excludePayoutId);
                }
            })
            ->pluck('id')
            ->all();
    }

    public static function resolveNextPeriodStart(int $eventId, int $organizerId, \App\Models\Event $event): ?\Carbon\Carbon
    {
        $lastPrior = static::query()
            ->where('event_id', $eventId)
            ->where('marketplace_organizer_id', $organizerId)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->orderByDesc('created_at')
            ->first(['id', 'created_at']);

        if ($lastPrior && $lastPrior->created_at) {
            return $lastPrior->created_at;
        }

        return $event->created_at ? \Carbon\Carbon::parse($event->created_at) : null;
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'fees_amount' => 'decimal:2',
        'adjustments_amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'payout_method' => 'array',
        'ticket_breakdown' => 'array',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /** Per-instance memo for getPosCommissionTotal() — not persisted. */
    protected ?float $posCommissionCache = null;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payout) {
            if (empty($payout->reference)) {
                $payout->reference = 'PAY-' . strtoupper(Str::random(8));
            }
            // Assign the marketplace-configurable decont series (prefix +
            // incrementing counter). Saved on the row at creation so it is
            // immutable thereafter. Existing payouts are never touched.
            $payout->assignDecontSeries();
        });

        static::created(function ($payout) {
            // Append payout ID to reference (e.g., PAY-QGTQJTNF-1)
            if (!str_ends_with($payout->reference, '-' . $payout->id)) {
                $payout->updateQuietly(['reference' => $payout->reference . '-' . $payout->id]);
            }
        });
    }

    /**
     * Assign the decont series from the owning marketplace client's settings:
     * `decont_prefix` + incrementing `decont_next_number` (no zero-padding,
     * e.g. DECAMB1, DECAMB2...). Idempotent — does nothing if a series is
     * already set or the client can't be resolved. The counter is read and
     * bumped under a row lock so concurrent creations never collide.
     */
    public function assignDecontSeries(): void
    {
        if (!empty($this->decont_series)) {
            return;
        }
        if (!$this->marketplace_client_id) {
            return;
        }

        DB::transaction(function () {
            $client = MarketplaceClient::whereKey($this->marketplace_client_id)
                ->lockForUpdate()
                ->first();
            if (!$client) {
                return;
            }

            $settings = $client->settings ?? [];
            $prefix = $settings['decont_prefix'] ?? 'DEC';
            $next = (int) ($settings['decont_next_number'] ?? 1);
            if ($next < 1) {
                $next = 1;
            }

            $this->decont_series = $prefix . $next;

            $settings['decont_next_number'] = $next + 1;
            $client->updateQuietly(['settings' => $settings]);
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'approved_by');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'processed_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAdmin::class, 'rejected_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MarketplaceTransaction::class);
    }

    public function decontDocument(): HasOne
    {
        return $this->hasOne(OrganizerDocument::class, 'marketplace_payout_id')
            ->where('document_type', 'decont');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * MarketplaceRefundRequests explicitly attached to THIS payout (the
     * operator picked them in the manual modal / edit-tickets action).
     * Each linked refund's amount is deducted from this payout's net,
     * and the refund appears in this payout's PDF document instead of
     * being treated as an unaccounted-for event-level deduction.
     */
    public function includedRefunds(): HasMany
    {
        return $this->hasMany(MarketplaceRefundRequest::class, 'marketplace_payout_id')
            ->whereIn('status', [
                MarketplaceRefundRequest::STATUS_REFUNDED,
                MarketplaceRefundRequest::STATUS_PARTIALLY_REFUNDED,
            ]);
    }

    /**
     * Sync the set of refunds linked to this payout. Pass the FULL list of
     * refund_request IDs that should be linked — anything currently linked
     * but not in the list is unlinked (set to null), and anything in the
     * list not yet linked is linked. Recomputes refund_amount from the
     * face_value of the linked refund items.
     *
     * Returns the new refund_amount total so the caller can adjust
     * payout.amount accordingly.
     */
    public function syncIncludedRefunds(array $refundIds): float
    {
        $refundIds = array_values(array_unique(array_filter(array_map('intval', $refundIds))));

        // 1. Unlink refunds currently linked to this payout but no longer wanted.
        MarketplaceRefundRequest::where('marketplace_payout_id', $this->id)
            ->whereNotIn('id', $refundIds ?: [0])
            ->update(['marketplace_payout_id' => null]);

        // 2. Link the requested refunds to this payout. We deliberately allow
        //    re-linking refunds that were attached to ANOTHER payout — the
        //    operator may be redistributing. The other payout's refund_amount
        //    becomes stale until its own syncIncludedRefunds runs.
        if (!empty($refundIds)) {
            MarketplaceRefundRequest::whereIn('id', $refundIds)
                ->update(['marketplace_payout_id' => $this->id]);
        }

        // 3. Recompute refund_amount: sum of face_value across the linked
        //    refund items (canonical per-ticket refund value).
        $total = (float) MarketplaceRefundItem::query()
            ->join('marketplace_refund_requests as rr', 'rr.id', '=', 'marketplace_refund_items.refund_request_id')
            ->where('rr.marketplace_payout_id', $this->id)
            ->where('marketplace_refund_items.status', 'refunded')
            ->sum('marketplace_refund_items.face_value');

        $this->update(['refund_amount' => round($total, 2)]);

        return round($total, 2);
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        // Regular invoice (commission billed for online ticket sales) — excludes POS-commission invoices
        return $this->hasOne(\App\Models\Invoice::class, 'marketplace_payout_id')
            ->where(function ($q) {
                $q->whereNull('meta->is_pos_commission')
                    ->orWhere('meta->is_pos_commission', false);
            });
    }

    public function posInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        // Separate invoice that charges the organizer for commissions on POS/app sales,
        // since that money never flowed through the marketplace.
        return $this->hasOne(\App\Models\Invoice::class, 'marketplace_payout_id')
            ->where('meta->is_pos_commission', true);
    }

    /**
     * Split gross / commission / discount / extras / net for this payout
     * across online vs POS rows of the breakdown. POS rows are those whose
     * ticket_type sells exclusively via pos_app orders (see getPosTicketTypeIds()).
     *
     * Per-line math mirrors the "Detalii bilete" blade:
     *   gross = price*qty (+ commission for added_on_top → what the customer paid)
     *   commission = commission_per_ticket * qty
     *   discount = per-row when present in snapshot, else allocated from order-level
     *              discounts via getDiscountsPerTicketType()
     *   extras = per-row only (insurance, cultural-card surcharge); legacy
     *            snapshots don't track this
     *   net = gross - commission - discount - extras
     *
     * @return array{
     *   online: array{gross: float, commission: float, discount: float, extras: float, net: float},
     *   pos: array{gross: float, commission: float, discount: float, extras: float, net: float}
     * }
     */
    public function getBreakdownTotals(): array
    {
        $breakdown = $this->ticket_breakdown ?? [];
        $posSet = array_flip($this->getPosTicketTypeIds());
        $result = [
            'online' => ['gross' => 0.0, 'commission' => 0.0, 'discount' => 0.0, 'extras' => 0.0, 'net' => 0.0],
            'pos'    => ['gross' => 0.0, 'commission' => 0.0, 'discount' => 0.0,'extras' => 0.0, 'net' => 0.0],
        ];

        // Legacy snapshots don't carry per-row discount; fall back to the
        // record-level allocation by ticket type.
        $hasPerRowDiscount = !empty($breakdown) && array_key_exists('discount', $breakdown[0] ?? []);
        $legacyDiscountsByType = (!empty($breakdown) && !$hasPerRowDiscount)
            ? $this->getDiscountsPerTicketType()
            : [];

        foreach ($breakdown as $item) {
            $ttId = $item['ticket_type_id'] ?? null;
            $bucket = ($ttId && isset($posSet[$ttId])) ? 'pos' : 'online';

            $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
            $commPer = (float) ($item['commission_per_ticket'] ?? 0);
            $itemMode = $item['commission_mode'] ?? null;
            $isOnTop = in_array($itemMode, ['added_on_top', 'on_top'], true);

            $commission = $commPer * $qty;
            $gross = $price * $qty + ($isOnTop ? $commission : 0);

            $discount = $hasPerRowDiscount
                ? (float) ($item['discount'] ?? 0)
                : (float) ($legacyDiscountsByType[$ttId] ?? 0);
            $extras = (float) ($item['extras'] ?? 0);

            // Prefer the snapshot's stored net when present (post-discount/extras);
            // else compute uniformly. The blade uses the same precedence so
            // "Net final" in the table matches what we add here.
            $net = isset($item['net'])
                ? (float) $item['net']
                : ($gross - $commission - $discount - $extras);

            $result[$bucket]['gross'] += $gross;
            $result[$bucket]['commission'] += $commission;
            $result[$bucket]['discount'] += $discount;
            $result[$bucket]['extras'] += $extras;
            $result[$bucket]['net'] += $net;
        }

        foreach ($result as $k => $v) {
            $result[$k]['gross'] = round($v['gross'], 2);
            $result[$k]['commission'] = round($v['commission'], 2);
            $result[$k]['discount'] = round($v['discount'], 2);
            $result[$k]['extras'] = round($v['extras'], 2);
            $result[$k]['net'] = round($v['net'], 2);
        }

        return $result;
    }

    /**
     * Commission value attributable to online (non-POS) tickets in this payout
     * — what the regular Factură bills (POS commission goes on a separate
     * invoice). Post-refactor both ticket_breakdown and commission_amount
     * already EXCLUDE POS, so we no longer subtract POS here (that would
     * double-discount it). Sum the breakdown when present so the invoice
     * subtotal matches the decont PDF's commission line exactly; otherwise
     * fall back to the stored commission_amount.
     */
    public function getCommissionExclPos(): float
    {
        $breakdown = $this->ticket_breakdown ?? [];
        if (!empty($breakdown)) {
            $sum = 0.0;
            foreach ($breakdown as $row) {
                $qty = (int) ($row['quantity'] ?? $row['qty'] ?? $row['tickets'] ?? 0);
                $commPer = (float) ($row['commission_per_ticket'] ?? 0);
                $sum += $qty * $commPer;
            }
            return round($sum, 2);
        }

        return round((float) ($this->commission_amount ?? 0), 2);
    }

    /**
     * Total commission value attributable to POS/app-only tickets in this payout.
     * Used to generate a separate invoice billed to the organizer.
     */
    /**
     * Total marketplace commission on this payout period's POS (mobile cash
     * app) sales. Computed live from the POS-only sales slice — NOT from
     * ticket_breakdown, which by design no longer carries POS rows (they're
     * excluded from the decont). Memoized per instance because several
     * action visible()/modal callbacks hit it on the same render.
     */
    /**
     * Total POS commission for THE ENTIRE EVENT (not the payout slice). POS
     * commission is billed once per event via a single invoice — see
     * getEventPosInvoice() — so the amount shown on each decont's "Generează
     * factură POS" button reflects what the single invoice will charge for
     * all POS sales of the event, regardless of which decont triggers it.
     *
     * Returns 0 when the event has no POS sales, or when there's no event
     * link. Memoized per instance.
     */
    public function getPosCommissionTotal(): float
    {
        if ($this->posCommissionCache !== null) {
            return $this->posCommissionCache;
        }
        if (!$this->event_id || !$this->event) {
            return $this->posCommissionCache = 0.0;
        }

        // No period bounds — POS is billed event-wide.
        $rows = app(\App\Services\Marketplace\SalesBreakdownService::class)
            ->buildPosForPayout($this->event, null, null);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) ($row['commission_amount'] ?? 0);
        }
        return $this->posCommissionCache = round($total, 2);
    }

    /**
     * The POS invoice for this payout's EVENT (if any), regardless of which
     * decont actually triggered the generation. Used so the button hides on
     * every payout of the event after the first emission, and so the other
     * payouts can show a "Vezi factura POS (Decont #X)" link to the one
     * that owns it.
     */
    public function getEventPosInvoice(): ?\App\Models\Invoice
    {
        if (!$this->event_id || !$this->marketplace_organizer_id) {
            return null;
        }

        return \App\Models\Invoice::query()
            ->whereHas('payout', fn ($q) => $q
                ->where('event_id', $this->event_id)
                ->where('marketplace_organizer_id', $this->marketplace_organizer_id))
            ->where('meta->is_pos_commission', true)
            ->latest('id')
            ->first();
    }

    /**
     * Per-ticket-type aggregation of commission for refund items where the
     * commission WAS returned to the customer (`commission_refunded = true`).
     * Marketplace lost that commission via the Stripe refund — it bills the
     * organizer for it on the unified "Factură organizator". Item status is
     * 'refunded' (per-item flag — the parent request may be partially or
     * fully refunded). Scoped to this payout's event + organizer.
     *
     * @return array<int, array{ticket_type_id:int, ticket_type_name:string, qty:int, commission_per_ticket:float, commission_amount:float}>
     */
    public function getRefundedCommissionRowsForEvent(): array
    {
        if (!$this->event_id || !$this->marketplace_organizer_id) {
            return [];
        }

        $items = \App\Models\MarketplaceRefundItem::query()
            ->whereHas('refundRequest', function ($q) {
                $q->where('marketplace_event_id', $this->event_id)
                    ->where('marketplace_organizer_id', $this->marketplace_organizer_id);
            })
            ->where('commission_refunded', true)
            ->where('status', 'refunded')
            ->with('ticketType:id,name')
            ->get();

        $byType = [];
        foreach ($items as $item) {
            $ttId = (int) $item->ticket_type_id;
            $commPer = (float) $item->commission_amount;

            if (!isset($byType[$ttId])) {
                $ttName = $item->ticketType?->name;
                $ttName = is_array($ttName)
                    ? ($ttName['ro'] ?? $ttName['en'] ?? (reset($ttName) ?: 'Bilet'))
                    : ($ttName ?? 'Bilet');
                $byType[$ttId] = [
                    'ticket_type_id' => $ttId,
                    'ticket_type_name' => $ttName,
                    'qty' => 0,
                    'commission_per_ticket' => $commPer,
                    'commission_amount' => 0.0,
                ];
            }

            $byType[$ttId]['qty']++;
            $byType[$ttId]['commission_amount'] += $commPer;
        }

        return array_values($byType);
    }

    /**
     * Total commission marketplace has to recover from the organizer because
     * those commissions were returned to the customer alongside the ticket
     * price (full refunds). Event-wide. Used to decide whether the unified
     * "Factură organizator" should appear even when there are no POS sales.
     */
    public function getRefundedCommissionTotalForEvent(): float
    {
        $total = 0.0;
        foreach ($this->getRefundedCommissionRowsForEvent() as $row) {
            $total += (float) $row['commission_amount'];
        }
        return round($total, 2);
    }

    /**
     * True once the event has reached its end — the only point at which we
     * allow organizer-invoice billing, since after that no more sales (POS
     * or online) can be added and the single invoice can safely cover
     * everything.
     */
    public function isEventFinished(): bool
    {
        $event = $this->event;
        if (!$event) {
            return false;
        }
        return $event->isPast() || ($event->status ?? null) === 'archived';
    }

    /**
     * Recompute ticket_breakdown + amount/gross/commission from the live sales
     * for this payout's stored period (POS excluded), and adjust the
     * organizer's reserved balance by the delta. This is the exact logic
     * behind the "Recalculează snapshot bilete" button, extracted so it can
     * also be run in bulk (tinker / artisan) across every payout.
     *
     * Returns ['ok' => bool, 'reason'? => string, 'amount','commission',
     * 'gross','delta' => float] for the caller to surface.
     */
    public function recalcBreakdownSnapshot(?\App\Services\Marketplace\SalesBreakdownService $service = null): array
    {
        $service ??= app(\App\Services\Marketplace\SalesBreakdownService::class);

        $event = $this->event;
        if (!$event) {
            return ['ok' => false, 'reason' => 'no_event'];
        }

        $rows = $service->buildForPayout($event, $this->period_start, $this->period_end);
        if (empty($rows)) {
            return ['ok' => false, 'reason' => 'no_sales'];
        }

        $onlineBreakdown = $service->build($event, $this->period_start, $this->period_end, excludePos: true);
        $newCommissionMode = (function () use ($onlineBreakdown, $event) {
            $modes = collect($onlineBreakdown['per_type'])->pluck('commission_mode')->filter()->unique()->values();
            if ($modes->count() === 1) return $modes->first();
            if ($modes->contains('added_on_top') || $modes->contains('on_top')) return 'added_on_top';
            return method_exists($event, 'getEffectiveCommissionMode')
                ? $event->getEffectiveCommissionMode()
                : 'included';
        })();

        $newCommission = 0.0;
        $newNet = 0.0;
        $newGross = 0.0;
        foreach ($onlineBreakdown['per_type'] as $row) {
            $isOnTop = in_array($row['commission_mode'] ?? null, ['added_on_top', 'on_top'], true);
            $newCommission += (float) $row['commission_amount'];
            $newNet += (float) $row['net'];
            $newGross += (float) $row['gross'] + ($isOnTop ? (float) $row['commission_amount'] : 0);
        }
        $newAmount = round($newNet, 2);
        $newGross = round($newGross, 2);
        $newCommission = round($newCommission, 2);

        $oldAmount = (float) $this->amount;
        $delta = round($oldAmount - $newAmount, 2);

        DB::transaction(function () use ($rows, $newCommissionMode, $newAmount, $newGross, $newCommission, $delta) {
            $this->update([
                'ticket_breakdown' => $rows,
                'commission_mode' => $newCommissionMode,
                'amount' => $newAmount,
                'gross_amount' => $newGross,
                'commission_amount' => $newCommission,
            ]);

            // Adjust the organizer's reserved (pending) balance so
            // available_balance reflects the corrected request.
            if (abs($delta) > 0.005 && $this->organizer) {
                if ($delta > 0) {
                    $this->organizer->returnPendingBalance($delta);
                } else {
                    $this->organizer->reserveBalanceForPayout(abs($delta));
                }
            }
        });

        // Period/breakdown may have shifted — drop the POS memo.
        $this->posCommissionCache = null;

        return [
            'ok' => true,
            'amount' => $newAmount,
            'commission' => $newCommission,
            'gross' => $newGross,
            'delta' => $delta,
        ];
    }

    /**
     * Ordered checklist of operator actions for this payout, used by the
     * "Pași de urmat" wizard on the view page. Each entry:
     *   ['key','label','done'].
     * The POS-invoice step appears only when the period has POS commission to
     * bill. Empty for rejected/cancelled payouts (the flow no longer applies).
     */
    public function getOperatorSteps(): array
    {
        if (in_array($this->status, ['rejected', 'cancelled'], true)) {
            return [];
        }

        $steps = [
            ['key' => 'approve', 'label' => 'Aprobă decontul', 'done' => $this->status !== 'pending'],
            ['key' => 'generate_decont', 'label' => 'Generează decontul', 'done' => $this->decontDocument !== null],
            ['key' => 'generate_invoice', 'label' => 'Generează factura', 'done' => $this->invoice !== null],
        ];

        // Organizer-invoice step appears only when the event is finished AND
        // there's something to bill the organizer for — either POS commission
        // or commission on full refunds (where the commission was returned to
        // the customer). Done = any decont on this event already emitted the
        // (single) organizer invoice — by design we bill once per event.
        if ($this->isEventFinished()
            && ($this->getPosCommissionTotal() > 0
                || $this->getRefundedCommissionTotalForEvent() > 0)) {
            $steps[] = [
                'key' => 'generate_invoice_organizer',
                'label' => 'Generează factura organizator',
                'done' => $this->getEventPosInvoice() !== null,
            ];
        }

        return $steps;
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeProcessed(): bool
    {
        return $this->isApproved();
    }

    public function canBeCompleted(): bool
    {
        // Can complete from either approved or processing status
        return $this->isApproved() || $this->isProcessing();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    // =========================================
    // Actions
    // =========================================

    /**
     * Approve the payout request.
     *
     * Does NOT auto-notify the organizer. Admin uses the explicit
     * "Trimite decont prin email" / "Notifică organizator (in-app)"
     * actions on the payout page to decide when (or whether) to notify
     * — same pattern as document generation. The status transitions
     * that DO still auto-notify (processing / completed / rejected)
     * remain untouched because those represent real money movement
     * the organizer must be aware of regardless of admin intent.
     */
    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(int $userId): void
    {
        $this->update([
            'status' => 'processing',
            'processed_by' => $userId,
            'processed_at' => now(),
        ]);

        $this->notifyOrganizer('processing');
    }

    /**
     * Complete the payout
     */
    public function complete(string $paymentReference, ?string $paymentNotes = null): void
    {
        $this->update([
            'status' => 'completed',
            'payment_reference' => $paymentReference,
            'payment_notes' => $paymentNotes,
            'completed_at' => now(),
        ]);

        // Update organizer balances
        $this->organizer->recordPayoutCompleted($this->amount);

        // Build description with payment reference
        $description = "Plată {$this->reference} finalizată";
        if ($paymentReference) {
            $description .= " (Ref: {$paymentReference})";
        }

        // Record transaction
        MarketplaceTransaction::create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'marketplace_organizer_id' => $this->marketplace_organizer_id,
            'type' => 'payout',
            'amount' => -$this->amount,
            'currency' => $this->currency,
            'balance_after' => $this->organizer->available_balance,
            'marketplace_payout_id' => $this->id,
            'description' => $description,
            'metadata' => [
                'payment_reference' => $paymentReference,
                'payment_notes' => $paymentNotes,
            ],
        ]);

        $this->notifyOrganizer('completed');
    }

    /**
     * Reject the payout request
     */
    public function reject(int $userId, string $reason): void
    {
        $wasApproved = $this->isApproved();

        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_by' => $userId,
            'rejected_at' => now(),
        ]);

        // Return balance to available
        $this->organizer->returnPendingBalance($this->amount);

        $this->notifyOrganizer('rejected');
    }

    /**
     * Cancel the payout request (by organizer)
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Return the list of ticket_type_ids in this payout's breakdown whose sales
     * come exclusively from POS/app orders (source=pos_app). These rows must
     * be shown in the table for transparency but excluded from totals, since
     * POS money doesn't flow through the marketplace.
     *
     * @return array<int>  ticket_type_ids
     */
    public function getPosTicketTypeIds(): array
    {
        $typeIds = collect($this->ticket_breakdown ?? [])
            ->pluck('ticket_type_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($typeIds) || !$this->event_id) {
            return [];
        }

        // For each ticket type, check: are there any non-pos_app tickets?
        // If not, and there are pos_app tickets, it's a POS-only type.
        $posTypeIds = [];
        foreach ($typeIds as $typeId) {
            $hasNonPos = Ticket::where('ticket_type_id', $typeId)
                ->whereHas('order', function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('event_id', $this->event_id)
                            ->orWhere('marketplace_event_id', $this->event_id);
                    })
                    ->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', '!=', 'pos_app');
                })
                ->exists();

            if ($hasNonPos) {
                continue;
            }

            $hasPos = Ticket::where('ticket_type_id', $typeId)
                ->whereHas('order', function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('event_id', $this->event_id)
                            ->orWhere('marketplace_event_id', $this->event_id);
                    })
                    ->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', 'pos_app');
                })
                ->exists();

            if ($hasPos) {
                $posTypeIds[] = $typeId;
            }
        }

        return $posTypeIds;
    }

    /**
     * Compute discount amount attributable to each ticket type in this payout.
     * For each paid order in the payout's event + period that carries a discount,
     * the discount is distributed across ticket types proportionally to each
     * type's value contribution in that order.
     *
     * @return array<int, float>  [ticket_type_id => discount_amount]
     */
    public function getDiscountsPerTicketType(): array
    {
        if (!$this->event_id) {
            return [];
        }

        $query = Order::where('event_id', $this->event_id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('discount_amount', '>', 0);

        if ($this->period_start) {
            $query->where('created_at', '>=', $this->period_start->copy()->startOfDay());
        }
        if ($this->period_end) {
            $query->where('created_at', '<=', $this->period_end->copy()->endOfDay());
        }

        $orders = $query->with(['tickets:id,order_id,ticket_type_id,price'])->get();

        $discountsByType = [];
        foreach ($orders as $order) {
            $tickets = $order->tickets;
            $totalsByType = [];
            foreach ($tickets as $ticket) {
                if (!$ticket->ticket_type_id) {
                    continue;
                }
                $totalsByType[$ticket->ticket_type_id] = ($totalsByType[$ticket->ticket_type_id] ?? 0) + (float) $ticket->price;
            }

            $orderValue = array_sum($totalsByType);
            if ($orderValue <= 0) {
                continue;
            }

            foreach ($totalsByType as $typeId => $typeTotal) {
                $proportion = $typeTotal / $orderValue;
                $share = (float) $order->discount_amount * $proportion;
                $discountsByType[$typeId] = ($discountsByType[$typeId] ?? 0) + $share;
            }
        }

        return array_map(fn ($v) => round($v, 2), $discountsByType);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'processing' => 'primary',
            'completed' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Send notification to organizer
     */
    public function notifyOrganizer(string $action): void
    {
        if (!$this->organizer) {
            return;
        }

        // Send email notification
        $this->organizer->notify(new MarketplacePayoutNotification($this, $action));

        // Create database notification in MarketplaceNotification table
        $this->createOrganizerNotification($action);
    }

    /**
     * Create notification record in MarketplaceNotification table
     */
    protected function createOrganizerNotification(string $action): void
    {
        $amount = number_format($this->amount, 2) . ' ' . $this->currency;

        $typeMap = [
            'submitted' => MarketplaceNotification::TYPE_PAYOUT_REQUEST,
            'approved' => MarketplaceNotification::TYPE_PAYOUT_APPROVED,
            'processing' => MarketplaceNotification::TYPE_PAYOUT_PROCESSING,
            'completed' => MarketplaceNotification::TYPE_PAYOUT_COMPLETED,
            'rejected' => MarketplaceNotification::TYPE_PAYOUT_REJECTED,
        ];

        $titleMap = [
            'submitted' => 'Cerere de plată înregistrată',
            'approved' => 'Cerere de plată aprobată',
            'processing' => 'Plată în procesare',
            'completed' => 'Plată finalizată',
            'rejected' => 'Cerere de plată respinsă',
        ];

        $messageMap = [
            'submitted' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost înregistrată.",
            'approved' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost aprobată.",
            'processing' => "Plata {$this->reference} în valoare de {$amount} este în curs de procesare.",
            'completed' => "Plata {$this->reference} în valoare de {$amount} a fost finalizată.",
            'rejected' => "Cererea de plată {$this->reference} în valoare de {$amount} a fost respinsă.",
        ];

        $data = [
            'payout_id' => $this->id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'action' => $action,
        ];

        if ($action === 'rejected' && $this->rejection_reason) {
            $data['rejection_reason'] = $this->rejection_reason;
        }

        MarketplaceNotification::create([
            'marketplace_client_id' => $this->marketplace_client_id,
            'marketplace_organizer_id' => $this->marketplace_organizer_id,
            'type' => $typeMap[$action] ?? MarketplaceNotification::TYPE_PAYOUT_REQUEST,
            'title' => $titleMap[$action] ?? 'Actualizare plată',
            'message' => $messageMap[$action] ?? "Actualizare pentru plata {$this->reference}.",
            'data' => $data,
            'actionable_type' => self::class,
            'actionable_id' => $this->id,
            'action_url' => "/organizator/sold",
        ]);
    }

    /**
     * Send notification to marketplace admins about new payout request
     */
    public function notifyAdmins(): void
    {
        // Get all active admins for this marketplace client
        $admins = MarketplaceAdmin::where('marketplace_client_id', $this->marketplace_client_id)
            ->where('status', 'active')
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new MarketplaceAdminPayoutRequestNotification($this));
        }
    }
}
