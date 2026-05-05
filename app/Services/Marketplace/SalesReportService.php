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
            $breakdown = $this->breakdown->build($event, Carbon::parse($from), Carbon::parse($to), excludePos: false);

            $eventTitle = $this->resolveTitle($event);
            // POS = ticket types whose only sales for this event are
            // pos_app orders. SalesBreakdownService doesn't tag rows
            // with this; we compute it once per event and look up.
            $posTypeIds = $this->resolvePosTicketTypeIds($event);

            foreach ($breakdown['per_type'] as $row) {
                $ttId = $row['ticket_type_id'] ?? null;
                $isPos = $ttId !== null && in_array((int) $ttId, $posTypeIds, true);

                $rowOut = [
                    'event_id'         => $event->id,
                    'event_title'      => $eventTitle,
                    'ticket_type_id'   => $ttId,
                    'ticket_type_name' => (string) ($row['ticket_type_name'] ?? 'Tip bilet'),
                    'is_pos'           => $isPos,
                    'qty'              => (int) ($row['qty'] ?? 0),
                    'price'            => (float) ($row['price'] ?? 0),
                    'gross'            => (float) ($row['gross'] ?? 0),
                    'commission'       => (float) ($row['commission_amount'] ?? 0),
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
            ->with(['marketplaceEvent', 'tickets.ticketType', 'items'])
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
        $eventTitle = $order->marketplaceEvent
            ? $this->resolveTitle($order->marketplaceEvent)
            : '—';

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

        $commission = (float) ($order->commission_amount ?? 0);
        $gross = (float) ($order->total ?? 0);
        $net = $gross - $commission - $refundTotal;

        return [
            'order_id'       => $order->id,
            'order_number'   => $order->order_number,
            'event_id'       => $order->marketplace_event_id,
            'event_title'    => $eventTitle,
            'paid_at'        => $order->paid_at,
            'created_at'     => $order->created_at,
            'customer_name'  => $order->customer_name,
            'customer_email' => $order->customer_email,
            'tickets'        => (int) ($order->tickets_count ?? 0),
            'gross'          => $gross,
            'commission'     => $commission,
            'refund'         => $refundTotal,
            'net'            => $net,
            'status'         => $order->status,
            'payment_status' => $order->payment_status,
            'currency'       => $order->currency ?? 'RON',
            'source'         => $order->source ?? null,
        ];
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
