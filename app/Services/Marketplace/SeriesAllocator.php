<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\EventTicketTypePromoSeries;
use App\Models\MarketplaceOrganizerPromoCode;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Phase B of the discount-aware tax declaration work.
 *
 * Materialises and reads the per-(event × ticket_type × promo × RED) series
 * allocations used by all three fiscal documents:
 *   - cerere de avizare (pre-approval — declares allocations)
 *   - declaratie impozite (declares sold per allocation)
 *   - PV distrugere (declares unsold parent stock)
 *
 * Allocation rules:
 *   - PARENT row (full-price tier, no discount): per ticket type, always
 *     exists when the type has stock. qty_allocated = ticket_type.quota_total.
 *   - INTRINSIC RED row: created when ticket_type.discount_percent > 0.
 *     qty_allocated = ticket_type.quota_total (same physical stock pool —
 *     "what fraction was sold under intrinsic earlybird" is bounded by
 *     quota_total). Tracked separately for fiscal display only.
 *   - PROMO row: per active promo that applies to the type. qty_allocated
 *     = min(promo.usage_limit, ticket_type.quota_total) when usage_limit is
 *     set; otherwise = quota_total. Promos that don't apply (wrong
 *     applies_to / event mismatch / expired) are skipped.
 *
 * series_prefix is derived from the parent's series_start prefix plus the
 * tier suffix ("RED" / promo code). See EventTicketTypePromoSeries::derivePrefix().
 *
 * qty_sold is recomputed on each sync from live data (orders + tickets +
 * order.meta.promo_code) — it's a denormalised cache for fast template
 * reads, not the source of truth.
 */
