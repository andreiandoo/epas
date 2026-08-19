<?php

namespace App\Models;

use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatHold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use LogsActivity;

    /**
     * When true, the saved() callback skips automatic ticket status sync.
     * Used by controllers that manage ticket statuses explicitly within transactions.
     * Not a database column — excluded from Eloquent attributes.
     */
    public bool $skipTicketSync = false;

    protected $fillable = [
        'tenant_id',
        'customer_email',
        'total_cents',
        'status',
        'meta',
        'customer_id',
        'promo_code_id',
        'promo_code',
        'promo_discount',
        // Marketplace fields
        'marketplace_client_id',
        'marketplace_organizer_id',
        'newsletter_attribution_id',
        'attribution_method',
        'marketplace_customer_id',
        'marketplace_event_id',
        'event_id',
        // Shorts attribution — last-touch, set when checkout starts from a short
        // (docs/plans/shorts.md B1 / D4).
        'source_short_id',
        'source_feed',
        'order_number',
        'subtotal',
        'discount_amount',
        'commission_rate',
        'commission_amount',
        'total',
        'currency',
        'locale',
        'source',
        'channel',
        'customer_name',
        'customer_phone',
        'payment_status',
        'payment_reference',
        'payment_processor',
        'payment_error',
        'expires_at',
        'paid_at',
        'cancelled_at',
        'refunded_at',
        'refund_amount',
        'refund_reason',
        'refund_status',
        'refunded_amount',
        'metadata',
        // Payment processing fee snapshot (Stripe/Netopia/RoPay etc).
        // Zero / NULL by default → backward compat for marketplaces without
        // payment_fees configured AND for every existing order at deploy time.
        'processing_fee_cents',
        'processing_fee_passed',
        'processing_fee_provider',
        'processing_fee_percent_rate',
        'processing_fee_fixed_cents',
    ];

    protected $casts = [
        'meta' => 'array',
        'metadata' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        // Processing fee snapshot (see fillable comment for kill-switch semantics)
        'processing_fee_cents' => 'integer',
        'processing_fee_passed' => 'boolean',
        'processing_fee_percent_rate' => 'decimal:2',
        'processing_fee_fixed_cents' => 'integer',
        // Set by ShortAttributionService once the conversion has been credited;
        // its presence is what makes crediting idempotent across webhook retries.
        'short_attributed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
    }

    public function marketplaceCustomer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class);
    }

    public function marketplaceEvent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function merchOrderItems(): HasMany
    {
        return $this->hasMany(MerchOrderItem::class);
    }

    /**
     * Check if this order contains merch items.
     */
    public function hasMerch(): bool
    {
        return $this->merchOrderItems()->exists();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(OrderReminder::class);
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(MarketplaceRefundRequest::class);
    }

    public function activeRefundRequest()
    {
        return $this->hasOne(MarketplaceRefundRequest::class)
            ->whereIn('status', ['pending', 'under_review', 'approved', 'processing'])
            ->latest();
    }

    /**
     * Returns [ticket_type_name => 'included'|'on_top'] mode map for this order.
     * Reads from meta.commission_details snapshot; falls back to meta/event mode
     * for legacy orders that lack commission_details (POS orders, older data).
     */
    public function getCommissionModeByTicketType(): array
    {
        $map = [];
        foreach (($this->meta['commission_details'] ?? []) as $cd) {
            $name = $cd['ticket_type'] ?? null;
            if (!$name) continue;
            $mode = $cd['commission_mode'] ?? null;
            $map[$name] = in_array($mode, ['on_top', 'add_on_top', 'added_on_top'], true) ? 'on_top' : 'included';
        }
        return $map;
    }

    public function getOrderFallbackCommissionMode(): string
    {
        $mode = $this->meta['commission_mode']
            ?? $this->event?->commission_mode
            ?? 'included';
        return in_array($mode, ['on_top', 'add_on_top', 'added_on_top'], true) ? 'on_top' : 'included';
    }

    /**
     * Returns 'included' | 'on_top' | 'mixed' based on the commission mode of
     * all non-refunded tickets in the order.
     *  - included: every ticket has commission baked into the face price
     *  - on_top:   every ticket has commission added on top of the face price
     *  - mixed:    the order contains BOTH modes (rare but possible when a
     *              ticket_type's mode was toggled after the sale)
     */
    public function getRefundCommissionProfile(): string
    {
        $modes = collect();
        $map = $this->getCommissionModeByTicketType();
        $fallback = $this->getOrderFallbackCommissionMode();

        $tickets = $this->relationLoaded('tickets')
            ? $this->tickets
            : $this->tickets()->with('ticketType')->get();

        foreach ($tickets as $t) {
            if (($t->refund_status ?? null) === 'refunded') continue;
            $name = $t->ticketType?->name ?? null;
            $mode = ($name && isset($map[$name])) ? $map[$name] : $fallback;
            $modes->push($mode);
        }

        $unique = $modes->unique()->values();
        if ($unique->count() === 0) return $fallback;
        if ($unique->count() === 1) return $unique->first();
        return 'mixed';
    }

    /**
     * True when at least one non-refunded ticket has commission baked into the price.
     * Included tickets can NEVER be partially refunded (fără comision) — refunding
     * face-only would return LESS than the customer actually paid.
     */
    public function hasIncludedCommissionTickets(): bool
    {
        $profile = $this->getRefundCommissionProfile();
        return in_array($profile, ['included', 'mixed'], true);
    }

    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            // sincronizare email <-> customer
            if ($order->customer_id && empty($order->customer_email)) {
                $email = $order->relationLoaded('customer')
                    ? $order->customer?->email
                    : Customer::where('id', $order->customer_id)->value('email');
                if ($email) {
                    $order->customer_email = $email;
                }
            }
            if (!$order->customer_id && $order->tenant_id && $order->customer_email) {
                // Search by email AND tenant_id to respect unique constraint
                $customer = Customer::where('email', $order->customer_email)
                    ->where('tenant_id', $order->tenant_id)
                    ->first();
                if (!$customer) {
                    $customer = Customer::create([
                        'tenant_id' => $order->tenant_id,
                        'email'     => $order->customer_email,
                        'primary_tenant_id' => $order->tenant_id,
                        'marketplace_client_id' => $order->marketplace_client_id ?? null,
                    ]);
                } elseif (!$customer->marketplace_client_id && $order->marketplace_client_id) {
                    $customer->update(['marketplace_client_id' => $order->marketplace_client_id]);
                }
                $order->customer_id = $customer->id;
            }
        });

        static::saved(function (Order $order) {
            // Skip automatic ticket status sync when a controller is managing this explicitly.
            // This prevents redundant queries and avoids PostgreSQL 25P02 errors where
            // a prior query failure in the same transaction causes all subsequent queries to fail.
            if ($order->skipTicketSync) {
                return;
            }

            if (!$order->wasChanged('status')) {
                return;
            }

            // Sync ticket statuses defensively — any failure here must NOT bubble up
            // and leave the order in a partially-reconciled state (status=expired
            // but tickets still pending). The reconcile command is the backstop.
            $newStatus = $order->status;
            try {
                if (in_array($newStatus, ['paid', 'confirmed', 'completed'])) {
                    $order->tickets()->update(['status' => 'valid']);
                } elseif ($newStatus === 'refunded') {
                    // PaymentRefundService has already stamped each ticket with
                    // status='refunded' + refund_status='refunded' before the
                    // order itself transitioned. The previous bulk update here
                    // overwrote 'refunded' with 'cancelled', erasing the
                    // refund history (cards showed 0 rambursate, deconturi
                    // ignored these tickets, ticket 279679 case in point).
                    // Now we only nudge stragglers — tickets that somehow
                    // missed the refund path get the 'refunded' status, the
                    // already-refunded ones are left alone.
                    $order->tickets()
                        ->whereNotIn('status', ['refunded'])
                        ->update(['status' => 'refunded']);
                    $order->releaseSeatsAndRestoreStock();
                } elseif ($newStatus === 'partially_refunded') {
                    // Don't bulk-touch tickets — PaymentRefundService sets
                    // per-ticket status (some refunded, the rest still valid).
                    // Releasing seats / stock is also already handled
                    // per-ticket in the refund flow.
                } elseif (in_array($newStatus, ['cancelled', 'expired', 'failed'])) {
                    // 'failed' = permanent payment failure (Netopia error_type 2).
                    // It is terminal: the payment cannot be retried on the same
                    // order (PaymentController::initiate requires status='pending';
                    // a retry creates a NEW order), and a permanently-failed
                    // transaction is never followed by a success callback. So it
                    // must release its reservation exactly like cancelled/expired —
                    // otherwise the pending tickets linger forever and quota_sold
                    // stays inflated, which over-counted "sold" on availability,
                    // deconturi and the PV Distrugere document (unsold = quota_total
                    // - quota_sold). 'failed' was simply omitted here originally.
                    $order->tickets()->update(['status' => 'cancelled']);
                    $order->releaseSeatsAndRestoreStock();
                } elseif ($newStatus === 'pending') {
                    $order->tickets()->update(['status' => 'pending']);
                }
            } catch (\Throwable $e) {
                Log::error('Order: saved observer ticket/seat sync failed', [
                    'order_id' => $order->id,
                    'new_status' => $newStatus,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        // Customer-tenant pivot sync runs AFTER the transaction commits.
        // This prevents any pivot insert failure from aborting the main
        // transaction on PostgreSQL (especially through PgBouncer which
        // does not support savepoints).
        static::created(function (Order $order) {
            if ($order->customer_id && $order->tenant_id) {
                $customerId = $order->customer_id;
                $tenantId = $order->tenant_id;
                DB::afterCommit(function () use ($customerId, $tenantId) {
                    try {
                        // insertOrIgnore = INSERT ... ON CONFLICT DO NOTHING
                        // Never fails, even if row already exists
                        DB::table('customer_tenant')->insertOrIgnore([
                            'customer_id' => $customerId,
                            'tenant_id' => $tenantId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        // Set primary_tenant_id if missing
                        Customer::where('id', $customerId)
                            ->whereNull('primary_tenant_id')
                            ->update(['primary_tenant_id' => $tenantId]);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Order afterCommit: customer-tenant sync failed', [
                            'customer_id' => $customerId,
                            'tenant_id' => $tenantId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }
        });

        static::deleting(function (Order $order) {
            // Release seats and restore stock before deletion
            $order->releaseSeatsAndRestoreStock();

            // Delete child tickets explicitly — the FK on tickets.order_id is
            // nullOnDelete, so without this tickets would be orphaned (order_id
            // set to NULL) and keep showing in listings. order_items already
            // cascade at the DB level.
            $order->tickets()->delete();
        });
    }

    /**
     * Release held/sold seats and restore ticket stock for this order
     */
    public function releaseSeatsAndRestoreStock(): void
    {
        try {
            // 1. Release seats from order meta or ticket meta
            $seatInfo = $this->extractSeatInfo();
            foreach ($seatInfo as $item) {
                $eventSeatingId = $item['event_seating_id'];
                $seatUids = $item['seat_uids'];

                // NEVER free a seat that a DIFFERENT order's still-active ticket
                // holds. Two orders can carry the SAME seat_uids in their meta:
                // a customer whose first order failed then re-bought the exact
                // same seats in a second, successful order. Both orders' tickets
                // reference those seat_uids, so releasing the failed/cancelled
                // one used to blindly flip the seats to 'available' even though
                // the paid order's valid tickets owned them (event 4658: failed
                // order 180137 freed seats S1002-1-9/10/11 that paid order 180143
                // held). Exclude any seat referenced by another order's
                // valid/used/pending ticket. Compare event_seating_id as text
                // since `meta->>` returns text.
                $foreignUids = \App\Models\Ticket::query()
                    ->where('order_id', '!=', $this->id)
                    ->whereIn('status', ['valid', 'used', 'pending'])
                    ->where('meta->event_seating_id', (string) $eventSeatingId)
                    ->whereIn('meta->seat_uid', $seatUids)
                    ->get(['id', 'meta'])
                    ->map(fn ($t) => data_get($t->meta, 'seat_uid'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $releasableUids = array_values(array_diff($seatUids, $foreignUids));

                if (!empty($foreignUids)) {
                    Log::channel('marketplace')->warning('Order: skipped releasing seats owned by another active order', [
                        'order_id' => $this->id,
                        'order_number' => $this->order_number,
                        'event_seating_id' => $eventSeatingId,
                        'kept_seats' => $foreignUids,
                    ]);
                }

                if (empty($releasableUids)) {
                    continue;
                }

                $released = EventSeat::where('event_seating_id', $eventSeatingId)
                    ->whereIn('seat_uid', $releasableUids)
                    ->whereIn('status', ['held', 'sold'])
                    ->update([
                        'status' => 'available',
                        'version' => DB::raw('version + 1'),
                        'last_change_at' => now(),
                    ]);

                SeatHold::where('event_seating_id', $eventSeatingId)
                    ->whereIn('seat_uid', $releasableUids)
                    ->delete();

                if ($released > 0) {
                    Log::channel('marketplace')->info('Order: Released seats', [
                        'order_id' => $this->id,
                        'order_number' => $this->order_number,
                        'event_seating_id' => $eventSeatingId,
                        'released_count' => $released,
                    ]);
                }
            }

            // 2. Restore stock for ticket types
            if (!$this->relationLoaded('items')) {
                $this->load('items');
            }
            $ticketTypeIds = $this->items->pluck('ticket_type_id')->unique()->toArray();
            $marketplaceTicketTypes = MarketplaceTicketType::whereIn('id', $ticketTypeIds)->get()->keyBy('id');

            foreach ($this->items as $orderItem) {
                if ($orderItem->quantity > 0) {
                    $mtt = $marketplaceTicketTypes->get($orderItem->ticket_type_id);
                    if ($mtt) {
                        $mtt->decrement('quantity_sold', min($orderItem->quantity, $mtt->quantity_sold ?? 0));
                        if ($mtt->status === 'sold_out' && $mtt->quantity_sold < ($mtt->quantity ?? PHP_INT_MAX)) {
                            $mtt->update(['status' => 'active']);
                        }
                    } else {
                        // Fallback to TicketType
                        TicketType::where('id', $orderItem->ticket_type_id)
                            ->where('quota_sold', '>=', $orderItem->quantity)
                            ->decrement('quota_sold', $orderItem->quantity);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Order: Failed to release seats/stock', [
                'order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract seat info from order meta or ticket meta
     */
    protected function extractSeatInfo(): array
    {
        // Try order meta first
        $seatedItems = $this->meta['seated_items'] ?? [];
        if (!empty($seatedItems)) {
            return $seatedItems;
        }

        // Fallback: extract from individual tickets
        $tickets = $this->tickets()->whereNotNull('meta')->get();
        $seatsByLayout = [];

        foreach ($tickets as $ticket) {
            $meta = $ticket->meta;
            $seatUid = $meta['seat_uid'] ?? null;
            $eventSeatingId = $meta['event_seating_id'] ?? null;

            if ($seatUid && $eventSeatingId) {
                $key = (string) $eventSeatingId;
                if (!isset($seatsByLayout[$key])) {
                    $seatsByLayout[$key] = [
                        'event_seating_id' => (int) $eventSeatingId,
                        'seat_uids' => [],
                    ];
                }
                $seatsByLayout[$key]['seat_uids'][] = $seatUid;
            }
        }

        return array_values($seatsByLayout);
    }

    /**
     * Scope: exclude external imports from financial queries.
     */
    public function scopeExcludeExternal($query)
    {
        return $query->where('source', '!=', 'external_import');
    }

    /**
     * Release stock for specific tickets (used for partial refunds).
     */
    public function releaseStockForTickets(\Illuminate\Support\Collection $tickets): void
    {
        try {
            // 1. Release seats for specific tickets
            foreach ($tickets as $ticket) {
                $meta = $ticket->meta ?? [];
                $seatUid = $meta['seat_uid'] ?? null;
                $eventSeatingId = $meta['event_seating_id'] ?? null;

                if ($seatUid && $eventSeatingId) {
                    EventSeat::where('event_seating_id', $eventSeatingId)
                        ->where('seat_uid', $seatUid)
                        ->whereIn('status', ['held', 'sold'])
                        ->update([
                            'status' => 'available',
                            'version' => DB::raw('version + 1'),
                            'last_change_at' => now(),
                        ]);

                    SeatHold::where('event_seating_id', $eventSeatingId)
                        ->where('seat_uid', $seatUid)
                        ->delete();
                }
            }

            // 2. Restore stock grouped by ticket_type_id
            $ticketsByType = $tickets->groupBy('ticket_type_id');

            foreach ($ticketsByType as $typeId => $typeTickets) {
                if (!$typeId) continue;
                $count = $typeTickets->count();

                $mtt = MarketplaceTicketType::find($typeId);
                if ($mtt) {
                    $mtt->decrement('quantity_sold', min($count, $mtt->quantity_sold ?? 0));
                    if ($mtt->status === 'sold_out' && $mtt->quantity_sold < ($mtt->quantity ?? PHP_INT_MAX)) {
                        $mtt->update(['status' => 'active']);
                    }
                } else {
                    TicketType::where('id', $typeId)
                        ->where('quota_sold', '>=', $count)
                        ->decrement('quota_sold', $count);
                }
            }

            Log::channel('marketplace')->info('Order: Released stock for specific tickets', [
                'order_id' => $this->id,
                'ticket_count' => $tickets->count(),
                'ticket_types' => $ticketsByType->keys()->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Order: Failed to release stock for tickets', [
                'order_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Configure activity logging
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'customer_email',
                'total_cents',
                'status',
                'marketplace_organizer_id',
                'marketplace_client_id',
                'event_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Order {$eventName}")
            ->useLogName('tenant');
    }

    /**
     * Add tenant_id to activity properties for scoping
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->put('tenant_id', $this->tenant_id);
    }
}
