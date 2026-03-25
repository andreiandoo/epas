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
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // === TICKETING BREAKDOWN PER EVENT ===
        $eventBreakdown = Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', $paidStatuses)
            ->where('source', '!=', 'test_order')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('marketplace_event_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('SUM(commission_amount) as commission')
            ->groupBy('marketplace_event_id')
            ->orderByDesc('commission')
            ->get();

        // Get event names
        $eventIds = $eventBreakdown->pluck('marketplace_event_id')->filter()->toArray();
        $eventNames = [];
        if (!empty($eventIds)) {
            $eventNames = Event::whereIn('id', $eventIds)
                ->get()
                ->mapWithKeys(function ($e) {
                    return [$e->id => $e->getTranslation('title', 'ro') ?: $e->getTranslation('title', 'en')];
                })
                ->toArray();
        }

        // Get ticket counts per event
        $ticketCounts = [];
        if (!empty($eventIds)) {
            $ticketCounts = DB::table('tickets')
                ->where('marketplace_client_id', $marketplaceId)
                ->whereIn('marketplace_event_id', $eventIds)
                ->where('status', 'valid')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->selectRaw('marketplace_event_id, COUNT(*) as cnt')
                ->groupBy('marketplace_event_id')
                ->pluck('cnt', 'marketplace_event_id')
                ->toArray();
        }

        $events = $eventBreakdown->map(function ($row) use ($eventNames, $ticketCounts) {
            $eventId = $row->marketplace_event_id;
            return [
                'event_id' => $eventId,
                'event_name' => $eventNames[$eventId] ?? 'Eveniment #' . $eventId,
                'order_count' => (int) $row->order_count,
                'ticket_count' => (int) ($ticketCounts[$eventId] ?? 0),
                'revenue' => (float) $row->revenue,
                'commission' => (float) $row->commission,
            ];
        })->toArray();

        $ticketingTotal = collect($events)->sum('commission');
        $revenueTotal = collect($events)->sum('revenue');

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
                'events' => $events,
                'ticketing_total' => $ticketingTotal,
                'revenue_total' => $revenueTotal,
                'services_by_type' => $servicesByType,
                'services_total' => $servicesTotal,
                'grand_total' => $ticketingTotal + $servicesTotal,
                'is_current_month' => $monthDate->format('Y-m') === Carbon::now()->format('Y-m'),
            ],
        ];
    }
}
