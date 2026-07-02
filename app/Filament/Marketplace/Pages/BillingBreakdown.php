<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Models\ServiceOrder;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class BillingBreakdown extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?string $navigationLabel = 'Billing Breakdown';
    protected static ?string $title = 'Detalii facturare Tixello';
    protected static ?int $navigationSort = 99;
    protected static bool $shouldRegisterNavigation = false; // Hidden from nav, accessible via link
    protected string $view = 'filament.marketplace.pages.billing-breakdown';

    public ?MarketplaceClient $marketplace = null;

    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;

        if (empty($this->month)) {
            $this->month = Carbon::now()->format('Y-m');
        }
    }

    public function getTitle(): string
    {
        return 'Detalii facturare Tixello';
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $next = Carbon::createFromFormat('Y-m', $this->month)->addMonth();
        if ($next->lte(Carbon::now()->endOfMonth())) {
            $this->month = $next->format('Y-m');
        }
    }

    /**
     * Calculate marketplace commission total for a given period.
     * Pass null for $monthStart/$monthEnd to calculate all-time.
     * Reusable by Dashboard and other pages.
     */
    public static function calculateMarketplaceCommission(int $marketplaceId, $monthStart = null, $monthEnd = null, float $defaultRate = 5): float
    {
        $mpEventIds = Event::where('marketplace_client_id', $marketplaceId)->pluck('id')->toArray();
        $validStatuses = ['paid', 'confirmed', 'completed', 'refunded'];

        $query = Order::where(function ($q) use ($marketplaceId, $mpEventIds) {
                $q->where('marketplace_client_id', $marketplaceId);
                if (!empty($mpEventIds)) {
                    $q->orWhereIn('marketplace_event_id', $mpEventIds)
                      ->orWhereIn('event_id', $mpEventIds);
                }
            })
            ->whereIn('status', $validStatuses)
            ->whereNotIn('source', ['test_order', 'external_import']);

        if ($monthStart && $monthEnd) {
            $query->whereBetween('created_at', [$monthStart, $monthEnd]);
        }

        $eventBreakdown = $query
            ->selectRaw('COALESCE(marketplace_event_id, event_id) as resolved_event_id')
            ->selectRaw('SUM(total) as revenue_all')
            ->selectRaw('SUM(CASE WHEN commission_amount > 0 THEN commission_amount ELSE total * COALESCE(commission_rate, 0) / 100 END) as marketplace_commission')
            ->groupBy('resolved_event_id')
            ->get();

        $eventIds = $eventBreakdown->pluck('resolved_event_id')->filter()->toArray();
        $eventRates = [];
        if (!empty($eventIds)) {
            $eventRates = Event::with('marketplaceOrganizer')->whereIn('id', $eventIds)
                ->get()
                ->mapWithKeys(fn ($e) => [
                    $e->id => (float) ($e->commission_rate ?? $e->marketplaceOrganizer?->commission_rate ?? $defaultRate)
                ])
                ->toArray();
        }

        $total = 0;
        foreach ($eventBreakdown as $row) {
            $eventId = $row->resolved_event_id;
            $comm = (float) ($row->marketplace_commission ?? 0);
            $revenueAll = (float) ($row->revenue_all ?? 0);

            if ($comm <= 0 && $revenueAll > 0) {
                $rate = $eventRates[$eventId] ?? $defaultRate;
                $comm = round($revenueAll * ($rate / 100), 2);
            }
            $total += $comm;
        }

        return round($total, 2);
    }

    public function getViewData(): array
    {
        $marketplace = $this->marketplace;
        if (!$marketplace) {
            return ['marketplace' => null, 'data' => []];
        }

        $marketplaceId = $marketplace->id;
        $tz = 'Europe/Bucharest';
        $monthDate = Carbon::createFromFormat('Y-m', $this->month);
        $monthStart = $monthDate->copy()->startOfMonth()->shiftTimezone($tz)->utc();
        $monthEnd = $monthDate->copy()->endOfMonth()->endOfDay()->shiftTimezone($tz)->utc();

        // If billing_starts_at falls in this month, use it as start
        $billingStartsAt = $marketplace->billing_starts_at ?? null;
        if ($billingStartsAt) {
            $billingStart = Carbon::parse($billingStartsAt, $tz)->startOfDay()->utc();
            if ($billingStart->between($monthStart, $monthEnd)) {
                $monthStart = $billingStart;
            }
        }
        $currency = $marketplace->currency ?? 'RON';
        $validStatuses = ['paid', 'confirmed', 'completed', 'refunded'];
        $commissionRate = (float) ($marketplace->commission_rate ?? 0);

        // === TICKETING BREAKDOWN PER EVENT ===
        // Include orders for marketplace events (migrated may lack marketplace_client_id)
        $mpEventIds = Event::where('marketplace_client_id', $marketplaceId)->pluck('id')->toArray();

        // Excluded sources everywhere: test orders, external imports (Ambilet
        // migrations from Wordpress), and legacy_import (older Wordpress data
        // pre-migration). Mirrors Dashboard::computeMonthlyBilling so the two
        // pages agree on which orders count toward monthly commission.
        $excludedSources = ['test_order', 'external_import', 'legacy_import'];

        $eventBreakdown = Order::where(function ($q) use ($marketplaceId, $mpEventIds) {
                $q->where('marketplace_client_id', $marketplaceId);
                if (!empty($mpEventIds)) {
                    $q->orWhereIn('marketplace_event_id', $mpEventIds)
                      ->orWhereIn('event_id', $mpEventIds);
                }
            })
            ->whereIn('status', $validStatuses)
            ->whereNotIn('source', $excludedSources)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('COALESCE(marketplace_event_id, event_id) as resolved_event_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw("SUM(CASE WHEN status = 'refunded' THEN 0 ELSE total END) as revenue")
            ->selectRaw('SUM(total) as revenue_with_refunds')
            ->selectRaw('SUM(CASE WHEN commission_amount > 0 THEN commission_amount ELSE total * COALESCE(commission_rate, 0) / 100 END) as marketplace_commission')
            ->groupBy('resolved_event_id')
            ->orderByDesc('revenue')
            ->get();

        // Get event details (name, date, venue)
        $eventIds = $eventBreakdown->pluck('resolved_event_id')->filter()->toArray();
        $eventDetails = [];
        if (!empty($eventIds)) {
            $eventDetails = Event::with(['venue', 'marketplaceOrganizer'])->whereIn('id', $eventIds)
                ->get()
                ->mapWithKeys(function ($e) use ($marketplace) {
                    $name = $e->getTranslation('title', 'ro') ?: $e->getTranslation('title', 'en');
                    $venueName = $e->venue ? (is_array($e->venue->name) ? ($e->venue->name['ro'] ?? $e->venue->name['en'] ?? '') : $e->venue->name) : null;
                    $venueCity = $e->venue?->city;
                    $eventDate = $e->event_date ?? $e->range_start_date;
                    // Commission rate fallback: event → organizer → marketplace default
                    $eventCommRate = $e->commission_rate
                        ?? $e->marketplaceOrganizer?->commission_rate
                        ?? null;
                    // Commission mode fallback: event → organizer → marketplace
                    // → 'included'. Mirrors Ticket::getCommissionPerUnit so
                    // the transparent breakdown here matches what was billed
                    // on each individual ticket sale.
                    $mode = $e->commission_mode
                        ?? $e->marketplaceOrganizer?->default_commission_mode
                        ?? $marketplace->commission_mode
                        ?? 'included';
                    return [$e->id => [
                        'name' => $name,
                        'date' => $eventDate?->format('d.m.Y'),
                        'venue' => $venueName,
                        'city' => $venueCity,
                        'commission_rate' => $eventCommRate,
                        'commission_mode' => $mode,
                    ]];
                })
                ->toArray();
        }

        // Get ticket counts per event
        $ticketCounts = [];
        if (!empty($eventIds)) {
            // Join through ticket_types to get event_id (same as TicketResource logic)
            $ticketCounts = DB::table('tickets')
                ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                ->whereIn('ticket_types.event_id', $eventIds)
                ->whereIn('tickets.status', ['valid', 'used'])
                ->whereBetween('tickets.created_at', [$monthStart, $monthEnd])
                ->selectRaw('ticket_types.event_id as resolved_event_id, COUNT(tickets.id) as cnt')
                ->groupBy('ticket_types.event_id')
                ->pluck('cnt', 'resolved_event_id')
                ->toArray();
        }

        $events = $eventBreakdown->map(function ($row) use ($eventDetails, $ticketCounts, $commissionRate, $marketplace) {
            $eventId = $row->resolved_event_id;
            $revenue = (float) $row->revenue; // excludes refunded
            $revenueAll = (float) $row->revenue_with_refunds; // order.total incl refunded (informational only)
            $marketplaceCommission = (float) ($row->marketplace_commission ?? 0);
            $details = $eventDetails[$eventId] ?? null;

            // If SQL couldn't calculate commission (both commission_amount and commission_rate are 0/null),
            // fall back to event's commission_rate or marketplace default
            // Commission is calculated from ALL orders including refunded
            if ($marketplaceCommission <= 0 && $revenueAll > 0) {
                $eventCommRate = $details['commission_rate'] ?? $marketplace->commission_rate ?? 5;
                $marketplaceCommission = round($revenueAll * ((float) $eventCommRate / 100), 2);
            }

            return [
                'event_id' => $eventId,
                'event_name' => $details['name'] ?? ($eventId ? 'Eveniment #' . $eventId : 'Necunoscut'),
                'event_date' => $details['date'] ?? null,
                'venue' => $details['venue'] ?? null,
                'city' => $details['city'] ?? null,
                'commission_mode' => $details['commission_mode'] ?? 'included',
                'order_count' => (int) $row->order_count,
                'ticket_count' => (int) ($ticketCounts[$eventId] ?? 0),
                'revenue' => $revenue,
                'marketplace_commission' => $marketplaceCommission,
                // tixello_commission filled in below (after ticket totals per event are known)
                'tixello_commission' => 0.0,
                'ticket_price_base' => 0.0,
            ];
        })->keyBy('event_id')->toArray();

        $revenueTotal = collect($events)->sum('revenue');
        $marketplaceCommissionTotal = collect($events)->sum('marketplace_commission');

        // === REVENUE SPLIT (order-level): ONLINE vs POS ===
        // Order-level totals (order.total). Used for the "valoarea totală
        // a încasărilor" line on the Vânzări cards. Excludes refunded so
        // the number reflects cash-in.
        $posSources = ['pos_app', 'venue_owner_pos'];
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        $revenueSplit = Order::where(function ($q) use ($marketplaceId, $mpEventIds) {
                $q->where('marketplace_client_id', $marketplaceId);
                if (!empty($mpEventIds)) {
                    $q->orWhereIn('marketplace_event_id', $mpEventIds)
                      ->orWhereIn('event_id', $mpEventIds);
                }
            })
            ->whereIn('status', $paidStatuses)
            ->whereNotIn('source', $excludedSources)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw("SUM(CASE WHEN source IN ('pos_app','venue_owner_pos') THEN total ELSE 0 END) as pos_revenue")
            ->selectRaw("SUM(CASE WHEN source NOT IN ('pos_app','venue_owner_pos') THEN total ELSE 0 END) as online_revenue")
            ->selectRaw("COUNT(CASE WHEN source IN ('pos_app','venue_owner_pos') THEN 1 END) as pos_orders")
            ->selectRaw("COUNT(CASE WHEN source NOT IN ('pos_app','venue_owner_pos') THEN 1 END) as online_orders")
            ->first();

        $onlineRevenue = (float) ($revenueSplit->online_revenue ?? 0);
        $posRevenue = (float) ($revenueSplit->pos_revenue ?? 0);
        $onlineOrders = (int) ($revenueSplit->online_orders ?? 0);
        $posOrders = (int) ($revenueSplit->pos_orders ?? 0);

        // === TICKET-LEVEL BREAKDOWN with commission_mode ===
        // The Tixello 1% is charged on ticket.price under the rule:
        //   - "included" tickets  → price already contains the commission,
        //                           so 1% applies to the full ticket price
        //   - "added_on_top" tickets → price is the nominal ticket value
        //                              (organizer's cut sits on top of it
        //                              in order.total but is NOT part of the
        //                              billable base). 1% applies to ticket.price
        //                              only, never to the on-top portion.
        // We load every ticket sold or refunded in the period with its
        // parent order source + resolved event_id, then bucket in PHP
        // (online vs pos × included vs on_top × sold vs refunded).
        $ticketRows = DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where(function ($q) use ($marketplaceId, $mpEventIds) {
                $q->where('o.marketplace_client_id', $marketplaceId);
                if (!empty($mpEventIds)) {
                    $q->orWhereIn('o.marketplace_event_id', $mpEventIds)
                      ->orWhereIn('o.event_id', $mpEventIds);
                }
            })
            ->whereNotIn('o.source', $excludedSources)
            ->whereBetween('t.created_at', [$monthStart, $monthEnd])
            ->select(
                't.id',
                DB::raw('COALESCE(t.price, 0) as price'),
                't.status',
                't.refund_status',
                'o.source as order_source',
                DB::raw('COALESCE(tt.event_id, o.marketplace_event_id, o.event_id) as resolved_event_id')
            )
            ->get();

        // Empty template for each bucket
        $emptyBucket = fn () => [
            'included_count' => 0, 'included_value' => 0.0,
            'on_top_count' => 0,   'on_top_value' => 0.0,
        ];
        $onlineSold     = $emptyBucket();
        $onlinRefunded  = $emptyBucket();
        $posSold        = $emptyBucket();
        $posRefunded    = $emptyBucket();

        $freeSoldOnlineCount = 0;
        $freeSoldPosCount = 0;

        foreach ($ticketRows as $t) {
            $isPos = in_array($t->order_source, $posSources, true);
            $isRefunded = $t->refund_status === 'refunded';
            $isSold = !$isRefunded && in_array($t->status, ['valid', 'used'], true);
            if (!$isSold && !$isRefunded) {
                continue;
            }

            $mode = $eventDetails[$t->resolved_event_id]['commission_mode'] ?? 'included';
            $modeKey = $mode === 'added_on_top' ? 'on_top' : 'included';
            $price = (float) $t->price;

            if ($isSold && $isPos) {
                $posSold["{$modeKey}_count"] += 1;
                $posSold["{$modeKey}_value"] += $price;
                if ($price === 0.0) $freeSoldPosCount++;
            } elseif ($isSold && !$isPos) {
                $onlineSold["{$modeKey}_count"] += 1;
                $onlineSold["{$modeKey}_value"] += $price;
                if ($price === 0.0) $freeSoldOnlineCount++;
            } elseif ($isRefunded && $isPos) {
                $posRefunded["{$modeKey}_count"] += 1;
                $posRefunded["{$modeKey}_value"] += $price;
            } elseif ($isRefunded && !$isPos) {
                $onlinRefunded["{$modeKey}_count"] += 1;
                $onlinRefunded["{$modeKey}_value"] += $price;
            }
        }

        // Round bucket values once, at the end
        foreach ([&$onlineSold, &$onlinRefunded, &$posSold, &$posRefunded] as &$b) {
            $b['included_value'] = round($b['included_value'], 2);
            $b['on_top_value'] = round($b['on_top_value'], 2);
        }
        unset($b);

        $soldTicketCount = $onlineSold['included_count'] + $onlineSold['on_top_count']
                         + $posSold['included_count'] + $posSold['on_top_count'];
        $soldTicketValue = round(
            $onlineSold['included_value'] + $onlineSold['on_top_value']
            + $posSold['included_value'] + $posSold['on_top_value'], 2
        );
        $refundedTicketCount = $onlinRefunded['included_count'] + $onlinRefunded['on_top_count']
                             + $posRefunded['included_count'] + $posRefunded['on_top_count'];
        $refundedTicketValue = round(
            $onlinRefunded['included_value'] + $onlinRefunded['on_top_value']
            + $posRefunded['included_value'] + $posRefunded['on_top_value'], 2
        );

        // === TIXELLO COMMISSION FROM TICKET PRICES (transparent derivation) ===
        // Marketplace rule: 1% × (sold + refunded ticket prices). The
        // per-event tixello_commission column in the events table below is
        // also switched to this basis so per-event totals sum to the same
        // grand total shown at the top.
        $tixelloCommissionByBucket = [
            'online_sold_included'    => round($onlineSold['included_value']    * ($commissionRate/100), 2),
            'online_sold_on_top'      => round($onlineSold['on_top_value']      * ($commissionRate/100), 2),
            'pos_sold_included'       => round($posSold['included_value']       * ($commissionRate/100), 2),
            'pos_sold_on_top'         => round($posSold['on_top_value']         * ($commissionRate/100), 2),
            'online_refunded_included'=> round($onlinRefunded['included_value'] * ($commissionRate/100), 2),
            'online_refunded_on_top'  => round($onlinRefunded['on_top_value']   * ($commissionRate/100), 2),
            'pos_refunded_included'   => round($posRefunded['included_value']   * ($commissionRate/100), 2),
            'pos_refunded_on_top'     => round($posRefunded['on_top_value']     * ($commissionRate/100), 2),
        ];

        // === INVITATIONS ISSUED IN PERIOD ===
        // Standalone invitations = tickets with order_id NULL and meta.is_invitation=true.
        // Scoped to marketplace events (via ticket_types.event_id).
        $invitationCount = 0;
        if (!empty($mpEventIds)) {
            $invitationCount = (int) DB::table('tickets as t')
                ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->whereNull('t.order_id')
                ->whereIn('tt.event_id', $mpEventIds)
                ->whereBetween('t.created_at', [$monthStart, $monthEnd])
                ->whereRaw("(t.meta::jsonb ->> 'is_invitation') = 'true'")
                ->count();
        }

        // === FREE TICKETS SOLD (via a real order, price = 0) ===
        // Excludes invitations (which have no order). Includes tickets sold
        // for free through bulk_admin / marketplace_free / promo_100_percent.
        $freeTicketCount = $freeSoldOnlineCount + $freeSoldPosCount;

        // === PER-EVENT TICKET PRICE BASE + TIXELLO COMMISSION ===
        // Second pass over the same ticket rows to bucket per event id, so
        // the events table's tixello_commission column agrees with the
        // grand total shown at the top of the page. Both sold and refunded
        // tickets contribute per the marketplace rule.
        foreach ($ticketRows as $t) {
            $isRefunded = $t->refund_status === 'refunded';
            $isSold = !$isRefunded && in_array($t->status, ['valid', 'used'], true);
            if (!$isSold && !$isRefunded) continue;
            $eid = $t->resolved_event_id;
            if (!isset($events[$eid])) continue;
            $events[$eid]['ticket_price_base'] += (float) $t->price;
        }
        foreach ($events as $eid => &$evt) {
            $evt['ticket_price_base'] = round($evt['ticket_price_base'], 2);
            $evt['tixello_commission'] = round($evt['ticket_price_base'] * ($commissionRate / 100), 2);
        }
        unset($evt);
        $events = array_values($events);

        // Grand total Tixello commission (ticketing) = 1% × total ticket price base
        $ticketBaseTotal = round($soldTicketValue + $refundedTicketValue, 2);
        $ticketingTotal = round($ticketBaseTotal * ($commissionRate / 100), 2);

        // === SERVICE ORDERS BREAKDOWN ===
        $serviceOrders = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['active', 'completed'])
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->with(['event', 'organizer'])
            ->orderBy('service_type')
            ->orderByDesc('total')
            ->get();

        $serviceLabels = [
            'featuring' => 'Promovare Eveniment',
            'email' => 'Email Marketing',
            'tracking' => 'Ad Tracking',
            'campaign' => 'Creare Campanie',
        ];

        // Group by service type
        // Tixello collects only TIXELLO_SHARE of each service order; per-row and per-type totals reflect that share.
        $tixelloShare = ServiceOrder::TIXELLO_SHARE;
        $servicesByType = [];
        foreach ($serviceLabels as $type => $label) {
            $typeOrders = $serviceOrders->where('service_type', $type);
            $servicesByType[$type] = [
                'label' => $label,
                'orders' => $typeOrders->map(function ($so) use ($tixelloShare) {
                    $gross = (float) $so->total;
                    return [
                        'id' => $so->id,
                        'order_number' => $so->order_number,
                        'event_name' => $so->event ? ($so->event->getTranslation('title', 'ro') ?: $so->event->getTranslation('title', 'en')) : '-',
                        'organizer_name' => $so->organizer?->name ?? '-',
                        'total' => round($gross * $tixelloShare, 2),
                        'gross_total' => $gross,
                        'created_at' => $so->created_at->format('d.m.Y'),
                        'config_label' => $so->config['package'] ?? $so->config['plan'] ?? null,
                    ];
                })->values()->toArray(),
                'total' => round((float) $typeOrders->sum('total') * $tixelloShare, 2),
            ];
        }

        $servicesTotal = collect($servicesByType)->sum('total');

        return [
            'marketplace' => $marketplace,
            'data' => [
                'month_label' => $monthDate->translatedFormat('F Y'),
                'month' => $this->month,
                'currency' => $currency,
                'commission_rate' => $commissionRate,
                'events' => $events,
                'ticketing_total' => $ticketingTotal,
                'revenue_total' => $revenueTotal,
                'marketplace_commission_total' => $marketplaceCommissionTotal,
                'services_by_type' => $servicesByType,
                'services_total' => $servicesTotal,
                'grand_total' => $ticketingTotal + $servicesTotal,
                'is_current_month' => $monthDate->format('Y-m') === Carbon::now()->format('Y-m'),
                // Detailed breakdown under the 4 summary cards
                'online_revenue' => $onlineRevenue,
                'online_orders' => $onlineOrders,
                'pos_revenue' => $posRevenue,
                'pos_orders' => $posOrders,
                'sold_ticket_count' => $soldTicketCount,
                'sold_ticket_value' => $soldTicketValue,
                'refunded_ticket_count' => $refundedTicketCount,
                'refunded_ticket_value' => $refundedTicketValue,

                // Breakdown by commission mode
                'online_sold' => $onlineSold,           // included/on_top count+value
                'online_refunded' => $onlinRefunded,
                'pos_sold' => $posSold,
                'pos_refunded' => $posRefunded,

                // Extra counts
                'invitation_count' => $invitationCount,
                'free_ticket_count' => $freeTicketCount,

                // Transparent commission derivation
                'commission_by_bucket' => $tixelloCommissionByBucket,
                'ticket_base_total' => $ticketBaseTotal,
            ],
        ];
    }
}
