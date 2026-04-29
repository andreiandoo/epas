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
    public function build(Event $event, ?Carbon $periodStart = null, ?Carbon $periodEnd = null, bool $excludePos = false): array
    {
        $eventId = $event->id;

        $tickets = Ticket::where(fn ($q) => $q->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId))
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', function ($q) use ($periodStart, $periodEnd, $excludePos) {
                $q->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', '!=', 'external_import');
                if ($excludePos) {
                    $q->where('source', '!=', 'pos_app')
                      ->where('source', '!=', 'test_order');
                }
                if ($periodStart) {
                    $q->where('created_at', '>=', $periodStart->copy()->startOfDay());
                }
                if ($periodEnd) {
                    $q->where('created_at', '<=', $periodEnd->copy()->endOfDay());
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

        $perType = [];
        $sumValidGross = 0.0;
        $sumOnTop = 0.0;
        $sumIncluded = 0.0;
        $sumDiscountValid = 0.0;
        $sumExtrasValid = 0.0;

        foreach ($tickets->groupBy('order_id') as $orderId => $orderTickets) {
            $order = $ordersById->get($orderId);
            if (!$order) continue;

            $orderValidGross = 0.0;
            $orderValidCount = 0;
            $orderOnTop = 0.0;
            $orderIncluded = 0.0;
            $ttSlices = [];

            foreach ($orderTickets->groupBy('ticket_type_id') as $ttId => $group) {
                $tt = $group->first()->ticketType;
                if (!$tt) continue;

                $validCount = $group->count();

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
                $effective = $tt->getEffectiveCommission($defaultRate, $defaultMode);
                $commission = $commPerTicket * $validCount;
                $mode = $effective['mode'];

                $ttSlices[$ttId] = [
                    'tt' => $tt,
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

            foreach ($ttSlices as $ttId => $slice) {
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

                if (!isset($perType[$ttId])) {
                    $perType[$ttId] = [
                        'tt' => $slice['tt'],
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
                $perType[$ttId]['valid_count'] += $slice['valid_count'];
                $perType[$ttId]['gross'] += $slice['gross'];
                $perType[$ttId]['commission'] += $slice['commission'];
                $perType[$ttId]['discount'] += $sliceDiscount;
                $perType[$ttId]['extras'] += $sliceExtras;
                $perType[$ttId]['net'] += $sliceNet;
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

        $finalPerType = [];
        foreach ($perType as $ttId => $d) {
            $qty = (int) $d['valid_count'];
            $gross = (float) $d['gross'];
            $effectivePrice = $qty > 0 ? round($gross / $qty, 2) : 0.0;
            $weightedCommissionPerTicket = $qty > 0 ? round($d['commission'] / $qty, 4) : 0.0;

            $finalPerType[$ttId] = [
                'tt' => $d['tt'],
                'ticket_type_id' => (int) $ttId,
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
            'total_extras' => round($totalExtrasCard, 2),
            'total_discount' => round($totalDiscountCard, 2),
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
    public function buildForPayout(Event $event, ?Carbon $periodStart = null, ?Carbon $periodEnd = null): array
    {
        $breakdown = $this->build($event, $periodStart, $periodEnd);

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
     * Resolve a (commission_mode, commission_amount) pair for the payout-level
     * fields, derived from the same breakdown. `commission_mode` is "dominant":
     * if all rows share one mode, use it; else if any row is added_on_top,
     * treat the whole payout as added_on_top (the customer paid commission on
     * top, so the organizer's net == gross_excl_commission for that row);
     * otherwise fall back to the event's effective mode.
     *
     * @return array{commission_mode: string, commission_amount: float, gross_amount: float, net_amount: float}
     */
    public function summarizeForPayout(Event $event, ?Carbon $periodStart = null, ?Carbon $periodEnd = null): array
    {
        $breakdown = $this->build($event, $periodStart, $periodEnd);

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
