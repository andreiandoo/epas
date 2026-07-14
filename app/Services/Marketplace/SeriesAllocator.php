<?php

namespace App\Services\Marketplace;

use App\Models\Coupon\CouponCode;
use App\Models\Event;
use App\Models\EventTicketTypePromoSeries;
use App\Models\MarketplaceOrganizerPromoCode;
use App\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase B of the discount-aware tax declaration work.
 *
 * Materialises and reads the per-(event × ticket_type × discount_code × RED)
 * series allocations used by the fiscal documents:
 *   - cerere de avizare (pre-approval — declares allocations)
 *   - declaratie impozite (declares sold per allocation)
 *   - PV distrugere (declares unsold parent stock)
 *
 * Universal lookup key: `discount_code` (string). Empty for parent rows;
 * the code value for promo / coupon tiers. `discount_source` identifies
 * which model owns the code:
 *   - ''                — parent row (no discount)
 *   - 'intrinsic_red'   — RED / sale_price reduction on the ticket type
 *   - 'organizer_promo' — MarketplaceOrganizerPromoCode
 *   - 'coupon'          — Coupon\CouponCode
 *
 * Allocation rules:
 *   - PARENT row: always exists for every type with stock. qty_allocated
 *     = ticket_type.quota_total.
 *   - INTRINSIC RED row: created when ticket_type.discount_percent > 0.
 *     qty_allocated = quota_total (same physical pool).
 *   - PROMO / COUPON row: created when the code applies to this event +
 *     ticket type. qty_allocated = min(usage_limit, quota_total) when a
 *     limit is set; otherwise = quota_total.
 *
 * series_prefix is auto-derived from the parent's series_start prefix +
 * tier code (via EventTicketTypePromoSeries::derivePrefix()).
 *
 * qty_sold is recomputed from live sales (orders.meta.promo_code.code +
 * tickets) on each sync — a denormalised cache for fast template reads.
 */
class SeriesAllocator
{
    /**
     * Sync (insert / update / soft-prune) all allocation rows for an event.
     * Idempotent.
     *
     * @return \Illuminate\Support\Collection<EventTicketTypePromoSeries>
     */
    public function syncForEvent(Event $event): Collection
    {
        $eventId = $event->id;
        $ticketTypes = $event->ticketTypes()->get();
        if ($ticketTypes->isEmpty()) {
            return collect();
        }
        $ticketTypeIds = $ticketTypes->pluck('id')->all();

        $organizerPromos = $this->loadApplicableOrganizerPromos($event, $ticketTypeIds);
        $couponCodes = $this->loadApplicableCouponCodes($event, $ticketTypeIds);

        $rowsToKeep = [];

        DB::transaction(function () use ($event, $ticketTypes, $organizerPromos, $couponCodes, &$rowsToKeep, $eventId) {
            foreach ($ticketTypes as $tt) {
                $seriesStart = (string) ($tt->series_start ?? '');
                $totalStock = (int) ($tt->quota_total ?? $tt->capacity ?? 0);

                // PARENT row (no discount).
                $rowsToKeep[] = $this->upsertRow(
                    $eventId,
                    $tt,
                    null,                                                       // promo_code_id (legacy)
                    '',                                                         // discount_code
                    '',                                                         // discount_source
                    false,                                                      // is_intrinsic_red
                    $totalStock,
                    EventTicketTypePromoSeries::derivePrefix($seriesStart, null, false)
                )->id;

                // INTRINSIC RED row.
                if ((float) ($tt->discount_percent ?? 0) > 0) {
                    $rowsToKeep[] = $this->upsertRow(
                        $eventId,
                        $tt,
                        null,
                        'RED',
                        'intrinsic_red',
                        true,
                        $totalStock,
                        EventTicketTypePromoSeries::derivePrefix($seriesStart, null, true)
                    )->id;
                }

                // ORGANIZER PROMO rows.
                foreach ($organizerPromos as $promo) {
                    if (!$this->organizerPromoAppliesToType($promo, $tt, $eventId)) {
                        continue;
                    }
                    $allocation = $totalStock;
                    if ($promo->usage_limit && $promo->usage_limit > 0) {
                        $allocation = min((int) $promo->usage_limit, $totalStock);
                    }
                    $rowsToKeep[] = $this->upsertRow(
                        $eventId,
                        $tt,
                        (int) $promo->id,
                        (string) $promo->code,
                        'organizer_promo',
                        false,
                        $allocation,
                        EventTicketTypePromoSeries::derivePrefix($seriesStart, $promo->code, false)
                    )->id;
                }

                // COUPON rows.
                foreach ($couponCodes as $coupon) {
                    if (!$this->couponAppliesToType($coupon, $tt, $eventId)) {
                        continue;
                    }
                    $allocation = $totalStock;
                    if ($coupon->max_uses_total && $coupon->max_uses_total > 0) {
                        $allocation = min((int) $coupon->max_uses_total, $totalStock);
                    }
                    $rowsToKeep[] = $this->upsertRow(
                        $eventId,
                        $tt,
                        null,                                                   // promo_code_id stays null for coupons
                        (string) $coupon->code,
                        'coupon',
                        false,
                        $allocation,
                        EventTicketTypePromoSeries::derivePrefix($seriesStart, $coupon->code, false)
                    )->id;
                }
            }

            // Stale rows — keep on record but zero out qty_allocated.
            EventTicketTypePromoSeries::query()
                ->whereIn('ticket_type_id', $ticketTypes->pluck('id'))
                ->when(!empty($rowsToKeep), fn ($q) => $q->whereNotIn('id', $rowsToKeep))
                ->update(['qty_allocated' => 0]);

            $this->refreshQtySold($event);
        });

        return EventTicketTypePromoSeries::query()
            ->whereIn('ticket_type_id', $ticketTypes->pluck('id'))
            ->get();
    }

