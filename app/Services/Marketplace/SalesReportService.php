<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\MarketplaceRefundRequest;
use App\Models\Order;
use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cross-event sales report orchestrator. Compact mode aggregates per
 * ticket type via SalesBreakdownService — same math as the payout
 * snapshot and the event-edit "Vânzări" tab. Extended mode lists raw
 * orders with per-order totals and refund offsets.
 *
 * Filters: event ids OR organizer id (mutually exclusive at the page
 * level), period (paid_at / created_at), status whitelist.
 */
class SalesReportService
{
    public function __construct(private readonly SalesBreakdownService $breakdown) {}

    /**
     * Compact view: one row per (event, ticket_type) plus a summary line.
     *
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float|int>}
     */
    public function compact(array $eventIds, CarbonInterface $from, CarbonInterface $to, array $statuses, string $dateColumn = 'paid_at'): array
    {
        $events = Event::query()
            ->whereIn('id', $eventIds)
            ->orderByDesc('event_date')
            ->get();

        $rows = [];
        $totals = ['qty' => 0, 'gross' => 0.0, 'commission' => 0.0, 'discount' => 0.0, 'extras' => 0.0, 'net' => 0.0];

        // POS rows are surfaced separately so they appear in the table but
        // never count toward the totals — same convention as the payout
        // snapshot. Build/summarize work per-event; filter/aggregate the
        // resulting per_type rows ourselves so we can apply the period +
        // status + date-column constraints upstream.
        foreach ($events as $event) {
            // Pass dateColumn so the per-type breakdown uses the same
            // bounds as extendedQuery — without it, compact filtered by
            // orders.created_at (the SalesBreakdownService default for
            // payout snapshots) and extended filtered by paid_at, leaving
            // the two views with different qty/gross numbers for the same
            // user-selected period.
            $breakdown = $this->breakdown->build(
                $event,
                Carbon::parse($from),
                Carbon::parse($to),
                excludePos: false,
                dateColumn: $dateColumn
            );

            $eventTitle = $this->resolveTitle($event);
            // POS = ticket types whose only sales for this event are
            // pos_app orders. SalesBreakdownService doesn't tag rows
            // with this; we compute it once per event and look up.
            $posTypeIds = $this->resolvePosTicketTypeIds($event);

            foreach ($breakdown['per_type'] as $row) {
                $ttId = $row['ticket_type_id'] ?? null;
                $isPos = $ttId !== null && in_array((int) $ttId, $posTypeIds, true);

                $commission = (float) ($row['commission_amount'] ?? 0);
                $isOnTop = in_array($row['commission_mode'] ?? null, ['on_top', 'added_on_top'], true);
                // Match the decont's "Total brut" definition: for on_top
                // rows the customer pays price*qty + commission, and that
                // is what should appear under "Brut" — same as the
                // payout-ticket-breakdown blade. Net stays as the
                // organizer-side number (gross − commission − discount −
                // extras), so 1545 brut − 75 comm = 1470 net for an
                // on-top earlybird and the row arithmetic still ties out.
                $baseGross = (float) ($row['gross'] ?? 0);
                $displayGross = $baseGross + ($isOnTop ? $commission : 0);

                $rowOut = [
                    'event_id'         => $event->id,
                    'event_title'      => $eventTitle,
                    'ticket_type_id'   => $ttId,
                    'ticket_type_name' => (string) ($row['ticket_type_name'] ?? 'Tip bilet'),
                    'is_pos'           => $isPos,
                    'qty'              => (int) ($row['qty'] ?? 0),
                    'price'            => (float) ($row['price'] ?? 0),
                    'gross'            => $displayGross,
                    'commission'       => $commission,
                    'commission_mode'  => $row['commission_mode'] ?? null,
                    'commission_type'  => $row['commission_type'] ?? null,
                    'commission_rate'  => $row['commission_rate'] ?? null,
                    'commission_fixed' => $row['commission_fixed'] ?? null,
                    'discount'         => (float) ($row['discount'] ?? 0),
                    'extras'           => (float) ($row['extras'] ?? 0),
                    'net'              => (float) ($row['net'] ?? 0),
                ];

                $rows[] = $rowOut;

                if (!$isPos) {
                    $totals['qty']        += $rowOut['qty'];
                    $totals['gross']      += $rowOut['gross'];
                    $totals['commission'] += $rowOut['commission'];
                    $totals['discount']   += $rowOut['discount'];
                    $totals['extras']     += $rowOut['extras'];
                    $totals['net']        += $rowOut['net'];
                }
            }
        }

        $totals = array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $totals);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Extended view: raw orders matching the filters. Returns a paginator-friendly
     * structure (caller paginates). Each row carries computed gross / commission /
     * net mirrored from the per-event breakdown for visual consistency with the
     * compact mode.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function extendedQuery(array $eventIds, CarbonInterface $from, CarbonInterface $to, array $statuses, string $dateColumn = 'paid_at')
    {
        // Orders may be linked through either column depending on how they
        // were created (legacy event_id vs newer marketplace_event_id). The
        // first cut filtered only on marketplace_event_id and missed half
        // the data — same OR pattern as SalesBreakdownService::build().
        $q = Order::query()
            ->where(fn ($q) => $q->whereIn('marketplace_event_id', $eventIds)
                                  ->orWhereIn('event_id', $eventIds))
            ->whereIn('status', $statuses)
            ->whereBetween($dateColumn, [$from, $to])
            // event() points at App\Models\Event (the real source of truth);
            // marketplaceEvent is a separate model on a different table that
            // is mostly unused — using it left the title column blank.
            ->with(['event', 'tickets.ticketType', 'items'])
            ->withCount('tickets')
            ->orderByDesc($dateColumn);

        return $q;
    }

