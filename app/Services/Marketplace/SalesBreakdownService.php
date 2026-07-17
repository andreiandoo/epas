<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Carbon\Carbon;

/**
 * Single source of truth for per-ticket-type sales math.
 *
 * Used by:
 *  - EventResource "Vânzări" tab on the event-edit page
 *  - Payout creation (Filament admin manual, organizer API request, scheduled
 *    auto-decont command), via {@see buildForPayout()}
 *
 * The math here matches the event-edit view exactly. Same allocation rules:
 *  - unit price = the price actually paid per ticket (tickets.price), with
 *    fallbacks to order_items.unit_price and finally to the ticket type's
 *    catalog price. Sale-period reductions are reflected because we read
 *    each ticket's stored price.
 *  - discount allocated per slice (percentage → by gross share, fixed → per
 *    ticket of the order)
 *  - extras (insurance, cultural-card surcharge) allocated proportionally
 *    by valid-gross share of the order
 *  - commission_mode determines whether commission is subtracted from net
 *    (`included` does, `added_on_top` doesn't — the customer paid it on top)
 */
class SalesBreakdownService
{
    /**
     * Order sources that count as physical POS (sold at the door / on-site),
     * NOT online marketplace checkout. These are excluded by excludePos and
     * are the only ones matched by onlyPos.
     *
     * - 'pos_app'          → Tixello mobile POS/scanner app
     * - 'pos'              → leisure POS (LeisureController — Sf. Ana etc.)
     *
     * Kept as a single list so the online/POS split stays consistent between
     * the Vânzări card and the payout builds. ('venue_owner_pos' is treated as
     * POS elsewhere in the app but historically was NOT excluded here; left out
     * to avoid retroactively changing existing payout math for it.)
     */
    public const POS_SOURCES = ['pos_app', 'pos'];

    /**
     * Build the sales breakdown for an event, optionally scoped to a date
     * range on order.created_at.
     *
     * @return array{
     *   total_revenue: float,
     *   total_net: float,
     *   total_commission: float,
     *   total_extras: float,
     *   total_discount: float,
     *   per_type: array<int, array{
     *     tt: \App\Models\TicketType,
     *     ticket_type_id: int,
     *     ticket_type_name: string,
     *     qty: int,
     *     price: float,
     *     gross: float,
     *     commission_per_ticket: float,
     *     commission_amount: float,
     *     commission_mode: string,
     *     commission_type: string|null,
     *     commission_rate: float|null,
     *     commission_fixed: float|null,
     *     discount: float,
     *     extras: float,
     *     net: float
     *   }>
     * }
     */
    public function build(Event $event, ?Carbon $periodStart = null, ?Carbon $periodEnd = null, bool $excludePos = false, string $dateColumn = 'created_at', bool $splitByPrice = false, bool $exactBounds = false, bool $onlyPos = false): array
    {
        // Some callers (e.g. the dashboard daily report) pass an Event loaded
        // with a lean column projection. This breakdown needs several fields —
        // display_template (leisure valuation), the commission settings and the
        // organizer/tenant/client FKs (rate + floor). If any are missing, load
        // the full row once so no caller can silently skew revenue OR commission.
        $requiredCols = ['display_template', 'marketplace_organizer_id', 'marketplace_client_id', 'tenant_id', 'commission_rate', 'commission_mode'];
        if (array_diff($requiredCols, array_keys($event->getAttributes()))) {
            $full = Event::find($event->getKey());
            if ($full) {
                $event = $full;
            }
        }

        $eventId = $event->id;

        // dateColumn is the order column the period bounds apply to.
        // 'created_at' (default) keeps the historical payout behaviour;
        // sales-report passes 'paid_at' so its compact view matches the
        // extended view, which queries Order directly on paid_at.
        $allowedColumns = ['created_at', 'paid_at'];
        $dateColumn = in_array($dateColumn, $allowedColumns, true) ? $dateColumn : 'created_at';

        // exactBounds = true: use the Carbon datetimes as-is (precise to the
        // second). Use this when slicing payouts so consecutive payouts on
        // the same day don't overlap — the new payout's periodStart is the
        // previous payout's created_at, and we need a strict cut at that
        // moment. Legacy date-range queries (event-edit Vânzări tab, sales
        // report) keep the startOfDay/endOfDay behaviour because they pass
        // dates, not datetimes.
        $tickets = Ticket::where(fn ($q) => $q->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId))
            ->whereIn('status', ['valid', 'used'])
            ->where(function ($outer) use ($periodStart, $periodEnd, $excludePos, $dateColumn, $exactBounds, $onlyPos) {
                // Normal flow: ticket tied to a paid/confirmed/completed order
                $outer->whereHas('order', function ($q) use ($periodStart, $periodEnd, $excludePos, $dateColumn, $exactBounds, $onlyPos) {
                    $q->whereIn('status', ['paid', 'confirmed', 'completed'])
                        // Test POS smoke-test sales never touch the payout
                        // pipeline, Vânzări totals, or POS-billed slices
                        // — they're strictly for the mobile app to
                        // exercise sell+print+scan without polluting
                        // the organizer's revenue. Excluded unconditionally
                        // alongside external_import.
                        ->whereNotIn('source', ['external_import', 'pos_test']);
                    if ($onlyPos) {
                        // POS-only slice: physical POS sales (mobile POS app +
                        // leisure POS). Used to bill POS commission separately
                        // (these never flow through a marketplace decont).
                        $q->whereIn('source', self::POS_SOURCES);
                    } elseif ($excludePos) {
                        $q->whereNotIn('source', array_merge(self::POS_SOURCES, ['test_order']));
                    }
                    if ($periodStart) {
                        // exactBounds uses '>' so the new payout doesn't
                        // re-include orders already in the previous one
                        // (whose created_at = this payout's periodStart).
                        $operator = $exactBounds ? '>' : '>=';
                        $bound = $exactBounds ? $periodStart : $periodStart->copy()->startOfDay();
                        $q->where($dateColumn, $operator, $bound);
                    }
                    if ($periodEnd) {
                        $bound = $exactBounds ? $periodEnd : $periodEnd->copy()->endOfDay();
                        $q->where($dateColumn, '<=', $bound);
                    }
                });
                // Invitations have no order_id. They were silently dropped by
                // whereHas above before this branch was added — which made
                // the "Invitatie" row disappear from the payout breakdown
                // after a recalc. Period bounds apply to tickets.created_at
                // instead since there's no order date to anchor on.
                // Skipped entirely for the POS-only slice — invitations are
                // not POS sales.
                if (!$onlyPos) {
                    $outer->orWhere(function ($q2) use ($periodStart, $periodEnd, $exactBounds) {
                        $q2->whereNull('order_id');
                        if ($periodStart) {
                            $operator = $exactBounds ? '>' : '>=';
                            $bound = $exactBounds ? $periodStart : $periodStart->copy()->startOfDay();
                            $q2->where('created_at', $operator, $bound);
                        }
                        if ($periodEnd) {
                            $bound = $exactBounds ? $periodEnd : $periodEnd->copy()->endOfDay();
                            $q2->where('created_at', '<=', $bound);
                        }
                    });
                }
            })
            ->with(['ticketType'])
            ->get(['id', 'order_id', 'ticket_type_id', 'price']);

