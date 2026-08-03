<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Order;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesBreakdown extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $navigationLabel = 'Breakdown vânzări';
    protected static ?string $title = 'Breakdown vânzări & bilete';
    protected static ?int $navigationSort = 99;
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'filament.marketplace.pages.sales-breakdown';

    public ?MarketplaceClient $marketplace = null;

    // NOT #[Url]: same reason as BillingBreakdown — the Livewire property
    // hydration was clobbering the value we want to read from the URL.
    // Month navigation is URL-based via plain <a href> links.
    public string $month = '';

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;

        $requested = request()->query('month');
        if (is_string($requested) && preg_match('/^\d{4}-\d{2}$/', $requested)) {
            $this->month = $requested;
        }
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $this->month)) {
            $this->month = Carbon::now('Europe/Bucharest')->format('Y-m');
        }
    }

    public function getTitle(): string
    {
        return 'Breakdown vânzări & bilete';
    }

    public function getViewData(): array
    {
        $marketplace = $this->marketplace;
        if (! $marketplace) {
            return ['marketplace' => null, 'data' => []];
        }

        $marketplaceId = (int) $marketplace->id;
        $tz = 'Europe/Bucharest';
        $monthDate = Carbon::createFromFormat('Y-m', $this->month);
        $monthStart = $monthDate->copy()->startOfMonth()->shiftTimezone($tz)->utc();
        $monthEnd = $monthDate->copy()->endOfMonth()->endOfDay()->shiftTimezone($tz)->utc();
        $currency = $marketplace->currency ?? 'RON';

        // POS list & exclusions — same as BillingBreakdown so the two pages agree
        // on which orders count as online vs POS.
        $posSources = ['pos_app', 'venue_owner_pos', 'pos'];
        $paidStatuses = ['paid', 'confirmed', 'completed'];
        $excludedSources = ['test_order', 'external_import', 'legacy_import', 'pos_test'];

        // Marketplace scope: 3-key OR pattern (marketplace_client_id OR
        // marketplace_event_id OR event_id) — imported / migrated orders may
        // lack marketplace_client_id but still resolve via the event id.
        $mpEventIds = Event::where('marketplace_client_id', $marketplaceId)->pluck('id')->toArray();

        $scopeOrders = fn ($q) => $q->where(function ($qq) use ($marketplaceId, $mpEventIds) {
                $qq->where('marketplace_client_id', $marketplaceId);
                if (! empty($mpEventIds)) {
                    $qq->orWhereIn('marketplace_event_id', $mpEventIds)
                       ->orWhereIn('event_id', $mpEventIds);
                }
            })
            ->whereNotIn('source', $excludedSources);

        // === BLOCK A — ONLINE SALES (per event) ===
        $onlineByEvent = $scopeOrders(Order::query())
            ->whereIn('status', $paidStatuses)
            ->whereNotIn('source', $posSources)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('COALESCE(marketplace_event_id, event_id) as resolved_event_id')
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->groupBy('resolved_event_id')
            ->orderByDesc('revenue')
            ->get();

        $onlineOrderIds = $scopeOrders(Order::query())
            ->whereIn('status', $paidStatuses)
            ->whereNotIn('source', $posSources)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->pluck('id')
            ->toArray();

        $onlineTicketCounts = [];
        if (! empty($onlineOrderIds)) {
            $onlineTicketCounts = DB::table('tickets as t')
                ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->whereIn('t.order_id', $onlineOrderIds)
                ->whereIn('t.status', ['valid', 'used'])
                ->where(function ($q) {
                    $q->whereNull('t.refund_status')
                      ->orWhere('t.refund_status', '<>', 'refunded');
                })
                ->selectRaw('tt.event_id, COUNT(t.id) as cnt')
                ->groupBy('tt.event_id')
                ->pluck('cnt', 'event_id')
                ->toArray();
        }

        // === BLOCK B — ONLINE REFUNDS (per event) ===
        // Anchored on marketplace_refund_items.updated_at so a refund shows up
        // in the month it was actually processed (not the month of the sale).
        $refundByEvent = DB::table('marketplace_refund_items as ri')
            ->join('tickets as t', 't.id', '=', 'ri.ticket_id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where(function ($q) use ($marketplaceId, $mpEventIds) {
                $q->where('o.marketplace_client_id', $marketplaceId);
                if (! empty($mpEventIds)) {
                    $q->orWhereIn('o.marketplace_event_id', $mpEventIds)
                      ->orWhereIn('o.event_id', $mpEventIds);
                }
            })
            ->where('ri.status', 'refunded')
            ->whereNotIn('o.source', $excludedSources)
            ->whereNotIn('o.source', $posSources)
            ->whereBetween('ri.updated_at', [$monthStart, $monthEnd])
            ->selectRaw('COALESCE(tt.event_id, o.marketplace_event_id, o.event_id) as resolved_event_id')
            ->selectRaw('SUM(ri.refund_amount) as amount')
            ->selectRaw('COUNT(DISTINCT o.id) as order_count')
            ->selectRaw('COUNT(ri.id) as ticket_count')
            ->groupBy('resolved_event_id')
            ->orderByDesc('amount')
            ->get();

        // === BLOCK C — POS SALES (per event, split by payment_method) ===
        $posOrders = $scopeOrders(Order::query())
            ->whereIn('status', $paidStatuses)
            ->whereIn('source', $posSources)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get(['id', 'source', 'total', 'meta', 'marketplace_event_id', 'event_id']);

        $posByEvent = [];
        foreach ($posOrders as $o) {
            $eid = (int) ($o->marketplace_event_id ?? $o->event_id ?? 0);
            $meta = $o->meta;
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            } elseif (! is_array($meta)) {
                $meta = [];
            }
            $pm = $meta['payment_method'] ?? null;
            if ($pm === 'cash') {
                $bucket = 'cash';
            } elseif (in_array($pm, ['card', 'tap'], true)) {
                $bucket = 'card';
            } else {
                $bucket = 'unknown';
            }

            if (! isset($posByEvent[$eid])) {
                $posByEvent[$eid] = [
                    'event_id' => $eid,
                    'total' => 0.0,
                    'cash' => 0.0,
                    'card' => 0.0,
                    'unknown' => 0.0,
                    'order_count' => 0,
                    'ticket_count' => 0,
                    'by_source' => ['pos_app' => 0.0, 'venue_owner_pos' => 0.0, 'pos' => 0.0],
                ];
            }
            $posByEvent[$eid]['total'] += (float) $o->total;
            $posByEvent[$eid][$bucket] += (float) $o->total;
            $posByEvent[$eid]['order_count']++;
            if (isset($posByEvent[$eid]['by_source'][$o->source])) {
                $posByEvent[$eid]['by_source'][$o->source] += (float) $o->total;
            }
        }

        $posTicketCounts = [];
        $posOrderIds = $posOrders->pluck('id')->toArray();
        if (! empty($posOrderIds)) {
            $posTicketCounts = DB::table('tickets as t')
                ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->whereIn('t.order_id', $posOrderIds)
                ->whereIn('t.status', ['valid', 'used'])
                ->where(function ($q) {
                    $q->whereNull('t.refund_status')
                      ->orWhere('t.refund_status', '<>', 'refunded');
                })
                ->selectRaw('tt.event_id, COUNT(t.id) as cnt')
                ->groupBy('tt.event_id')
                ->pluck('cnt', 'event_id')
                ->toArray();

            foreach ($posByEvent as $eid => &$row) {
                $row['ticket_count'] = (int) ($posTicketCounts[$eid] ?? 0);
            }
            unset($row);
        }

        // Sort POS events by total desc
        uasort($posByEvent, fn ($a, $b) => $b['total'] <=> $a['total']);

        // === Event details (name / date / venue) for every event that appears
        // in any of the three blocks above ===
        $allEventIds = array_values(array_unique(array_filter(array_merge(
            $onlineByEvent->pluck('resolved_event_id')->map(fn ($v) => (int) $v)->toArray(),
            $refundByEvent->pluck('resolved_event_id')->map(fn ($v) => (int) $v)->toArray(),
            array_map('intval', array_keys($posByEvent)),
        ))));

        $eventDetails = [];
        if (! empty($allEventIds)) {
            $eventDetails = Event::with('venue')->whereIn('id', $allEventIds)->get()
                ->mapWithKeys(function ($e) {
                    $name = $e->getTranslation('title', 'ro') ?: $e->getTranslation('title', 'en');
                    $venueName = null;
                    if ($e->venue) {
                        $vn = $e->venue->name;
                        $venueName = is_array($vn) ? ($vn['ro'] ?? $vn['en'] ?? null) : $vn;
                    }
                    $eventDate = $e->event_date ?? $e->range_start_date;

                    return [$e->id => [
                        'name' => $name ?: ('Eveniment #' . $e->id),
                        'date' => $eventDate?->format('d.m.Y'),
                        'venue' => $venueName,
                    ]];
                })
                ->toArray();
        }

        $labelFor = function ($eid) use ($eventDetails) {
            $eid = (int) $eid;
            if ($eid === 0) {
                return ['name' => 'Multi-eveniment / necunoscut', 'date' => null, 'venue' => null];
            }

            return $eventDetails[$eid] ?? ['name' => 'Eveniment #' . $eid, 'date' => null, 'venue' => null];
        };

        // === Assemble the online rows ===
        $onlineRows = [];
        $onlineTotal = 0.0;
        $onlineOrderCount = 0;
        $onlineTicketTotal = 0;
        foreach ($onlineByEvent as $row) {
            $eid = (int) $row->resolved_event_id;
            $rev = (float) $row->revenue;
            $oc = (int) $row->order_count;
            $tc = (int) ($onlineTicketCounts[$eid] ?? 0);
            $onlineTotal += $rev;
            $onlineOrderCount += $oc;
            $onlineTicketTotal += $tc;
            $meta = $labelFor($eid);
            $onlineRows[] = [
                'event_id' => $eid,
                'event_name' => $meta['name'],
                'event_date' => $meta['date'],
                'venue' => $meta['venue'],
                'revenue' => round($rev, 2),
                'order_count' => $oc,
                'ticket_count' => $tc,
            ];
        }

        // === Assemble the refund rows ===
        $refundRows = [];
        $refundTotal = 0.0;
        $refundOrderCount = 0;
        $refundTicketCount = 0;
        foreach ($refundByEvent as $row) {
            $eid = (int) $row->resolved_event_id;
            $amt = (float) $row->amount;
            $oc = (int) $row->order_count;
            $tc = (int) $row->ticket_count;
            $refundTotal += $amt;
            $refundOrderCount += $oc;
            $refundTicketCount += $tc;
            $meta = $labelFor($eid);
            $refundRows[] = [
                'event_id' => $eid,
                'event_name' => $meta['name'],
                'event_date' => $meta['date'],
                'venue' => $meta['venue'],
                'amount' => round($amt, 2),
                'order_count' => $oc,
                'ticket_count' => $tc,
            ];
        }

        // === Assemble the POS rows ===
        $posRows = [];
        $posTotal = 0.0;
        $posCash = 0.0;
        $posCard = 0.0;
        $posUnknown = 0.0;
        $posOrderCount = 0;
        $posTicketTotal = 0;
        foreach ($posByEvent as $eid => $row) {
            $meta = $labelFor($eid);
            $posTotal += $row['total'];
            $posCash += $row['cash'];
            $posCard += $row['card'];
            $posUnknown += $row['unknown'];
            $posOrderCount += $row['order_count'];
            $posTicketTotal += $row['ticket_count'];
            $posRows[] = [
                'event_id' => $eid,
                'event_name' => $meta['name'],
                'event_date' => $meta['date'],
                'venue' => $meta['venue'],
                'total' => round($row['total'], 2),
                'cash' => round($row['cash'], 2),
                'card' => round($row['card'], 2),
                'unknown' => round($row['unknown'], 2),
                'order_count' => $row['order_count'],
                'ticket_count' => $row['ticket_count'],
                'by_source' => [
                    'pos_app' => round($row['by_source']['pos_app'], 2),
                    'venue_owner_pos' => round($row['by_source']['venue_owner_pos'], 2),
                    'pos' => round($row['by_source']['pos'], 2),
                ],
            ];
        }

        // Total sales across every channel (online + POS). Refunds are shown
        // separately in Block B and not deducted here — "vânzări" = gross
        // revenue, "returnat" is its own line.
        $salesTotal = round($onlineTotal + $posTotal, 2);

        return [
            'marketplace' => $marketplace,
            'data' => [
                'month' => $this->month,
                'month_label' => $monthDate->translatedFormat('F Y'),
                'prev_url' => url()->current() . '?month=' . $monthDate->copy()->subMonth()->format('Y-m'),
                'next_url' => $monthDate->format('Y-m') === Carbon::now($tz)->format('Y-m')
                    ? null
                    : url()->current() . '?month=' . $monthDate->copy()->addMonth()->format('Y-m'),
                'is_current_month' => $monthDate->format('Y-m') === Carbon::now($tz)->format('Y-m'),
                'currency' => $currency,

                // Block A — online
                'online_total' => round($onlineTotal, 2),
                'online_order_count' => $onlineOrderCount,
                'online_ticket_count' => $onlineTicketTotal,
                'online_rows' => $onlineRows,

                // Block B — online refunds
                'refund_total' => round($refundTotal, 2),
                'refund_order_count' => $refundOrderCount,
                'refund_ticket_count' => $refundTicketCount,
                'refund_rows' => $refundRows,

                // Combined sales (online + POS)
                'sales_total' => $salesTotal,

                // Block D — POS
                'pos_total' => round($posTotal, 2),
                'pos_cash' => round($posCash, 2),
                'pos_card' => round($posCard, 2),
                'pos_unknown' => round($posUnknown, 2),
                'pos_order_count' => $posOrderCount,
                'pos_ticket_count' => $posTicketTotal,
                'pos_rows' => $posRows,
            ],
        ];
    }
}