    /**
     * Recompute qty_sold for each row from live orders/tickets data.
     * Bucket key is the discount code STRING (from orders.meta.promo_code.code).
     * Works for both organizer promos (integer ids) and coupons (UUIDs) —
     * the code is the universal identifier.
     */
    public function refreshQtySold(Event $event): void
    {
        $eventId = $event->id;
        $ticketTypes = $event->ticketTypes;
        if (!$ticketTypes || $ticketTypes->isEmpty()) {
            return;
        }

        $rows = DB::table('tickets')
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->where(function ($q) use ($eventId) {
                $q->where('tickets.event_id', $eventId)
                  ->orWhere('tickets.marketplace_event_id', $eventId);
            })
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
            ->where('orders.source', '!=', 'external_import')
            ->whereNotIn('orders.source', \App\Services\Marketplace\SalesBreakdownService::POS_SOURCES)
            ->where('orders.source', '!=', 'test_order')
            ->select(
                'tickets.ticket_type_id',
                'tickets.meta as ticket_meta',
                'orders.promo_code',
                'orders.meta as order_meta'
            )
            ->get();

        // Bucket: [ticket_type_id][discount_code or ''] => count
        $buckets = [];
        foreach ($rows as $r) {
            $ttId = (int) $r->ticket_type_id;

            // First try the per-ticket discount marker — if meta.discount_amount
            // is 0, this ticket was in a mixed-eligibility order but wasn't
            // touched by the promo, so it belongs to the parent bucket.
            $ticketMeta = $this->decodeJson($r->ticket_meta);
            if (is_array($ticketMeta) && array_key_exists('discount_amount', $ticketMeta)
                && (float) $ticketMeta['discount_amount'] <= 0.01) {
                $buckets[$ttId][''] = ($buckets[$ttId][''] ?? 0) + 1;
                continue;
            }

            // Otherwise read the discount code from the order's meta.promo_code.code.
            $code = '';
            $orderMeta = $this->decodeJson($r->order_meta);
            if (is_array($orderMeta) && isset($orderMeta['promo_code']) && is_array($orderMeta['promo_code'])) {
                $code = trim((string) ($orderMeta['promo_code']['code'] ?? ''));
            }
            if ($code === '' && $r->promo_code) {
                // Tenant-side flow fallback: orders.promo_code column.
                $code = trim((string) $r->promo_code);
            }
            // Tickets without any code attribute to parent.
            $buckets[$ttId][$code] = ($buckets[$ttId][$code] ?? 0) + 1;
        }

        $allRows = EventTicketTypePromoSeries::query()
            ->whereIn('ticket_type_id', $ticketTypes->pluck('id'))
            ->get();

        foreach ($allRows as $row) {
            $ttId = (int) $row->ticket_type_id;
            // Intrinsic RED has no order-level signal we can reliably read
            // (sale_price reduction is baked into ticket.price, not into
            // order.meta). Leave qty_sold at the current value or 0 — a
            // dedicated computation could be wired up later if needed.
            if ($row->is_intrinsic_red) {
                continue;
            }
            $key = (string) ($row->discount_code ?? '');
            $row->qty_sold = (int) ($buckets[$ttId][$key] ?? 0);
            $row->save();
        }
    }