        $orderIds = $tickets->pluck('order_id')->filter()->unique()->values();
        $ordersById = $orderIds->isEmpty()
            ? collect()
            : Order::with('items')->whereIn('id', $orderIds)->get()->keyBy('id');

        $totalDiscountCard = 0.0;
        $totalExtrasCard = 0.0;
        foreach ($ordersById as $o) {
            $totalDiscountCard += (float) $o->discount_amount;
            $m = is_array($o->meta) ? $o->meta : [];
            $totalExtrasCard += (float) ($m['insurance_amount'] ?? 0);
            $totalExtrasCard += (float) ($m['cultural_card_surcharge'] ?? 0);
        }

        $totalTicketsPerOrder = $orderIds->isEmpty()
            ? collect()
            : Ticket::whereIn('order_id', $orderIds)
                ->selectRaw('order_id, COUNT(*) as cnt')
                ->groupBy('order_id')
                ->pluck('cnt', 'order_id');

        $defaultRate = (float) (
            $event->commission_rate
            ?? $event->marketplaceOrganizer?->commission_rate
            ?? $event->tenant?->commission_rate
            ?? $event->marketplaceClient?->commission_rate
            ?? 5
        );
        $defaultMode = $event->commission_mode
            ?? $event->marketplaceOrganizer?->default_commission_mode
            ?? $event->marketplaceClient?->commission_mode
            ?? 'included';
        // Floor comision per bilet — DOAR cand organizator opt-in via
        // commission_use_floor === true. Cand NU bifat: floor=0, deci comportament
        // vechi (pur procentual). Aliniat cu customer checkout + raport backend.
        $organizerFloor = ($event->marketplaceOrganizer?->commission_use_floor)
            ? (float) ($event->marketplaceOrganizer?->fixed_commission_default ?? 0)
            : 0.0;

        // Leisure events (Sf. Ana etc.) sell packages whose components carry
        // price 0 (value is in the parent) plus free/comp tickets. Value those
        // per-ticket via getEffectivePrice() so the parent counts as X and the
        // components/free tickets count as 0 — see the leisure branch in the
        // slice loop. Scoped strictly to leisure_venue so no other marketplace
        // is affected. ($event is guaranteed to have display_template loaded by
        // the lean-projection guard at the top of build().)
        $isLeisure = ($event->display_template ?? null) === 'leisure_venue';

        $perType = [];
        $sumValidGross = 0.0;
        $sumOnTop = 0.0;
        $sumIncluded = 0.0;
        $sumDiscountValid = 0.0;
        $sumExtrasValid = 0.0;

