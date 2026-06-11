<?php

namespace App\Filament\Pages;

use App\Models\MarketplaceClient;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class CapiCustomerIntelligence extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected string $view = 'filament.pages.capi-customer-intelligence';
    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';
    protected static ?int $navigationSort = 7;
    protected static ?string $title = 'Customer Intelligence';
    protected static ?string $navigationLabel = 'Customer Intelligence';

    public int $visitorsTotal = 0;
    public int $customersWithPurchase = 0;
    public int $repeatCustomers = 0;
    public int $crossOrganizerLoyalists = 0;
    public float $avgLtv = 0;
    public float $avgTimeToPurchaseDays = 0;
    public int $totalRevenue = 0;

    /** @var array<int,array<string,mixed>> */
    public array $topCustomersByLtv = [];

    /** @var array<int,array<string,mixed>> */
    public array $multiOrganizerLoyalists = [];

    /** @var array<int,array<string,mixed>> */
    public array $channelMix = [];

    /** @var array<int,array<string,mixed>> */
    public array $marketplaces = [];

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?int $marketplaceClientId = null;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?? now()->subDays(90)->format('Y-m-d');
        $this->endDate = $this->endDate ?? now()->format('Y-m-d');

        $this->loadFilterOptions();
        $this->calculate();
    }

    public function updatedStartDate(): void
    {
        $this->calculate();
    }

    public function updatedEndDate(): void
    {
        $this->calculate();
    }

    public function updatedMarketplaceClientId(): void
    {
        $this->calculate();
    }

    protected function loadFilterOptions(): void
    {
        $this->marketplaces = MarketplaceClient::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
            ->all();
    }

    protected function calculate(): void
    {
        $startDate = Carbon::parse($this->startDate)->startOfDay();
        $endDate = Carbon::parse($this->endDate)->endOfDay();

        $this->loadTopCardStats($startDate, $endDate);
        $this->loadTopCustomersByLtv($startDate, $endDate);
        $this->loadMultiOrganizerLoyalists($startDate, $endDate);
        $this->loadChannelMix($startDate, $endDate);
    }

    protected function loadTopCardStats(Carbon $startDate, Carbon $endDate): void
    {
        $eventsBase = DB::table('core_customer_events')
            ->whereBetween('occurred_at', [$startDate, $endDate]);
        if ($this->marketplaceClientId) {
            $eventsBase->where('marketplace_client_id', $this->marketplaceClientId);
        }

        $this->visitorsTotal = (int) (clone $eventsBase)
            ->distinct()
            ->count('visitor_id');

        $purchaseVisitors = (clone $eventsBase)
            ->where('event_type', 'purchase')
            ->distinct()
            ->pluck('visitor_id');
        $this->customersWithPurchase = $purchaseVisitors->count();

        // Repeat customers + cross-organizer loyalists derive from orders so
        // refunded/abandoned tracking events don't poison the numbers.
        $orderQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['paid', 'confirmed', 'completed']);
        if ($this->marketplaceClientId) {
            $orderQuery->where('marketplace_client_id', $this->marketplaceClientId);
        }

        $this->totalRevenue = (int) round((float) (clone $orderQuery)->sum('total'));

        // Repeat = customers (by email) with ≥ 2 paid orders in window.
        $byEmail = (clone $orderQuery)
            ->whereNotNull('customer_email')
            ->select('customer_email', DB::raw('COUNT(*) as c'), DB::raw('COUNT(DISTINCT marketplace_organizer_id) as orgs'))
            ->groupBy('customer_email')
            ->get();
        $this->repeatCustomers = $byEmail->where('c', '>=', 2)->count();
        $this->crossOrganizerLoyalists = $byEmail->where('orgs', '>=', 2)->count();

        // Avg LTV from core_customers — global, not interval-bound (LTV is a
        // lifetime metric by definition). Marketplace filter ignored here too.
        $coreCustomersBase = DB::table('core_customers')->where('total_orders', '>', 0);
        $this->avgLtv = (float) ($coreCustomersBase->avg('lifetime_value') ?? 0);

        $this->avgTimeToPurchaseDays = (float) DB::table('core_customers')
            ->whereNotNull('first_seen_at')
            ->whereNotNull('first_purchase_at')
            ->where('total_orders', '>', 0)
            ->selectRaw("AVG(EXTRACT(EPOCH FROM (first_purchase_at - first_seen_at)) / 86400) as d")
            ->value('d') ?? 0;
        $this->avgTimeToPurchaseDays = round($this->avgTimeToPurchaseDays, 1);
    }

    protected function loadTopCustomersByLtv(Carbon $startDate, Carbon $endDate): void
    {
        // Top customers by spending in the selected window.
        $query = DB::table('orders')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotNull('customer_email');

        if ($this->marketplaceClientId) {
            $query->where('marketplace_client_id', $this->marketplaceClientId);
        }

        $rows = $query->select(
                'customer_email',
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COUNT(DISTINCT marketplace_organizer_id) as organizers_count'),
                DB::raw('SUM(total) as total_spent'),
                DB::raw('MAX(created_at) as last_purchase_at')
            )
            ->groupBy('customer_email')
            ->orderByDesc('total_spent')
            ->limit(20)
            ->get();

        $this->topCustomersByLtv = $rows->map(fn ($r) => [
            'email_masked' => $this->maskEmail($r->customer_email),
            'customer_name' => $r->customer_name,
            'orders_count' => (int) $r->orders_count,
            'organizers_count' => (int) $r->organizers_count,
            'total_spent' => (float) $r->total_spent,
            'last_purchase_at' => $r->last_purchase_at,
        ])->all();
    }

    protected function loadMultiOrganizerLoyalists(Carbon $startDate, Carbon $endDate): void
    {
        // Customers who bought from ≥ 2 distinct organizers in the window.
        $query = DB::table('orders')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotNull('customer_email')
            ->whereNotNull('marketplace_organizer_id');

        if ($this->marketplaceClientId) {
            $query->where('marketplace_client_id', $this->marketplaceClientId);
        }

        $rows = $query->select(
                'customer_email',
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw('COUNT(DISTINCT marketplace_organizer_id) as organizers_count'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as total_spent')
            )
            ->groupBy('customer_email')
            ->having(DB::raw('COUNT(DISTINCT marketplace_organizer_id)'), '>=', 2)
            ->orderByDesc('organizers_count')
            ->orderByDesc('total_spent')
            ->limit(15)
            ->get();

        $this->multiOrganizerLoyalists = $rows->map(fn ($r) => [
            'email_masked' => $this->maskEmail($r->customer_email),
            'customer_name' => $r->customer_name,
            'organizers_count' => (int) $r->organizers_count,
            'orders_count' => (int) $r->orders_count,
            'total_spent' => (float) $r->total_spent,
        ])->all();
    }

    protected function loadChannelMix(Carbon $startDate, Carbon $endDate): void
    {
        // first_source distribution among customers seen for the first time
        // in window (best signal for "where did our acquisitions come from").
        $query = DB::table('core_customers')
            ->whereBetween('first_seen_at', [$startDate, $endDate]);

        $rows = $query->select(
                DB::raw("COALESCE(NULLIF(first_source, ''), 'direct') as source"),
                DB::raw('COUNT(*) as visitors'),
                DB::raw('SUM(CASE WHEN total_orders > 0 THEN 1 ELSE 0 END) as buyers'),
                DB::raw('COALESCE(SUM(total_spent), 0) as revenue')
            )
            ->groupBy('source')
            ->orderByDesc('visitors')
            ->limit(15)
            ->get();

        $this->channelMix = $rows->map(function ($r) {
            $visitors = (int) $r->visitors;
            $buyers = (int) $r->buyers;
            return [
                'source' => $r->source,
                'visitors' => $visitors,
                'buyers' => $buyers,
                'conversion_pct' => $visitors > 0 ? round($buyers / $visitors * 100, 2) : 0,
                'revenue' => (float) $r->revenue,
            ];
        })->all();
    }

    protected function maskEmail(?string $email): string
    {
        if (!$email) return '—';
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return $email;
        [$local, $domain] = $parts;
        $localMasked = mb_substr($local, 0, 1) . str_repeat('*', max(1, mb_strlen($local) - 1));
        $domainParts = explode('.', $domain);
        $domainMasked = mb_substr($domainParts[0], 0, 1) . str_repeat('*', max(1, mb_strlen($domainParts[0]) - 1));
        if (count($domainParts) > 1) {
            $domainMasked .= '.' . implode('.', array_slice($domainParts, 1));
        }
        return $localMasked . '@' . $domainMasked;
    }
}