    /**
     * Detect ticket types that sell exclusively via pos_app for a given
     * event. Same shape as MarketplacePayout::getPosTicketTypeIds() but
     * driven from the event itself rather than a payout snapshot.
     *
     * @return array<int, int>
     */
    protected function resolvePosTicketTypeIds(Event $event): array
    {
        // Pull the ticket type ids that have any sold ticket for this event,
        // then keep only those with no non-pos_app order behind them.
        $typeIds = Ticket::query()
            ->where(fn ($q) => $q->where('event_id', $event->id)
                                  ->orWhere('marketplace_event_id', $event->id))
            ->whereIn('status', ['valid', 'used'])
            ->pluck('ticket_type_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($typeIds)) return [];

        $posOnly = [];
        foreach ($typeIds as $typeId) {
            $hasNonPos = Ticket::query()
                ->where('ticket_type_id', $typeId)
                ->whereHas('order', function ($q) use ($event) {
                    $q->where(fn ($q2) => $q2->where('event_id', $event->id)
                                              ->orWhere('marketplace_event_id', $event->id))
                      ->whereIn('status', ['paid', 'confirmed', 'completed'])
                      ->where('source', '!=', 'pos_app');
                })
                ->exists();

            if (!$hasNonPos) {
                $posOnly[] = (int) $typeId;
            }
        }
        return $posOnly;
    }

    /**
     * Decorate an order row for the extended view. Refund total is fetched
     * once per order so it appears in the table and the CSV export.
     *
     * @return array<string, mixed>
     */
    public function extendedRow(Order $order): array
    {
        // Order has both ->event (App\Models\Event, the real source of
        // truth used by SalesBreakdownService) and ->marketplaceEvent (a
        // mostly-empty mirror table). Using the wrong one left the
        // Eveniment column blank.
        $event = $order->event ?? null;
        $eventTitle = $event ? $this->resolveTitle($event) : '—';

        // Sum approved_amount on refund requests that actually paid out
        // (refunded or partially_refunded). Pending / approved-but-not-yet-
        // processed refunds don't count toward the customer-facing net.
        $refundTotal = (float) MarketplaceRefundRequest::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [
                MarketplaceRefundRequest::STATUS_REFUNDED,
                MarketplaceRefundRequest::STATUS_PARTIALLY_REFUNDED,
            ])
            ->sum('approved_amount');

        // Order.commission_amount is unreliable across sources (POS app
        // sometimes writes per-ticket numbers; legacy orders may leave it
        // blank). Re-compute from the actual tickets via
        // TicketType::calculateCommission — same primitive
        // SalesBreakdownService uses, so this matches the compact view
        // exactly. Walks each valid/used ticket once and sums.
        $defaultRate = $event
            ? (float) ($event->commission_rate
                ?? $event->marketplaceOrganizer?->commission_rate
                ?? $event->marketplaceClient?->commission_rate
                ?? 5)
            : 5.0;
        $defaultMode = $event
            ? ($event->commission_mode
                ?? $event->marketplaceOrganizer?->default_commission_mode
                ?? $event->marketplaceClient?->commission_mode
                ?? 'included')
            : 'included';

        $commission = 0.0;
        $modes = [];
        $rateTags = [];
        $typesByCount = [];
        foreach ($order->tickets as $ticket) {
            if (!in_array($ticket->status, ['valid', 'used'], true)) continue;
            $tt = $ticket->ticketType;
            if (!$tt) continue;
            $price = (float) ($ticket->price ?? 0);
            if ($price <= 0) {
                $price = ((int) ($tt->sale_price_cents ?? 0) > 0
                    ? $tt->sale_price_cents
                    : (int) ($tt->price_cents ?? 0)) / 100;
            }
            $commission += (float) $tt->calculateCommission($price, $defaultRate, $defaultMode);
            $eff = $tt->getEffectiveCommission($defaultRate, $defaultMode);
            $modes[] = $eff['mode'] ?? null;
            $tag = $this->shortCommissionTag($eff);
            if ($tag !== '') $rateTags[] = $tag;

            $name = $tt->name ?? 'Tip bilet';
            $typesByCount[$name] = ($typesByCount[$name] ?? 0) + 1;
        }
        $commission = round($commission, 2);

        $ticketTypeLabel = collect($typesByCount)
            ->map(fn ($qty, $name) => "{$name} x{$qty}")
            ->values()
            ->implode(', ');

        $gross = (float) ($order->total ?? 0);
        $discount = (float) ($order->discount_amount ?? $order->promo_discount ?? 0);
        $promoCode = is_array($order->meta ?? null)
            ? ($order->meta['promo_code']['code'] ?? null)
            : null;

        // Net = what the organizer actually keeps. Commission always comes
        // out (for added_on_top the customer paid it on top, so it's in
        // order.total; for included it's carved from order.total). Discount
        // and refund are always hard subtracts.
        $modesUnique = array_values(array_unique(array_filter($modes)));
        $net = $gross - $commission - $discount - $refundTotal;
        if ($net < 0) $net = 0.0;

        return [
            'order_id'         => $order->id,
            'order_number'     => $order->order_number,
            'event_id'         => $order->event_id ?? $order->marketplace_event_id,
            'event_title'      => $eventTitle,
            'paid_at'          => $order->paid_at,
            'created_at'       => $order->created_at,
            'customer_name'    => $order->customer_name,
            'customer_email'   => $order->customer_email,
            'tickets'          => (int) ($order->tickets_count ?? 0),
            'ticket_types'     => $ticketTypeLabel,
            'gross'            => $gross,
            'commission'       => $commission,
            'commission_mode'  => $this->summariseModes($modesUnique, array_values(array_unique($rateTags))),
            'discount'         => round($discount, 2),
            'promo_code'       => $promoCode,
            'refund'           => $refundTotal,
            'net'              => round($net, 2),
            'status'           => $order->status,
            'payment_status'   => $order->payment_status,
            'currency'         => $order->currency ?? 'RON',
            'source'           => $order->source ?? null,
        ];
    }