        foreach ($tickets->groupBy('order_id') as $orderId => $orderTickets) {
            // Invitation tickets have no order. Emit them as zero-value
            // slices grouped by ticket_type so the Invitatie row stays
            // visible on the payout breakdown even after a recalc.
            if (!$orderId) {
                foreach ($orderTickets->groupBy('ticket_type_id') as $invTtId => $invGroup) {
                    $invTt = $invGroup->first()->ticketType;
                    if (!$invTt) continue;
                    $invKey = (string) $invTtId;
                    if (!isset($perType[$invKey])) {
                        $perType[$invKey] = [
                            'tt' => $invTt,
                            'ticket_type_id' => (int) $invTtId,
                            'unit_price' => 0.0,
                            'valid_count' => 0,
                            'gross' => 0.0,
                            'commission' => 0.0,
                            'commission_per_ticket' => 0.0,
                            'discount' => 0.0,
                            'extras' => 0.0,
                            'net' => 0.0,
                            'mode' => 'included',
                            'commission_type' => null,
                            'commission_rate' => null,
                            'commission_fixed' => null,
                        ];
                    }
                    $perType[$invKey]['valid_count'] += $invGroup->count();
                }
                continue;
            }
            $order = $ordersById->get($orderId);
            if (!$order) continue;

            $orderValidGross = 0.0;
            $orderValidCount = 0;
            $orderOnTop = 0.0;
            $orderIncluded = 0.0;
            $ttSlices = [];

            // When splitByPrice is enabled, split each ticket_type into one slice
            // per distinct unit price (ticket.price). That way Categoria III sold
            // at two price tiers (e.g. 37 at 60 + 3 at 48 due to sale_price) shows
            // as two breakdown rows. The legacy single-slice-per-type behaviour is
            // preserved when splitByPrice=false.
            $sliceGroups = $splitByPrice
                ? $orderTickets->groupBy(function ($t) {
                    return $t->ticket_type_id . '|' . number_format((float) ($t->price ?? 0), 4, '.', '');
                })
                : $orderTickets->groupBy('ticket_type_id');

            foreach ($sliceGroups as $sliceKey => $group) {
                $ttId = (int) ($group->first()->ticket_type_id ?? 0);
                $tt = $group->first()->ticketType;
                if (!$tt) continue;

                $validCount = $group->count();

                $effective = $tt->getEffectiveCommission($defaultRate, $defaultMode);
                $mode = $effective['mode'];

                if ($isLeisure) {
                    // LEISURE: value each ticket by its EFFECTIVE price. Package
                    // components (price 0 — value carried by the parent) and
                    // free/comp tickets count as 0; never fall back to catalog.
                    // Commission is per-ticket (floor-aware) on tickets with
                    // price > 0. Aligns with Ticket::getEffectivePrice() and the
                    // leisure reports (matches order.total). Scoped to leisure —
                    // the else branch below is the unchanged legacy behaviour.
                    $gross = 0.0;
                    $commission = 0.0;
                    foreach ($group as $lt) {
                        if (!$lt->relationLoaded('order')) {
                            $lt->setRelation('order', $order);
                        }
                        $eff = (float) $lt->getEffectivePrice();
                        $gross += $eff;
                        if ($eff > 0) {
                            $cpt = (float) $tt->calculateCommission($eff, $defaultRate, $defaultMode);
                            if ($organizerFloor > 0 && $cpt < $organizerFloor) {
                                $cpt = $organizerFloor;
                            }
                            $commission += $cpt;
                        }
                    }
                    $gross = round($gross, 2);
                    $commission = round($commission, 2);
                    $unitPrice = $validCount > 0 ? round($gross / $validCount, 2) : 0.0;
                    $commPerTicket = $validCount > 0 ? round($commission / $validCount, 2) : 0.0;
                } else {
                    $firstTicketPrice = (float) ($group->first()->price ?? 0);
                    if ($firstTicketPrice > 0) {
                        $unitPrice = $firstTicketPrice;
                    } else {
                        $orderItem = $order->items->first(fn ($it) => (int) $it->ticket_type_id === (int) $ttId);
                        if ($orderItem && (float) $orderItem->unit_price > 0) {
                            $unitPrice = (float) $orderItem->unit_price;
                        } else {
                            $unitPrice = ((int) ($tt->sale_price_cents ?? 0) > 0
                                ? $tt->sale_price_cents
                                : (int) ($tt->price_cents ?? 0)) / 100;
                        }
                    }

                    $gross = $unitPrice * $validCount;
                    $commPerTicket = (float) $tt->calculateCommission($unitPrice, $defaultRate, $defaultMode);
                    // Aplica floor per bilet daca organizator > 0 si biletul are pret > 0
                    if ($organizerFloor > 0 && $unitPrice > 0 && $commPerTicket < $organizerFloor) {
                        $commPerTicket = $organizerFloor;
                    }
                    $commission = $commPerTicket * $validCount;
                }

                // Composite accumulator key — same as $sliceKey when splitByPrice,
                // otherwise just the ttId (legacy shape).
                $accumKey = $splitByPrice ? (string) $sliceKey : (string) $ttId;

                $ttSlices[$accumKey] = [
                    'tt' => $tt,
                    'ticket_type_id' => $ttId,
                    'valid_count' => $validCount,
                    'gross' => $gross,
                    'unit_price' => $unitPrice,
                    'commission' => $commission,
                    'commission_per_ticket' => $commPerTicket,
                    'mode' => $mode,
                    'commission_type' => $effective['type'] ?? null,
                    'commission_rate' => isset($effective['rate']) ? (float) $effective['rate'] : null,
                    'commission_fixed' => isset($effective['fixed']) ? (float) $effective['fixed'] : null,
                ];

                $orderValidGross += $gross;
                $orderValidCount += $validCount;
                if (in_array($mode, ['on_top', 'added_on_top'], true)) {
                    $orderOnTop += $commission;
                } else {
                    $orderIncluded += $commission;
                }
            }

            $orderDiscount = (float) $order->discount_amount;
            $orderSubtotal = (float) $order->subtotal;
            $meta = is_array($order->meta) ? $order->meta : [];
            $promoInfo = is_array($meta['promo_code'] ?? null) ? $meta['promo_code'] : null;
            $promoType = $promoInfo['type'] ?? null;

            $discountValid = 0.0;
            $discountRate = null;
            $discountPerTicket = null;
            if ($orderDiscount > 0) {
                if ($promoType === 'percentage' && $orderSubtotal > 0) {
                    $discountRate = $orderDiscount / $orderSubtotal;
                    $discountValid = $orderValidGross * $discountRate;
                } else {
                    $totalCount = (int) ($totalTicketsPerOrder[$orderId] ?? $orderValidCount);
                    if ($totalCount > 0) {
                        $discountPerTicket = $orderDiscount / $totalCount;
                        $discountValid = $orderValidCount * $discountPerTicket;
                    }
                }
                if ($discountValid > $orderDiscount) {
                    $discountValid = $orderDiscount;
                    if ($discountRate !== null && $orderValidGross > 0) {
                        $discountRate = $discountValid / $orderValidGross;
                    } elseif ($discountPerTicket !== null && $orderValidCount > 0) {
                        $discountPerTicket = $discountValid / $orderValidCount;
                    }
                }
            }

            $insurance = (float) ($meta['insurance_amount'] ?? 0);
            $surcharge = (float) ($meta['cultural_card_surcharge'] ?? 0);
            $extrasValid = 0.0;
            if (($insurance + $surcharge) > 0) {
                $ratio = $orderSubtotal > 0
                    ? min(1.0, $orderValidGross / $orderSubtotal)
                    : 1.0;
                $extrasValid = ($insurance + $surcharge) * $ratio;
            }

            foreach ($ttSlices as $accumKey => $slice) {
                $sliceDiscount = 0.0;
                if ($discountRate !== null) {
                    $sliceDiscount = $slice['gross'] * $discountRate;
                } elseif ($discountPerTicket !== null) {
                    $sliceDiscount = $slice['valid_count'] * $discountPerTicket;
                }

                $sliceExtras = 0.0;
                if ($extrasValid > 0 && $orderValidGross > 0) {
                    $sliceExtras = $extrasValid * ($slice['gross'] / $orderValidGross);
                }

                $sliceNet = $slice['gross'] - $sliceDiscount - $sliceExtras;
                if (!in_array($slice['mode'], ['on_top', 'added_on_top'], true)) {
                    $sliceNet -= $slice['commission'];
                }
                if ($sliceNet < 0) $sliceNet = 0.0;

                if (!isset($perType[$accumKey])) {
                    $perType[$accumKey] = [
                        'tt' => $slice['tt'],
                        'ticket_type_id' => $slice['ticket_type_id'],
                        'unit_price' => $slice['unit_price'],
                        'valid_count' => 0,
                        'gross' => 0.0,
                        'commission' => 0.0,
                        'commission_per_ticket' => 0.0,
                        'discount' => 0.0,
                        'extras' => 0.0,
                        'net' => 0.0,
                        'mode' => $slice['mode'],
                        'commission_type' => $slice['commission_type'],
                        'commission_rate' => $slice['commission_rate'],
                        'commission_fixed' => $slice['commission_fixed'],
                    ];
                }
                $perType[$accumKey]['valid_count'] += $slice['valid_count'];
                $perType[$accumKey]['gross'] += $slice['gross'];
                $perType[$accumKey]['commission'] += $slice['commission'];
                $perType[$accumKey]['discount'] += $sliceDiscount;
                $perType[$accumKey]['extras'] += $sliceExtras;
                $perType[$accumKey]['net'] += $sliceNet;
            }

            $sumValidGross += $orderValidGross;
            $sumOnTop += $orderOnTop;
            $sumIncluded += $orderIncluded;
            $sumDiscountValid += $discountValid;
            $sumExtrasValid += $extrasValid;
        }

