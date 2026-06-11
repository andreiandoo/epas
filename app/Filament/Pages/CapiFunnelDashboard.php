<?php

namespace App\Filament\Pages;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class CapiFunnelDashboard extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-funnel';
    protected string $view = 'filament.pages.capi-funnel-dashboard';
    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';
    protected static ?int $navigationSort = 6;
    protected static ?string $title = 'CAPI Funnel Analytics';
    protected static ?string $navigationLabel = 'CAPI Funnel';

    /** @var array<string,int> */
    public array $funnelCounts = [];
    /** @var array<string,float> */
    public array $funnelDropoffPct = [];
    public float $overallConversionPct = 0;
    public int $totalOrders = 0;
    public float $totalRevenue = 0;

    /** @var array<int,array<string,mixed>> */
    public array $organizerLeaderboard = [];

    /** @var array<int,array<string,mixed>> */
    public array $marketplaces = [];

    /** @var array<int,array<string,mixed>> */
    public array $organizers = [];

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?int $marketplaceClientId = null;

    #[Url]
    public ?int $marketplaceOrganizerId = null;

    public const FUNNEL_STEPS = [
        'page_view' => 'PageView',
        'view_item' => 'ViewContent',
        'add_to_cart' => 'AddToCart',
        'begin_checkout' => 'InitiateCheckout',
        'purchase' => 'Purchase',
    ];

    public function mount(): void
    {
        $this->startDate = $this->startDate ?? now()->subDays(30)->format('Y-m-d');
        $this->endDate = $this->endDate ?? now()->format('Y-m-d');

        $this->loadFilterOptions();
        $this->calculateFunnel();
    }

    public function updatedStartDate(): void
    {
        $this->calculateFunnel();
    }

    public function updatedEndDate(): void
    {
        $this->calculateFunnel();
    }

    public function updatedMarketplaceClientId(): void
    {
        // Reset organizer if it doesn't belong to the new marketplace
        if ($this->marketplaceOrganizerId) {
            $belongs = MarketplaceOrganizer::where('id', $this->marketplaceOrganizerId)
                ->where('marketplace_client_id', $this->marketplaceClientId)
                ->exists();
            if (!$belongs) {
                $this->marketplaceOrganizerId = null;
            }
        }
        $this->loadFilterOptions();
        $this->calculateFunnel();
    }

    public function updatedMarketplaceOrganizerId(): void
    {
        $this->calculateFunnel();
    }

    protected function loadFilterOptions(): void
    {
        $this->marketplaces = MarketplaceClient::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
            ->all();

        $organizersQuery = MarketplaceOrganizer::query()->orderBy('name');
        if ($this->marketplaceClientId) {
            $organizersQuery->where('marketplace_client_id', $this->marketplaceClientId);
        }
        $this->organizers = $organizersQuery
            ->limit(500)
            ->get(['id', 'name', 'marketplace_client_id'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'marketplace_client_id' => $o->marketplace_client_id,
            ])
            ->all();
    }

    protected function calculateFunnel(): void
    {
        $startDate = Carbon::parse($this->startDate)->startOfDay();
        $endDate = Carbon::parse($this->endDate)->endOfDay();

        $base = DB::table('core_customer_events')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if ($this->marketplaceClientId) {
            $base->where('marketplace_client_id', $this->marketplaceClientId);
        }

        if ($this->marketplaceOrganizerId) {
            // Constrain via marketplace_event_id ↔ events.marketplace_organizer_id
            $eventIds = DB::table('events')
                ->where('marketplace_organizer_id', $this->marketplaceOrganizerId)
                ->pluck('id');
            if ($eventIds->isEmpty()) {
                $base->whereRaw('1 = 0'); // no matching events → no funnel rows
            } else {
                $base->whereIn('marketplace_event_id', $eventIds);
            }
        }

        // Count distinct visitors per event_type (more meaningful than raw event count
        // — a visitor refreshing 3 times shouldn't inflate the PageView column).
        $rows = (clone $base)
            ->whereIn('event_type', array_keys(self::FUNNEL_STEPS))
            ->select('event_type', DB::raw('COUNT(DISTINCT visitor_id) as c'))
            ->groupBy('event_type')
            ->pluck('c', 'event_type');

        $this->funnelCounts = [];
        $previous = null;
        $this->funnelDropoffPct = [];
        foreach (self::FUNNEL_STEPS as $type => $label) {
            $count = (int) ($rows[$type] ?? 0);
            $this->funnelCounts[$label] = $count;
            if ($previous !== null && $previous > 0) {
                $this->funnelDropoffPct[$label] = round((1 - $count / $previous) * 100, 1);
            } else {
                $this->funnelDropoffPct[$label] = 0;
            }
            $previous = $count;
        }

        $pageViews = $this->funnelCounts['PageView'] ?? 0;
        $purchases = $this->funnelCounts['Purchase'] ?? 0;
        $this->overallConversionPct = $pageViews > 0
            ? round($purchases / $pageViews * 100, 2)
            : 0.0;

        // Order/revenue stats from the same window — uses the dedicated orders
        // table (more reliable than purchase events, which depend on tracking
        // having fired client-side).
        $orderQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['paid', 'confirmed', 'completed']);
        if ($this->marketplaceClientId) {
            $orderQuery->where('marketplace_client_id', $this->marketplaceClientId);
        }
        if ($this->marketplaceOrganizerId) {
            $orderQuery->where('marketplace_organizer_id', $this->marketplaceOrganizerId);
        }
        $orderStats = $orderQuery->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as rev')->first();
        $this->totalOrders = (int) ($orderStats->cnt ?? 0);
        $this->totalRevenue = (float) ($orderStats->rev ?? 0);

        $this->loadOrganizerLeaderboard($startDate, $endDate);
    }

    /**
     * Top organizers by conversion rate (Purchase visitors / PageView visitors).
     * Limited to organizers with at least 30 unique page-view visitors so we
     * don't promote outliers from low-traffic events.
     */
    protected function loadOrganizerLeaderboard(Carbon $startDate, Carbon $endDate): void
    {
        $sub = DB::table('core_customer_events as ce')
            ->join('events as e', 'e.id', '=', 'ce.marketplace_event_id')
            ->whereNotNull('e.marketplace_organizer_id')
            ->whereBetween('ce.occurred_at', [$startDate, $endDate])
            ->whereIn('ce.event_type', ['page_view', 'purchase']);

        if ($this->marketplaceClientId) {
            $sub->where('ce.marketplace_client_id', $this->marketplaceClientId);
        }

        $rows = $sub->select(
                'e.marketplace_organizer_id as oid',
                'ce.event_type',
                DB::raw('COUNT(DISTINCT ce.visitor_id) as c')
            )
            ->groupBy('e.marketplace_organizer_id', 'ce.event_type')
            ->get();

        $byOrg = [];
        foreach ($rows as $row) {
            $byOrg[$row->oid][$row->event_type] = (int) $row->c;
        }

        $names = MarketplaceOrganizer::whereIn('id', array_keys($byOrg))
            ->pluck('name', 'id');

        $list = [];
        foreach ($byOrg as $oid => $counts) {
            $pv = $counts['page_view'] ?? 0;
            $pu = $counts['purchase'] ?? 0;
            if ($pv < 30) continue;
            $rate = $pv > 0 ? round($pu / $pv * 100, 2) : 0;
            $list[] = [
                'organizer_id' => $oid,
                'name' => $names[$oid] ?? ('#' . $oid),
                'page_views' => $pv,
                'purchases' => $pu,
                'conversion_pct' => $rate,
            ];
        }

        usort($list, fn ($a, $b) => $b['conversion_pct'] <=> $a['conversion_pct']);
        $this->organizerLeaderboard = array_slice($list, 0, 10);
    }
}