class SeriesAllocator
{
    /**
     * Sync (insert / update / soft-prune) all allocation rows for an event.
     * Idempotent. Returns the resulting rows.
     *
     * Stale rows (existed for a promo that no longer applies) are kept but
     * have qty_allocated set to 0 — preserves audit trail without inflating
     * the documents. A separate maintenance task can delete them outright
     * when qty_sold is also 0.
     *
     * @return \Illuminate\Support\Collection<EventTicketTypePromoSeries>
     */
    public function syncForEvent(Event $event): \Illuminate\Support\Collection
    {
        $eventId = $event->id;
        $ticketTypes = $event->ticketTypes()->get();
        if ($ticketTypes->isEmpty()) {
            return collect();
        }
        $ticketTypeIds = $ticketTypes->pluck('id')->all();

        // Pull all promos that could touch this event's types. A code is
        // relevant if EITHER ticket_type_id / applicable_ticket_type_ids
        // points at one of these types, OR (marketplace_event_id == event
        // AND no ticket type restriction → applies to every type of the
        // event). Same rules as the existing ticket_types_rows generator.
        $promos = MarketplaceOrganizerPromoCode::query()
            ->where('status', 'active')
            ->where(function ($q) use ($ticketTypeIds, $eventId) {
                $q->whereIn('ticket_type_id', $ticketTypeIds);
                foreach ($ticketTypeIds as $ttId) {
                    $q->orWhereJsonContains('applicable_ticket_type_ids', $ttId);
                }
                $q->orWhere(function ($q2) use ($eventId) {
                    $q2->where('marketplace_event_id', $eventId)
                       ->whereNull('ticket_type_id')
                       ->where(function ($q3) {
                           $q3->whereNull('applicable_ticket_type_ids')
                              ->orWhere('applicable_ticket_type_ids', '[]');
                       });
                });
            })
            ->get();

        $rowsToKeep = [];

        DB::transaction(function () use ($event, $ticketTypes, $promos, &$rowsToKeep, $eventId) {
            foreach ($ticketTypes as $tt) {
                $seriesStart = (string) ($tt->series_start ?? '');
                $totalStock = (int) ($tt->quota_total ?? $tt->capacity ?? 0);

                // PARENT row.
                $rowsToKeep[] = $this->upsertRow(
                    $eventId,
                    $tt,
                    null,
                    false,
                    $totalStock,
                    EventTicketTypePromoSeries::derivePrefix($seriesStart, null, false)
                )->id;

                // INTRINSIC RED row (when ticket type has its own discount %).
                if ((float) ($tt->discount_percent ?? 0) > 0) {
                    $rowsToKeep[] = $this->upsertRow(
                        $eventId,
                        $tt,
                        null,
                        true,
                        $totalStock,
                        EventTicketTypePromoSeries::derivePrefix($seriesStart, null, true)
                    )->id;
                }

                // PROMO rows.
                foreach ($promos as $promo) {
                    if (!$this->promoAppliesToType($promo, $tt, $eventId)) {
                        continue;
                    }
                    $allocation = $totalStock;
                    if ($promo->usage_limit && $promo->usage_limit > 0) {
                        $allocation = min((int) $promo->usage_limit, $totalStock);
                    }
                    $rowsToKeep[] = $this->upsertRow(
                        $eventId,
                        $tt,
                        $promo,
                        false,
                        $allocation,
                        EventTicketTypePromoSeries::derivePrefix($seriesStart, $promo->code, false)
                    )->id;
                }
            }

            // Stale rows — keep on record but zero out qty_allocated so the
            // documents stop counting them.
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
     * Source of truth: order.meta.promo_code.id (modern) or .code (older),
     * joined with the tickets table for the per-type count. Tickets without
     * a promo code attribute to the parent row.
     */
    public function refreshQtySold(Event $event): void
    {
        $eventId = $event->id;
        $ticketTypes = $event->ticketTypes;
        if (!$ticketTypes || $ticketTypes->isEmpty()) {
            return;
        }

        // Pull all paid tickets + their orders' promo meta in one go.
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
            ->select('tickets.ticket_type_id', 'orders.promo_code_id', 'orders.promo_code', 'orders.meta')
            ->get();

        // Bucket: [ticket_type_id][promo_code_id or 'PARENT'] => count
        $buckets = [];
        foreach ($rows as $r) {
            $ttId = (int) $r->ticket_type_id;
            $promoId = $r->promo_code_id ? (int) $r->promo_code_id : null;
            // meta is JSON text in pgsql; decode and try to find an id in
            // meta.promo_code.id (canonical for marketplace flow).
            if ($promoId === null && $r->meta) {
                $meta = is_string($r->meta) ? json_decode($r->meta, true) : (is_array($r->meta) ? $r->meta : []);
                $promoId = $meta['promo_code']['id'] ?? null;
                if ($promoId !== null) $promoId = (int) $promoId;
            }
            $bucketKey = $promoId !== null ? (string) $promoId : 'PARENT';
            $buckets[$ttId][$bucketKey] = ($buckets[$ttId][$bucketKey] ?? 0) + 1;
        }

        // Push counts back to the rows. Tiers that have no sales get 0.
        $allRows = EventTicketTypePromoSeries::query()
            ->whereIn('ticket_type_id', $ticketTypes->pluck('id'))
            ->get();

        foreach ($allRows as $row) {
            $ttId = (int) $row->ticket_type_id;
            $bucketKey = $row->promo_code_id !== null ? (string) $row->promo_code_id : 'PARENT';
            // RED rows are accounted to PARENT bucket too for now — we
            // can't distinguish at order level which sales were "RED" vs
            // plain full-price (the discount is baked into ticket.price
            // for sale_price reductions). Refinement deferred.
            if ($row->is_intrinsic_red) {
                $bucketKey = 'INTRINSIC_RED_NO_BUCKET';
            }
            $row->qty_sold = (int) ($buckets[$ttId][$bucketKey] ?? 0);
            $row->save();
        }
    }

    /**
     * Retrieve all rows for an event, sorted for fiscal display.
     */
    public function getForEvent(Event $event): \Illuminate\Support\Collection
    {
        $ticketTypeIds = $event->ticketTypes->pluck('id')->all();
        if (empty($ticketTypeIds)) {
            return collect();
        }
        return EventTicketTypePromoSeries::query()
            ->whereIn('ticket_type_id', $ticketTypeIds)
            ->with('promoCode', 'ticketType')
            ->get()
            ->sortBy([
                fn ($r) => (string) ($r->ticketType?->name ?? ''),
                fn ($r) => $r->is_intrinsic_red || $r->promo_code_id !== null ? 1 : 0,
                fn ($r) => $r->promo_code_id ?? PHP_INT_MAX,
            ])
            ->values();
    }

    private function upsertRow(int $eventId, TicketType $tt, ?MarketplaceOrganizerPromoCode $promo, bool $isRed, int $allocated, string $prefix): EventTicketTypePromoSeries
    {
        return EventTicketTypePromoSeries::updateOrCreate(
            [
                'ticket_type_id' => $tt->id,
                'promo_code_id' => $promo?->id,
                'is_intrinsic_red' => $isRed,
            ],
            [
                'marketplace_event_id' => $eventId,
                'series_prefix' => $prefix,
                'qty_allocated' => $allocated,
            ]
        );
    }

    private function promoAppliesToType(MarketplaceOrganizerPromoCode $promo, TicketType $tt, int $eventId): bool
    {
        // Direct match — single ticket_type_id or array.
        $applicableIds = $promo->getApplicableTicketTypeIdsList();
        if (!empty($applicableIds) && in_array((int) $tt->id, $applicableIds, true)) {
            return true;
        }

        // Event-scoped promo that doesn't restrict types — applies to every
        // type of the event.
        if ((int) ($promo->marketplace_event_id ?? 0) === $eventId
            && empty($applicableIds)) {
            return true;
        }

        return false;
    }
}