        $totalCommission = $sumOnTop + $sumIncluded;
        $totalNet = max(0.0, $sumValidGross - $sumDiscountValid - $sumIncluded - $sumExtrasValid);
        $totalRevenue = $sumValidGross + $sumOnTop + $sumExtrasValid;

        // Commission the platform KEPT from refunded orders — refunds
        // where the operator chose "fara taxa" (commission_refunded=false
        // on the item) mean the 6% fee never went back to the customer;
        // the platform earned it. Historical build() dropped the whole
        // refunded order (order.status='refunded' + ticket.status='refunded'
        // both filtered out), so this kept portion silently disappeared
        // from total_commission and the /marketplace/events/{id}?tab=vanzari
        // header read as if the refund had reversed the taxa too.
        // Repro: order MKT-FFNIQWKJ on event 4601 (refund_request 75) —
        // commission_refund=0.00, four refund_items with commission_amount
        // 4.20 each and commission_refunded=false; total_commission was
        // 357.00, should have been 373.80.
        //
        // Sum ri.commission_amount over refund_items where the parent
        // ticket belongs to this event AND commission stayed with the
        // platform. Bounded by the same periodStart / periodEnd used
        // above so payout slices remain historically consistent — the
        // kept-commission anchor is the refund_item's updated_at (the
        // moment the refund was actually processed).
        $keptCommissionQ = \DB::table('marketplace_refund_items as ri')
            ->join('tickets as t', 'ri.ticket_id', '=', 't.id')
            ->where(function ($q) use ($eventId) {
                $q->where('t.event_id', $eventId)
                    ->orWhere('t.marketplace_event_id', $eventId);
            })
            ->where('ri.commission_refunded', false)
            ->where('ri.status', 'refunded');
        if ($periodStart) {
            $operator = $exactBounds ? '>' : '>=';
            $bound = $exactBounds ? $periodStart : $periodStart->copy()->startOfDay();
            $keptCommissionQ->where('ri.updated_at', $operator, $bound);
        }
        if ($periodEnd) {
            $bound = $exactBounds ? $periodEnd : $periodEnd->copy()->endOfDay();
            $keptCommissionQ->where('ri.updated_at', '<=', $bound);
        }
        $keptCommission = (float) $keptCommissionQ->sum('ri.commission_amount');
        // NB: we DO NOT fold $keptCommission into $totalCommission here
        // — summarizeForPayout / buildForPayout would silently
        // double-count refund commission on payouts (already tracked
        // separately by the payout pipeline). The Vanzari header adds
        // it in for display only, using the dedicated key emitted below.
        // Refund principal that WAS returned to the customer (refund_amount
        // rows only accumulate the ticket face value we sent back). Kept
        // for transparency — surfaces on tab=vanzari so the operator sees
        // both sides of every refund without having to open the payout.
        $refundedPrincipalQ = \DB::table('marketplace_refund_items as ri')
            ->join('tickets as t', 'ri.ticket_id', '=', 't.id')
            ->where(function ($q) use ($eventId) {
                $q->where('t.event_id', $eventId)
                    ->orWhere('t.marketplace_event_id', $eventId);
            })
            ->where('ri.status', 'refunded');
        if ($periodStart) {
            $operator = $exactBounds ? '>' : '>=';
            $bound = $exactBounds ? $periodStart : $periodStart->copy()->startOfDay();
            $refundedPrincipalQ->where('ri.updated_at', $operator, $bound);
        }
        if ($periodEnd) {
            $bound = $exactBounds ? $periodEnd : $periodEnd->copy()->endOfDay();
            $refundedPrincipalQ->where('ri.updated_at', '<=', $bound);
        }
        $refundedPrincipal = (float) $refundedPrincipalQ->sum('ri.refund_amount');

