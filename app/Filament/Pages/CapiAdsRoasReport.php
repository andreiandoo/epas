<?php

namespace App\Filament\Pages;

use App\Models\Integrations\FacebookCapi\FacebookAdsAccount;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class CapiAdsRoasReport extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';
    protected string $view = 'filament.pages.capi-ads-roas-report';
    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';
    protected static ?int $navigationSort = 8;
    protected static ?string $title = 'Meta Ads ROAS Report';
    protected static ?string $navigationLabel = 'Ads ROAS';

    public float $totalSpend = 0;
    public float $totalRevenue = 0;
    public float $roas = 0;
    public int $totalImpressions = 0;
    public int $totalClicks = 0;
    public float $avgCtr = 0;
    public float $avgCpc = 0;
    public int $attributedOrderCount = 0;

    /** @var array<int,array<string,mixed>> */
    public array $campaignTable = [];
    /** @var array<int,array<string,mixed>> */
    public array $marketplaces = [];
    /** @var array<int,array<string,mixed>> */
    public array $organizers = [];

    public bool $hasNoData = false;

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?int $marketplaceClientId = null;

    #[Url]
    public ?int $marketplaceOrganizerId = null;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?? now()->subDays(30)->format('Y-m-d');
        $this->endDate = $this->endDate ?? now()->format('Y-m-d');
        $this->loadFilters();
        $this->calculate();
    }

    public function updatedStartDate(): void { $this->calculate(); }
    public function updatedEndDate(): void { $this->calculate(); }
    public function updatedMarketplaceClientId(): void { $this->loadFilters(); $this->calculate(); }
    public function updatedMarketplaceOrganizerId(): void { $this->calculate(); }

    protected function loadFilters(): void
    {
        // Only list marketplaces that have at least one organizer with an
        // active CAPI connection — irrelevant entries shouldn't appear here.
        $this->marketplaces = MarketplaceClient::query()
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('facebook_capi_connections')
                    ->whereColumn('facebook_capi_connections.marketplace_client_id', 'marketplace_clients.id')
                    ->where('facebook_capi_connections.status', 'active');
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
            ->all();

        // Organizers limited to those with active CAPI (and matching the
        // selected marketplace, when one is set).
        $orgs = MarketplaceOrganizer::query()
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('facebook_capi_connections')
                    ->whereColumn('facebook_capi_connections.marketplace_organizer_id', 'marketplace_organizers.id')
                    ->where('facebook_capi_connections.status', 'active');
            })
            ->orderBy('name');
        if ($this->marketplaceClientId) {
            $orgs->where('marketplace_client_id', $this->marketplaceClientId);
        }
        $this->organizers = $orgs->limit(500)->get(['id', 'name'])
            ->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->all();
    }

    protected function calculate(): void
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $accountIds = $this->resolveAccountIdsForFilters();
        if ($accountIds->isEmpty()) {
            $this->hasNoData = true;
            $this->resetTotals();
            return;
        }
        $this->hasNoData = false;

        // Aggregate insights for the window.
        $insights = DB::table('facebook_ads_insights')
            ->whereIn('ads_account_id', $accountIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->select(
                'fb_campaign_id',
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(spend) as spend'),
                DB::raw('SUM(conversions) as meta_conversions'),
                DB::raw('SUM(conversion_value) as meta_conversion_value')
            )
            ->groupBy('fb_campaign_id')
            ->get();

        $this->totalSpend = (float) $insights->sum('spend');
        $this->totalImpressions = (int) $insights->sum('impressions');
        $this->totalClicks = (int) $insights->sum('clicks');
        $this->avgCtr = $this->totalImpressions > 0
            ? round($this->totalClicks / $this->totalImpressions * 100, 3)
            : 0;
        $this->avgCpc = $this->totalClicks > 0
            ? round($this->totalSpend / $this->totalClicks, 4)
            : 0;

        // Tixello-side revenue: orders in window with fbclid in meta JSON.
        // Maps each fbclid to a campaign would require Meta's click-id ↔
        // campaign mapping (not exposed via Marketing API). We approximate
        // by attributing ALL fbclid revenue to the campaign mix here, then
        // per-campaign by total clicks weight.
        // Single aggregate query (sum + count) wrapped in a 5-min cache.
        // Keeps the page snappy when an admin clicks around date pickers
        // and prevents the multi-month-window Cloudflare timeout.
        $cacheKey = sprintf(
            'capi_roas_rev:%s:%s:%s:%s',
            $start->toDateString(),
            $end->toDateString(),
            $this->marketplaceClientId ?: 'all',
            $this->marketplaceOrganizerId ?: 'all'
        );
        $attr = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($start, $end) {
            $row = $this->fbclidRevenueQuery($start, $end)
                ->selectRaw('COALESCE(SUM(orders.total), 0) as rev, COUNT(*) as cnt')
                ->first();
            return [
                'revenue' => (float) ($row->rev ?? 0),
                'count' => (int) ($row->cnt ?? 0),
            ];
        });
        $totalRevenueFromFbAds = $attr['revenue'];
        $this->attributedOrderCount = $attr['count'];
        $this->totalRevenue = $totalRevenueFromFbAds;
        $this->roas = $this->totalSpend > 0 ? round($this->totalRevenue / $this->totalSpend, 2) : 0;

        // Per-campaign table — clicks-weighted revenue allocation.
        $campaigns = DB::table('facebook_ads_campaigns')
            ->whereIn('ads_account_id', $accountIds)
            ->pluck('name', 'fb_campaign_id');

        $list = [];
        foreach ($insights as $row) {
            $clicks = (int) $row->clicks;
            $spend = (float) $row->spend;
            $revShare = $this->totalClicks > 0
                ? ($clicks / $this->totalClicks) * $totalRevenueFromFbAds
                : 0;
            $roasCampaign = $spend > 0 ? round($revShare / $spend, 2) : 0;
            $ctr = (int) $row->impressions > 0 ? round($clicks / $row->impressions * 100, 3) : 0;
            $cpc = $clicks > 0 ? round($spend / $clicks, 4) : 0;

            $list[] = [
                'fb_campaign_id' => $row->fb_campaign_id,
                'name' => $campaigns[$row->fb_campaign_id] ?? ('campaign_' . $row->fb_campaign_id),
                'impressions' => (int) $row->impressions,
                'clicks' => $clicks,
                'spend' => $spend,
                'ctr' => $ctr,
                'cpc' => $cpc,
                'meta_conversions' => (int) $row->meta_conversions,
                'meta_conversion_value' => (float) $row->meta_conversion_value,
                'allocated_revenue' => round($revShare, 2),
                'roas' => $roasCampaign,
            ];
        }

        usort($list, fn ($a, $b) => $b['spend'] <=> $a['spend']);
        $this->campaignTable = $list;
    }

    /**
     * Orders attributable to Facebook ads via ANY of three signals:
     *   1. orders.meta JSON contains fbclid/fbc (last-touch at checkout)
     *   2. core_customer_events has fbclid set for this order_id (mid-funnel)
     *   3. core_customers.last_fbclid set for the customer (last-seen)
     *
     * Different checkout flows store the click id in different places;
     * union of all three avoids missing revenue that's clearly attributable.
     */
    protected function fbclidRevenueQuery(Carbon $start, Carbon $end)
    {
        $query = DB::table('orders')
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
            ->where(function ($q) {
                // jsonb ? 'key' is an index-friendly key-existence check;
                // way faster than LIKE on the serialized text form.
                $q->whereRaw("(orders.meta::jsonb ? 'fbclid' OR orders.meta::jsonb ? 'fbc')")
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('core_customer_events')
                            ->whereColumn('core_customer_events.order_id', 'orders.id')
                            ->whereNotNull('core_customer_events.fbclid');
                    })
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('core_customers')
                            ->whereColumn('core_customers.email', 'orders.customer_email')
                            ->whereNotNull('core_customers.last_fbclid');
                    });
            });

        if ($this->marketplaceClientId) {
            $query->where('orders.marketplace_client_id', $this->marketplaceClientId);
        }
        if ($this->marketplaceOrganizerId) {
            $query->where('orders.marketplace_organizer_id', $this->marketplaceOrganizerId);
        }
        return $query;
    }

    protected function resolveAccountIdsForFilters()
    {
        $q = FacebookAdsAccount::query();
        if ($this->marketplaceOrganizerId) {
            $q->where('marketplace_organizer_id', $this->marketplaceOrganizerId);
        } elseif ($this->marketplaceClientId) {
            $q->where('marketplace_client_id', $this->marketplaceClientId);
        }
        return $q->pluck('id');
    }

    protected function resetTotals(): void
    {
        $this->totalSpend = 0;
        $this->totalRevenue = 0;
        $this->roas = 0;
        $this->totalImpressions = 0;
        $this->totalClicks = 0;
        $this->avgCtr = 0;
        $this->avgCpc = 0;
        $this->attributedOrderCount = 0;
        $this->campaignTable = [];
    }
}
