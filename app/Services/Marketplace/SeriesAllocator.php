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
            ->where('orders.source', '!=', 'pos_app')
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
     * Load active MarketplaceOrganizerPromoCode rows that could touch the
     * event's ticket types. Scoped to:
     *   - event.marketplace_client_id (same marketplace)
     *   - event.marketplace_organizer_id (same organizer) OR null (truly
     *     marketplace-wide promo, not tied to any one organizer)
     * Filtered in PHP for type-applicability checks to dodge pgsql json
     * operator strictness.
     */
    private function loadApplicableOrganizerPromos(Event $event, array $ticketTypeIds): Collection
    {
        $marketplaceClientId = (int) ($event->marketplace_client_id ?? 0);
        $marketplaceOrganizerId = (int) ($event->marketplace_organizer_id ?? 0);
        $eventId = $event->id;
        $now = now();

        return MarketplaceOrganizerPromoCode::query()
            ->when(
                $marketplaceClientId > 0,
                fn ($q) => $q->where('marketplace_client_id', $marketplaceClientId)
            )
            // Organizer scope — exclude promos owned by other organizers
            // even when applies_to='all_events'. A promo with no organizer
            // (marketplace-wide) still passes.
            ->when($marketplaceOrganizerId > 0, function ($q) use ($marketplaceOrganizerId) {
                $q->where(function ($inner) use ($marketplaceOrganizerId) {
                    $inner->where('marketplace_organizer_id', $marketplaceOrganizerId)
                          ->orWhereNull('marketplace_organizer_id');
                });
            })
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

    private function organizerPromoAppliesToEvent(MarketplaceOrganizerPromoCode $promo, int $eventId, array $ticketTypeIds): bool
    {
        if ($promo->ticket_type_id && in_array((int) $promo->ticket_type_id, $ticketTypeIds, true)) {
            return true;
        }
        $applicable = $promo->applicable_ticket_type_ids;
        if (is_array($applicable) && !empty($applicable)) {
            foreach ($applicable as $id) {
                if (in_array((int) $id, $ticketTypeIds, true)) return true;
            }
        }
        $promoEventId = (int) ($promo->marketplace_event_id ?? 0);
        $hasNoRestriction = !$promo->ticket_type_id && (empty($applicable) || $applicable === []);
        if ($promoEventId === $eventId && $hasNoRestriction) {
            return true;
        }
        // applies_to=all_events with no specific event scope — touches every
        // event under its marketplace_organizer / marketplace_client.
        if ($promo->applies_to === 'all_events' && $hasNoRestriction) {
            return true;
        }
        return false;
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
     * Load coupon codes for the marketplace that could apply to the event.
     * Same organizer scoping rule as organizer promos: a coupon owned by
     * organizer X never applies to events of organizer Y. Marketplace-wide
     * coupons (no organizer) still pass.
     *
     * Filters applicability arrays in PHP — coupon scoping uses array
     * columns (applicable_events, applicable_ticket_types) and the
     * empty-array-vs-null check is fragile across pgsql json operators.
     */
    private function loadApplicableCouponCodes(Event $event, array $ticketTypeIds): Collection
    {
        $marketplaceClientId = (int) ($event->marketplace_client_id ?? 0);
        $marketplaceOrganizerId = (int) ($event->marketplace_organizer_id ?? 0);
        $eventId = $event->id;
        $now = now();

        return CouponCode::query()
            ->when(
                $marketplaceClientId > 0,
                fn ($q) => $q->where('marketplace_client_id', $marketplaceClientId)
            )
            ->when($marketplaceOrganizerId > 0, function ($q) use ($marketplaceOrganizerId) {
                $q->where(function ($inner) use ($marketplaceOrganizerId) {
                    $inner->where('marketplace_organizer_id', $marketplaceOrganizerId)
                          ->orWhereNull('marketplace_organizer_id');
                });
            })
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

    private function couponAppliesToEvent(CouponCode $coupon, int $eventId, array $ticketTypeIds): bool
    {
        $applicableEvents = is_array($coupon->applicable_events) ? array_map('intval', $coupon->applicable_events) : [];
        $applicableTypes = is_array($coupon->applicable_ticket_types) ? array_map('intval', $coupon->applicable_ticket_types) : [];

        // Empty applicable_events means "any event" for the marketplace_client.
        $eventMatches = empty($applicableEvents) || in_array($eventId, $applicableEvents, true);
        if (!$eventMatches) {
            return false;
        }
        // Empty applicable_ticket_types means "any type" — applies to all.
        if (empty($applicableTypes)) {
            return true;
        }
        // Otherwise the coupon must restrict to one of this event's types.
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