        // F4 — sum payment processing fees across the orders touched by this
        // breakdown. orders.processing_fee_cents is 0 for marketplaces without
        // payment_fees configured (kill switch), so this total stays 0 on
        // Ambilet / Tics — reports + payouts render unchanged there.
        $totalProcessingFeeCents = $orderIds->isEmpty()
            ? 0
            : (int) Order::whereIn('id', $orderIds)->sum('processing_fee_cents');
        $totalProcessingFee = round($totalProcessingFeeCents / 100, 2);

        $finalPerType = [];
        foreach ($perType as $accumKey => $d) {
            $qty = (int) $d['valid_count'];
            $gross = (float) $d['gross'];
            $ttId = (int) $d['ticket_type_id'];
            // When splitByPrice, all tickets in a slice share the same unit_price
            // by construction (it's part of the accumKey). Otherwise fall back to
            // the legacy weighted-average from gross/qty.
            $effectivePrice = $splitByPrice
                ? round((float) $d['unit_price'], 2)
                : ($qty > 0 ? round($gross / $qty, 2) : 0.0);
            $weightedCommissionPerTicket = $qty > 0 ? round($d['commission'] / $qty, 4) : 0.0;

            $finalPerType[$accumKey] = [
                'tt' => $d['tt'],
                'ticket_type_id' => $ttId,
                'ticket_type_name' => (string) ($d['tt']->name ?? ''),
                'qty' => $qty,
                'price' => $effectivePrice,
                'gross' => round($gross, 2),
                'commission_per_ticket' => $weightedCommissionPerTicket,
                'commission_amount' => round($d['commission'], 2),
                'commission_mode' => (string) $d['mode'],
                'commission_type' => $d['commission_type'],
                'commission_rate' => $d['commission_rate'],
                'commission_fixed' => $d['commission_fixed'],
                'discount' => round($d['discount'], 2),
                'extras' => round($d['extras'], 2),
                'net' => round($d['net'], 2),
            ];
        }

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_net' => round($totalNet, 2),
            'total_commission' => round($totalCommission, 2),
            // Refund telemetry — surfaced so tab=vanzari can render a
            // "din care taxa retinuta din refund-uri: X.YZ" line and the
            // operator no longer has to reverse-engineer where the
            // number came from. Both default to 0.00 when no refunds
            // exist for the period, matching legacy output.
            'total_commission_kept_from_refunds' => round($keptCommission, 2),
            'total_refunded_principal' => round($refundedPrincipal, 2),
            'total_extras' => round($totalExtrasCard, 2),
            'total_discount' => round($totalDiscountCard, 2),
            // F4 — processing fee summed across orders in this period.
            // Zero on marketplaces without payment_fees opted in (kill switch).
            'total_processing_fee' => $totalProcessingFee,
            'per_type' => $finalPerType,
        ];
    }

    /**
     * Build the array stored on `payouts.ticket_breakdown`. Each row is
     * self-sufficient — the payout-detail blade can render Net final without
     * re-querying anything.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildForPayout(Event $event, ?Carbon $periodStart = null, ?Carbon $periodEnd = null, bool $exactBounds = false): array
    {
        // Aggregate per ticket type. The "split by price tier + promo" view
        // is produced by buildPayoutSplitTable() and rendered as a second
        // table below this one. exactBounds plumbs through to build() so the
        // payout slice respects strict datetime boundaries (no overlap with
        // the previous payout when periods touch).
        //
        // excludePos=true: POS tickets (sold via the organizer's mobile cash
        // POS app) NEVER belong to a marketplace decont. The organizer
        // already collected those funds at the door; the marketplace bills
        // their commission separately. Same goes for test_order /
        // external_import sources. Without this filter ticket_breakdown
        // includes POS rows, the modal repeater shows POS lines, and the
        // gross/net totals don't match the "Sold disponibil" card (which
        // already excludes POS).
        $breakdown = $this->build($event, $periodStart, $periodEnd, excludePos: true, dateColumn: 'created_at', splitByPrice: false, exactBounds: $exactBounds);

        return collect($breakdown['per_type'])->values()->map(function (array $row) {
            // Strip the TicketType model — Eloquent objects don't survive JSON casting cleanly.
            unset($row['tt']);
            // Storage convention used by MarketplacePayout::getBreakdownTotals(): `quantity` aliases qty,
            // and `unit_price` aliases price. Keep both so legacy readers still work.
            $row['quantity'] = $row['qty'];
            $row['unit_price'] = $row['price'];
            return $row;
        })->all();
    }

    /**
     * POS-only per-ticket-type breakdown for the payout period — the mirror
     * of buildForPayout() but restricted to POS sales (self::POS_SOURCES). Used to bill
     * the organizer's POS commission on a separate invoice, since POS sales
     * never flow through a marketplace decont (and are therefore excluded from
     * buildForPayout / ticket_breakdown). Same row shape as buildForPayout.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildPosForPayout(Event $event, ?Carbon $periodStart = null, ?Carbon $periodEnd = null, bool $exactBounds = false): array
    {
        $breakdown = $this->build($event, $periodStart, $periodEnd, excludePos: false, dateColumn: 'created_at', splitByPrice: false, exactBounds: $exactBounds, onlyPos: true);

        return collect($breakdown['per_type'])->values()->map(function (array $row) {
            unset($row['tt']);
            $row['quantity'] = $row['qty'];
            $row['unit_price'] = $row['price'];
            return $row;
        })->all();
    }

    /**
     * Per-(ticket_type, unit_price, promo_code) split table for the payout
     * "Detalii bilete" view. Each row is one price tier of one type, with a
     * "(redus)" name suffix and a Tip reducere label when the row corresponds
     * to a promo-coded purchase. Read on-demand from the blade — no JSON
     * snapshot.
     *
     * @return array<int, array{
     *   ticket_type_id: int,
     *   ticket_type_name: string,
     *   display_name: string,
     *   is_reduced: bool,
     *   price: float,
     *   qty: int,
     *   gross: float,
     *   commission_per_ticket: float,
     *   commission_amount: float,
     *   commission_mode: string,
     *   commission_type: string|null,
     *   commission_rate: float|null,
     *   commission_fixed: float|null,
     *   net: float,
     *   promo_code: string|null,
     *   promo_label: string
     * }>
     */
    public function buildPayoutSplitTable(Event $event, ?Carbon $periodStart = null, ?Carbon $periodEnd = null, bool $excludePos = true, string $dateColumn = 'created_at', bool $exactBounds = false): array
    {
        $eventId = $event->id;
        $allowedColumns = ['created_at', 'paid_at'];
        $dateColumn = in_array($dateColumn, $allowedColumns, true) ? $dateColumn : 'created_at';

        $tickets = Ticket::where(fn ($q) => $q->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId))
            ->whereIn('status', ['valid', 'used'])
            ->where(function ($outer) use ($periodStart, $periodEnd, $excludePos, $dateColumn, $exactBounds) {
                $outer->whereHas('order', function ($q) use ($periodStart, $periodEnd, $excludePos, $dateColumn, $exactBounds) {
                    $q->whereIn('status', ['paid', 'confirmed', 'completed'])
                        ->whereNotIn('source', ['external_import', 'pos_test']);
                    if ($excludePos) {
                        $q->whereNotIn('source', array_merge(self::POS_SOURCES, ['test_order']));
                    }
                    if ($periodStart) {
                        $operator = $exactBounds ? '>' : '>=';
                        $bound = $exactBounds ? $periodStart : $periodStart->copy()->startOfDay();
                        $q->where($dateColumn, $operator, $bound);
                    }
                    if ($periodEnd) {
                        $bound = $exactBounds ? $periodEnd : $periodEnd->copy()->endOfDay();
                        $q->where($dateColumn, '<=', $bound);
                    }
                });
                // Invitations have order_id=NULL — include them so the split
                // table mirrors the aggregate (which we already fixed to
                // surface invitations). Period bounds anchor on tickets.created_at
                // because there's no order date to use.
                $outer->orWhere(function ($q2) use ($periodStart, $periodEnd, $exactBounds) {
                    $q2->whereNull('order_id');
                    if ($periodStart) {
                        $operator = $exactBounds ? '>' : '>=';
                        $bound = $exactBounds ? $periodStart : $periodStart->copy()->startOfDay();
                        $q2->where('created_at', $operator, $bound);
                    }
                    if ($periodEnd) {
                        $bound = $exactBounds ? $periodEnd : $periodEnd->copy()->endOfDay();
                        $q2->where('created_at', '<=', $bound);
                    }
                });
            })
            ->with(['ticketType'])
            ->get(['id', 'order_id', 'ticket_type_id', 'price', 'meta']);

        if ($tickets->isEmpty()) {
            return [];
        }

        $orderIds = $tickets->pluck('order_id')->filter()->unique()->values();
        $ordersById = Order::whereIn('id', $orderIds)->get(['id', 'discount_amount', 'subtotal', 'promo_code', 'meta'])->keyBy('id');

        $defaultRate = (float) (
            $event->commission_rate
            ?? $event->marketplaceOrganizer?->commission_rate
            ?? $event->tenant?->commission_rate
            ?? $event->marketplaceClient?->commission_rate
            ?? 5
        );
        $defaultMode = $event->commission_mode
            ?? $event->marketplaceOrganizer?->default_commission_mode
            ?? $event->marketplaceClient?->commission_mode
            ?? 'included';
        // Floor comision per bilet — DOAR cand organizator opt-in via
        // commission_use_floor === true. Cand NU bifat: floor=0 (comportament
        // vechi, pur procentual). Aliniat cu buildCurrent() de mai sus.
        $organizerFloor = ($event->marketplaceOrganizer?->commission_use_floor)
            ? (float) ($event->marketplaceOrganizer?->fixed_commission_default ?? 0)
            : 0.0;

        $currency = $event->currency ?? 'RON';

        // Group key: ticket_type_id | rounded_effective_price | promo_code
        // (empty when no code). Within a group all tickets share unit price
        // AND promo origin, so each is one distinct table row.
        $groups = [];
        foreach ($tickets as $t) {
            $order = $ordersById->get($t->order_id);
            // Set the order relation so getEffectivePrice picks up the
            // proportional fallback for legacy orders without per-ticket meta.
            if ($order) $t->setRelation('order', $order);

            $effective = round($t->getEffectivePrice(), 2);
            $orderMeta = $order && is_array($order->meta) ? $order->meta : [];
            $promoData = $orderMeta['promo_code'] ?? null;
            $promoCode = is_array($promoData) ? trim((string) ($promoData['code'] ?? '')) : '';
            if ($promoCode === '') {
                $promoCode = trim((string) ($orderMeta['coupon_code'] ?? $order?->promo_code ?? ''));
            }
            // We only treat a row as "reduced" when a promo code actually
            // touched THIS ticket (its meta.discount_amount > 0 in modern
            // checkout, or the legacy proportional path lowered its price).
            $ticketDiscount = round((float) ($t->price ?? 0) - $effective, 2);
            $hasPromoOnThisTicket = $ticketDiscount > 0.01;

            // Group key excludes the code when this specific ticket didn't
            // benefit from a discount — that way a mixed-eligibility order
            // doesn't drag the un-discounted tickets into a "(redus)" row.
            $effectiveCode = $hasPromoOnThisTicket ? $promoCode : '';

            $key = $t->ticket_type_id . '|' . number_format($effective, 4, '.', '') . '|' . $effectiveCode;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'tt' => $t->ticketType,
                    'ticket_type_id' => (int) $t->ticket_type_id,
                    // Display price = what the customer actually paid per ticket.
                    'unit_price' => $effective,
                    // Commission base = pre-promo line price (the catalog/sale
                    // value of this ticket). Marketplace commission is charged
                    // on the full ticket value, NOT the post-promo amount —
                    // promo discount comes out of the organizer's net, not
                    // out of the marketplace's commission.
                    'commission_base' => (float) ($t->price ?? 0),
                    'promo_code' => $effectiveCode,
                    'promo_meta' => $hasPromoOnThisTicket && $promoData ? $promoData : null,
                    'is_reduced' => $hasPromoOnThisTicket,
                    'qty' => 0,
                ];
            }
            $groups[$key]['qty']++;
        }

        $rows = [];
        foreach ($groups as $g) {
            $tt = $g['tt'];
            if (!$tt) continue;
            $qty = (int) $g['qty'];
            $unitPrice = (float) $g['unit_price'];
            // Calculate commission on the pre-promo price — promo discount
            // reduces the organizer's net, not the marketplace's cut. For
            // rows without a promo, commissionBase == unitPrice.
            $commissionBase = (float) $g['commission_base'];

            $commPerTicket = (float) $tt->calculateCommission($commissionBase, $defaultRate, $defaultMode);
            $effectiveCommission = $tt->getEffectiveCommission($defaultRate, $defaultMode);
            // Aplica floor per bilet daca organizatorul are fixed_commission_default > 0
            // si biletul are pret > 0 (biletele bonus/componente pachet = 0 lei NU
            // genereaza comision — floor-ul e o regula pentru bilete platite).
            if ($organizerFloor > 0 && $commissionBase > 0 && $commPerTicket < $organizerFloor) {
                $commPerTicket = $organizerFloor;
            }
            $commissionAmount = $commPerTicket * $qty;
            $mode = $effectiveCommission['mode'];
            $isOnTop = in_array($mode, ['on_top', 'added_on_top'], true);

            // Gross / net use the displayed unit price (post-promo) so the
            // Net column reflects what actually reaches the organizer.
            $gross = $unitPrice * $qty + ($isOnTop ? $commissionAmount : 0);
            $net = $gross - $commissionAmount;

            // Pretty promo label for the Tip reducere column.
            $promoLabel = '-';
            if ($g['is_reduced']) {
                $pm = $g['promo_meta'];
                $codeStr = $g['promo_code'] !== '' ? 'cod: ' . $g['promo_code'] : 'redus';
                if (is_array($pm)) {
                    $type = $pm['type'] ?? null;
                    $val = $pm['value'] ?? null;
                    if ($type === 'percentage' && $val !== null) {
                        $promoLabel = $codeStr . ' (-' . rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.') . '%)';
                    } elseif ($type === 'fixed' && $val !== null) {
                        $promoLabel = $codeStr . ' (-' . number_format((float) $val, 2, '.', '') . ' ' . $currency . ')';
                    } else {
                        $promoLabel = $codeStr;
                    }
                } else {
                    $promoLabel = $codeStr;
                }
            }

            $rows[] = [
                'ticket_type_id' => $g['ticket_type_id'],
                'ticket_type_name' => (string) ($tt->name ?? ''),
                'display_name' => (string) ($tt->name ?? '') . ($g['is_reduced'] ? ' (redus)' : ''),
                'is_reduced' => $g['is_reduced'],
                'price' => round($unitPrice, 2),
                'qty' => $qty,
                'gross' => round($gross, 2),
                'commission_per_ticket' => round($commPerTicket, 4),
                'commission_amount' => round($commissionAmount, 2),
                'commission_mode' => (string) $mode,
                'commission_type' => $effectiveCommission['type'] ?? null,
                'commission_rate' => isset($effectiveCommission['rate']) ? (float) $effectiveCommission['rate'] : null,
                'commission_fixed' => isset($effectiveCommission['fixed']) ? (float) $effectiveCommission['fixed'] : null,
                'net' => round($net, 2),
                'promo_code' => $g['promo_code'] !== '' ? $g['promo_code'] : null,
                'promo_label' => $promoLabel,
            ];
        }

        // Sort: by type name (alphabetic), then non-reduced before reduced,
        // then by price desc — mirrors the user's mock layout.
        usort($rows, function ($a, $b) {
            $cmp = strcmp($a['ticket_type_name'], $b['ticket_type_name']);
            if ($cmp !== 0) return $cmp;
            if ($a['is_reduced'] !== $b['is_reduced']) return $a['is_reduced'] ? 1 : -1;
            return $b['price'] <=> $a['price'];
        });

        return $rows;
    }

    /**
     * Resolve a (commission_mode, commission_amount) pair for the payout-level
     * fields, derived from the same breakdown. `commission_mode` is "dominant":
     * if all rows share one mode, use it; else if any row is added_on_top,
     * treat the whole payout as added_on_top (the customer paid commission on
     * top, so the organizer's net == gross_excl_commission for that row);
     * otherwise fall back to the event's effective mode.
     *
     * @return array{commission_mode: string, commission_amount: float, gross_amount: float, net_amount: float}
     */
    public function summarizeForPayout(Event $event, ?Carbon $periodStart = null, ?Carbon $periodEnd = null, bool $exactBounds = false): array
    {
        // Same excludePos=true rule as buildForPayout — see the comment
        // there. POS tickets never belong to a marketplace decont.
        $breakdown = $this->build($event, $periodStart, $periodEnd, excludePos: true, dateColumn: 'created_at', splitByPrice: false, exactBounds: $exactBounds);

        $modes = collect($breakdown['per_type'])->pluck('commission_mode')->filter()->unique()->values();
        if ($modes->count() === 1) {
            $commissionMode = $modes->first();
        } elseif ($modes->contains('added_on_top') || $modes->contains('on_top')) {
            $commissionMode = 'added_on_top';
        } else {
            $commissionMode = method_exists($event, 'getEffectiveCommissionMode')
                ? $event->getEffectiveCommissionMode()
                : 'included';
        }

        $grossSum = 0.0;
        foreach ($breakdown['per_type'] as $row) {
            // For on_top rows, the "Total brut" displayed on the decont mirrors what the
            // customer paid (price × qty + commission). For included rows it's just price × qty.
            $isOnTop = in_array($row['commission_mode'] ?? null, ['on_top', 'added_on_top'], true);
            $grossSum += $isOnTop
                ? ($row['gross'] + $row['commission_amount'])
                : $row['gross'];
        }

        return [
            'commission_mode' => $commissionMode,
            'commission_amount' => round($breakdown['total_commission'], 2),
            'gross_amount' => round($grossSum, 2),
            'net_amount' => round($breakdown['total_net'], 2),
        ];
    }
}
