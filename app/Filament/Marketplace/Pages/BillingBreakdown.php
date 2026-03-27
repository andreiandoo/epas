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

    public function getViewData(): array
    {
        $marketplace = $this->marketplace;
        if (!$marketplace) {
            return ['marketplace' => null, 'data' => []];
        }

        $marketplaceId = $marketplace->id;
        $monthDate = Carbon::createFromFormat('Y-m', $this->month);
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();
        $currency = $marketplace->currency ?? 'RON';
        $validStatuses = ['paid', 'confirmed', 'completed', 'refunded'];
        $commissionRate = (float) ($marketplace->commission_rate ?? 0);

        // === TICKETING BREAKDOWN PER EVENT ===
        // Use COALESCE to catch orders with event_id but no marketplace_event_id (POS/app)
        $eventBreakdown = Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', $validStatuses)
            ->where('source', '!=', 'test_order')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('COALESCE(marketplace_event_id, event_id) as resolved_event_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw("SUM(CASE WHEN status = 'refunded' THEN 0 ELSE total END) as revenue")
            ->selectRaw("SUM(CASE WHEN status = 'refunded' THEN 0 WHEN commission_amount > 0 THEN commission_amount ELSE total * COALESCE(commission_rate, 0) / 100 END) as marketplace_commission")
            ->selectRaw("SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded_count")
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
            $ticketCounts = DB::table('tickets')
                ->where('marketplace_client_id', $marketplaceId)
                ->whereIn(DB::raw('COALESCE(marketplace_event_id, event_id)'), $eventIds)
                ->whereIn('status', ['valid', 'pending'])
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->selectRaw('COALESCE(marketplace_event_id, event_id) as resolved_event_id, COUNT(*) as cnt')
                ->groupBy('resolved_event_id')
                ->pluck('cnt', 'resolved_event_id')
                ->toArray();
        }

        $events = $eventBreakdown->map(function ($row) use ($eventDetails, $ticketCounts, $commissionRate, $marketplace) {
            $eventId = $row->resolved_event_id;
            $revenue = (float) $row->revenue;
            $marketplaceCommission = (float) ($row->marketplace_commission ?? 0);
            $details = $eventDetails[$eventId] ?? null;

            // If SQL couldn't calculate commission (both commission_amount and commission_rate are 0/null),
            // fall back to event's commission_rate or marketplace default
            if ($marketplaceCommission <= 0 && $revenue > 0) {
                $eventCommRate = $details['commission_rate'] ?? $marketplace->commission_rate ?? 5;
                $marketplaceCommission = round($revenue * ((float) $eventCommRate / 100), 2);
            }

            $tixelloCommission = round($revenue * ($commissionRate / 100), 2);

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
        $ticketingTotal = round($revenueTotal * ($commissionRate / 100), 2);
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
        $servicesByType = [];
        foreach ($serviceLabels as $type => $label) {
            $typeOrders = $serviceOrders->where('service_type', $type);
            $servicesByType[$type] = [
                'label' => $label,
                'orders' => $typeOrders->map(function ($so) {
                    return [
                        'id' => $so->id,
                        'order_number' => $so->order_number,
                        'event_name' => $so->event ? ($so->event->getTranslation('title', 'ro') ?: $so->event->getTranslation('title', 'en')) : '-',
                        'organizer_name' => $so->organizer?->name ?? '-',
                        'total' => (float) $so->total,
                        'created_at' => $so->created_at->format('d.m.Y'),
                        'config_label' => $so->config['package'] ?? $so->config['plan'] ?? null,
                    ];
                })->values()->toArray(),
                'total' => (float) $typeOrders->sum('total'),
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