    protected function shortCommissionTag(array $eff): string
    {
        $type = $eff['type'] ?? null;
        $rate = $eff['rate'] ?? null;
        $fixed = $eff['fixed'] ?? null;
        return match (true) {
            $type === 'percentage' && $rate !== null => $rate . '%',
            $type === 'fixed' && $fixed !== null => number_format((float) $fixed, 2) . ' RON',
            $type === 'both' => trim(($rate !== null ? $rate . '%' : '') . ($rate !== null && $fixed !== null ? ' + ' : '') . ($fixed !== null ? number_format((float) $fixed, 2) . ' RON' : '')),
            $rate !== null => $rate . '%',
            default => '',
        };
    }

    protected function summariseModes(array $modes, array $rateTags): string
    {
        if (empty($modes)) return '';

        $modeLabel = (count($modes) === 1)
            ? match ($modes[0]) {
                'added_on_top', 'on_top' => 'Peste preț',
                'included' => 'Inclus',
                default => (string) $modes[0],
            }
            : 'Mixt';

        if (empty($rateTags)) return $modeLabel;
        return $modeLabel . ' (' . implode(', ', $rateTags) . ')';
    }

    /**
     * Resolve event title across translatable arrays and plain strings.
     */
    private function resolveTitle(Event $event): string
    {
        $title = $event->title ?? null;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? (reset($title) ?: 'Eveniment');
        }
        return (string) ($title ?? 'Eveniment');
    }
}