    public function getForEvent(Event $event): Collection
    {
        $ticketTypeIds = $event->ticketTypes->pluck('id')->all();
        if (empty($ticketTypeIds)) {
            return collect();
        }
        return EventTicketTypePromoSeries::query()
            ->whereIn('ticket_type_id', $ticketTypeIds)
            ->with('ticketType')
            ->get()
            ->sortBy([
                fn ($r) => (string) ($r->ticketType?->name ?? ''),
                fn ($r) => ($r->discount_code !== '' || $r->is_intrinsic_red) ? 1 : 0,
                fn ($r) => (string) ($r->discount_code ?? ''),
            ])
            ->values();
    }

    // ============================================================
    // Internals
    // ============================================================

    private function upsertRow(
        int $eventId,
        TicketType $tt,
        ?int $promoCodeId,
        string $discountCode,
        string $discountSource,
        bool $isRed,
        int $allocated,
        string $prefix
    ): EventTicketTypePromoSeries {
        return EventTicketTypePromoSeries::updateOrCreate(
            [
                'ticket_type_id' => $tt->id,
                'discount_code' => $discountCode,
                'is_intrinsic_red' => $isRed,
            ],
            [
                'marketplace_event_id' => $eventId,
                'promo_code_id' => $promoCodeId,
                'discount_source' => $discountSource,
                'series_prefix' => $prefix,
                'qty_allocated' => $allocated,
            ]
        );
    }

    /**
     * Load active MarketplaceOrganizerPromoCode rows that touch the event.
     *
     * STRICT scope — no marketplace-wide / all-events fallbacks:
     *   - marketplace_client_id must match the event
     *   - marketplace_organizer_id MUST equal the event's organizer.
     *     NULL (marketplace-wide promo) is REJECTED.
     *   - the promo must be explicitly tied to THIS event via either:
     *       * marketplace_event_id == event.id, OR
     *       * ticket_type_id or applicable_ticket_type_ids referencing one
     *         of the event's ticket types
     *   - applies_to='all_events' alone is REJECTED.
     */
    private function loadApplicableOrganizerPromos(Event $event, array $ticketTypeIds): Collection
    {
        $marketplaceClientId = (int) ($event->marketplace_client_id ?? 0);
        $marketplaceOrganizerId = (int) ($event->marketplace_organizer_id ?? 0);
        $eventId = $event->id;
        $now = now();

        // Without an organizer scope on the event, the "must belong to this
        // organizer" rule cannot be satisfied → drop everything.
        if ($marketplaceOrganizerId === 0) {
            return collect();
        }

        return MarketplaceOrganizerPromoCode::query()
            ->when(
                $marketplaceClientId > 0,
                fn ($q) => $q->where('marketplace_client_id', $marketplaceClientId)
            )
            ->where('marketplace_organizer_id', $marketplaceOrganizerId)
            ->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->get()
            ->filter(fn ($p) => $this->organizerPromoAppliesToEvent($p, $eventId, $ticketTypeIds))
            ->values();
    }

    /**
     * Returns true when the promo applies to this event. The SQL layer
     * already guarantees the promo belongs to the event's organizer (the
     * tight scope), so any of the following counts as a match:
     *   - applies_to='all_events' (or no event/type restriction) — applies
     *     to every event of the organizer, including this one
     *   - applies_to='specific_event' AND marketplace_event_id == this event
     *   - applies_to='ticket_type' AND ticket_type matches one of this
     *     event's types (legacy ticket_type_id or applicable_ticket_type_ids)
     */
    private function organizerPromoAppliesToEvent(MarketplaceOrganizerPromoCode $promo, int $eventId, array $ticketTypeIds): bool
    {
        // Tied via single legacy ticket_type_id → must be one of THIS event's types.
        if ($promo->ticket_type_id && in_array((int) $promo->ticket_type_id, $ticketTypeIds, true)) {
            return true;
        }
        // Tied via array applicable_ticket_type_ids → at least one match.
        $applicable = $promo->applicable_ticket_type_ids;
        if (is_array($applicable) && !empty($applicable)) {
            foreach ($applicable as $id) {
                if (in_array((int) $id, $ticketTypeIds, true)) return true;
            }
        }
        // If the promo restricts to a specific event, it must be this one.
        $promoEventId = (int) ($promo->marketplace_event_id ?? 0);
        $hasNoTypeRestriction = !$promo->ticket_type_id && (empty($applicable) || $applicable === []);
        if ($promoEventId > 0 && $promoEventId !== $eventId) {
            // Targets a different event of the same organizer — not for us.
            return false;
        }
        // No type restriction AND no other-event restriction → applies here.
        // Covers applies_to='all_events' (no event/type pins) and event-scoped
        // promos with event_id matching this event.
        return $hasNoTypeRestriction;
    }

