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
            ->where('source', '!=', 'test_order')->where('source', '!=', 'external_import');

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

        $eventBreakdown = Order::where(function ($q) use ($marketplaceId, $mpEventIds) {
                $q->where('marketplace_client_id', $marketplaceId);
                if (!empty($mpEventIds)) {
                    $q->orWhereIn('marketplace_event_id', $mpEventIds)
                      ->orWhereIn('event_id', $mpEventIds);
                }
            })
            ->whereIn('status', $validStatuses)
            ->where('source', '!=', 'test_order')->where('source', '!=', 'external_import')
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
                ->mapWithKeys(function ($e) {
                    $name = $e->getTranslation('title', 'ro') ?: $e->getTranslation('title', 'en');
                    $venueName = $e->venue ? (is_array($e->venue->name) ? ($e->venue->name['ro'] ?? $e->venue->name['en'] ?? '') : $e->venue->name) : null;
                    $venueCity = $e->venue?->city;
                    $eventDate = $e->event_date ?? $e->range_start_date;
                    // Commission rate fallback: event → organizer → marketplace default
                    $eventCommRate = $e->commission_rate
                        ?? $e->marketplaceOrganizer?->commission_rate
                        ?? null;
                    return [$e->id => [
                        'name' => $name,
                        'date' => $eventDate?->format('d.m.Y'),
                        'venue' => $venueName,
                        'city' => $venueCity,
                        'commission_rate' => $eventCommRate,
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
            $revenueAll = (float) $row->revenue_with_refunds; // includes refunded (for commission calc)
            $marketplaceCommission = (float) ($row->marketplace_commission ?? 0);
            $details = $eventDetails[$eventId] ?? null;

            // If SQL couldn't calculate commission (both commission_amount and commission_rate are 0/null),
            // fall back to event's commission_rate or marketplace default
            // Commission is calculated from ALL orders including refunded
            if ($marketplaceCommission <= 0 && $revenueAll > 0) {
                $eventCommRate = $details['commission_rate'] ?? $marketplace->commission_rate ?? 5;
                $marketplaceCommission = round($revenueAll * ((float) $eventCommRate / 100), 2);
            }

            // Tixello commission also from all orders (including refunded)
            $tixelloCommission = round($revenueAll * ($commissionRate / 100), 2);

            return [
                'event_id' => $eventId,
                'event_name' => $details['name'] ?? ($eventId ? 'Eveniment #' . $eventId : 'Necunoscut'),
                'event_date' => $details['date'] ?? null,
                'venue' => $details['venue'] ?? null,
                'city' => $details['city'] ?? null,
                'order_count' => (int) $row->order_count,
                'ticket_count' => (int) ($ticketCounts[$eventId] ?? 0),
                'revenue' => $revenue,
                'marketplace_commission' => $marketplaceCommission,
                'tixello_commission' => $tixelloCommission,
            ];
        })->toArray();

        $revenueTotal = collect($events)->sum('revenue');
        // Tixello commission total from per-event calculated values (which use revenue_with_refunds)
        $ticketingTotal = collect($events)->sum('tixello_commission');
        $marketplaceCommissionTotal = collect($events)->sum('marketplace_commission');

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
            ],
        ];
    }
}
