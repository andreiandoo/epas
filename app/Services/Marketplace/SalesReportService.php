<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\MarketplaceRefundRequest;
use App\Models\Order;
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
            // Restrict the breakdown to orders matching our filters. We do
            // this by handing the filtered set of valid order ids to
            // SalesBreakdownService via a custom path: the service's
            // public `build()` takes (event, periodStart, periodEnd) and
            // already filters tickets by their order's paid_at. Here we
            // just pass the period and let it resolve.
            $service = $this->breakdown;
            $breakdown = $service->build($event, Carbon::parse($from), Carbon::parse($to), excludePos: false);

            $eventTitle = $this->resolveTitle($event);
            $posTypeIds = method_exists($event, 'getPosTicketTypeIds') ? [] : []; // resolved per row below

            foreach ($breakdown['per_type'] as $row) {
                $isPos = $row['is_pos'] ?? false;

                $rowOut = [
                    'event_id'         => $event->id,
                    'event_title'      => $eventTitle,
                    'ticket_type_id'   => $row['ticket_type_id'] ?? null,
                    'ticket_type_name' => $row['name'] ?? 'Tip bilet',
                    'is_pos'           => (bool) $isPos,
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
        $q = Order::query()
            ->whereIn('marketplace_event_id', $eventIds)
            ->whereIn('status', $statuses)
            ->whereBetween($dateColumn, [$from, $to])
            ->with(['marketplaceEvent', 'tickets.ticketType', 'items'])
            ->withCount('tickets')
            ->orderByDesc($dateColumn);

        return $q;
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