    private function organizerPromoAppliesToType(MarketplaceOrganizerPromoCode $promo, TicketType $tt, int $eventId): bool
    {
        $applicable = $promo->getApplicableTicketTypeIdsList();
        if (!empty($applicable) && in_array((int) $tt->id, $applicable, true)) {
            return true;
        }
        if (empty($applicable)) {
            // No type restriction — applies to every type of any event the
            // promo touches.
            return true;
        }
        return false;
    }

    /**
     * Load coupon codes that touch the event.
     *
     * STRICT scope — same rules as organizer promos:
     *   - marketplace_client_id must match the event
     *   - marketplace_organizer_id MUST equal the event's organizer.
     *     NULL (marketplace-wide coupon) is REJECTED.
     *   - applicable_events MUST explicitly include the event id (empty
     *     array = "any event" is REJECTED).
     *   - applicable_ticket_types either empty (applies to all event's
     *     types) OR overlaps with the event's ticket type ids.
     */
    private function loadApplicableCouponCodes(Event $event, array $ticketTypeIds): Collection
    {
        $marketplaceClientId = (int) ($event->marketplace_client_id ?? 0);
        $marketplaceOrganizerId = (int) ($event->marketplace_organizer_id ?? 0);
        $eventId = $event->id;
        $now = now();

        if ($marketplaceOrganizerId === 0) {
            return collect();
        }

        return CouponCode::query()
            ->when(
                $marketplaceClientId > 0,
                fn ($q) => $q->where('marketplace_client_id', $marketplaceClientId)
            )
            ->where('marketplace_organizer_id', $marketplaceOrganizerId)
            ->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->get()
            ->filter(fn ($c) => $this->couponAppliesToEvent($c, $eventId, $ticketTypeIds))
            ->values();
    }

    /**
     * Returns true when the coupon applies to this event. The SQL layer
     * already enforces that the coupon belongs to the event's organizer
     * (tight scope), so within that:
     *   - applicable_events empty → applies to every event of the organizer
     *   - applicable_events listing event ids → must include this event
     *   - applicable_ticket_types empty → any type of this event
     *   - applicable_ticket_types listing ids → must overlap with this
     *     event's ticket types
     */
    private function couponAppliesToEvent(CouponCode $coupon, int $eventId, array $ticketTypeIds): bool
    {
        $applicableEvents = is_array($coupon->applicable_events) ? array_map('intval', $coupon->applicable_events) : [];
        $applicableTypes = is_array($coupon->applicable_ticket_types) ? array_map('intval', $coupon->applicable_ticket_types) : [];

        // Event match: empty = "any event of the organizer" → ok; otherwise
        // must explicitly include this event.
        if (!empty($applicableEvents) && !in_array($eventId, $applicableEvents, true)) {
            return false;
        }
        // Type match: empty = any of this event's types → ok; otherwise must
        // overlap.
        if (empty($applicableTypes)) {
            return true;
        }
        foreach ($applicableTypes as $id) {
            if (in_array($id, $ticketTypeIds, true)) return true;
        }
        return false;
    }

    private function couponAppliesToType(CouponCode $coupon, TicketType $tt, int $eventId): bool
    {
        $applicableTypes = is_array($coupon->applicable_ticket_types) ? array_map('intval', $coupon->applicable_ticket_types) : [];
        if (empty($applicableTypes)) {
            return true; // No restriction → applies to every type.
        }
        return in_array((int) $tt->id, $applicableTypes, true);
    }

    private function decodeJson($raw): ?array
    {
        if (is_array($raw)) return $raw;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }
        if (is_object($raw)) {
            return json_decode(json_encode($raw), true) ?: null;
        }
        return null;
    }
}
