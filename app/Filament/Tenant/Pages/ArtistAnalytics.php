<?php

namespace App\Filament\Tenant\Pages;

use App\Enums\TenantType;
use App\Models\Artist;
use App\Support\ArtistAnalyticsMethods;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ArtistAnalytics extends Page
{
    use ArtistAnalyticsMethods;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Analytics';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.tenant.pages.artist-analytics-wrapper';

    public Artist $record;

    // Livewire properties for series (same as ViewArtist)
    public array $seriesMonths = [];
    public array $seriesEvents = [];
    public array $seriesTickets = [];
    public array $seriesRevenue = [];

    // Livewire properties for interactive tools
    public ?int $selectedEventId = null;
    public ?array $eventAnalysis = null;
    public ?int $selectedVenueId = null;
    public ?array $venueAnalysis = null;
    public string $venueSearch = '';
    public array $venueResults = [];

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        if (!$tenant) return false;
        return in_array($tenant->tenant_type, [TenantType::TenantArtist, TenantType::Artist])
            && $tenant->artist_id !== null;
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        $artist = $tenant?->artist;

        if (!$artist) {
            abort(404, 'No artist profile linked to this tenant.');
        }

        $this->record = $artist->load(['artistTypes:id,name,slug', 'artistGenres:id,name,slug']);

        // Build yearly series (same as ViewArtist mount)
        try {
            if (method_exists($this->record, 'buildYearlySeries')) {
                [$months, $events, $tickets, $revenue] = $this->record->buildYearlySeries();
                $this->seriesMonths = $months;
                $this->seriesEvents = $events;
                $this->seriesTickets = $tickets;
                $this->seriesRevenue = $revenue;
            }
        } catch (\Exception $e) {
            \Log::warning('ArtistAnalytics: buildYearlySeries failed', ['error' => $e->getMessage()]);
        }
    }

    public function getHeading(): string
    {
        return $this->record->name;
    }

    public function getTitle(): string
    {
        return 'Analytics — ' . $this->record->name;
    }

    public function getViewData(): array
    {
        $cacheKey = "artist_analytics_tenant_{$this->record->id}";
        if (request()->has('refresh_analytics')) {
            Cache::forget($cacheKey);
        }

        $emptyData = [
            'kpis' => [], 'months' => [], 'events' => [], 'tickets' => [], 'revenue' => [],
            'from' => now()->subDays(365), 'to' => now(),
            'artistEvents' => collect(), 'artistVenues' => collect(), 'artistTenants' => collect(),
            'topVenues' => collect(), 'topCities' => collect(), 'topCounties' => collect(),
            'coreStats' => ['total_tickets' => 0, 'unique_buyers' => 0, 'total_revenue' => 0],
            'audiencePersonas' => ['personas' => [], 'totals' => ['total_customers' => 0, 'with_demographics' => 0, 'age_distribution' => [], 'gender_overall' => []]],
            'geoIntelligence' => [], 'performanceDeepDive' => ['events' => [], 'role_comparison' => [], 'customer_loyalty' => []],
            'salesIntelligence' => ['channels' => [], 'purchase_timing' => [], 'price_sensitivity' => [], 'velocity_curves' => [], 'fee_comparison' => null],
            'expansionPlanner' => [], 'upcomingAnalysis' => [], 'opportunities' => [],
        ];

        return Cache::remember($cacheKey, 300, function () use ($emptyData) {
            try {
            $from = now()->subDays(365)->startOfDay();
            $to = now()->endOfDay();

            $kpis = $this->record->computeKpis($from, $to);

            $artistEvents = $this->record->events()
                ->with(['venue', 'tenant', 'marketplaceClient'])
                ->orderBy('event_date', 'desc')
                ->get();

            $artistVenues = $artistEvents->pluck('venue')->filter()->unique('id')->values();
            $artistTenants = $artistEvents->pluck('tenant')->filter()->unique('id')->values();

            $artistId = $this->record->id;
            $ticketBase = fn () => DB::table('tickets')
                ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                ->join('events', 'events.id', '=', 'ticket_types.event_id')
                ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                ->join('venues', 'venues.id', '=', 'events.venue_id')
                ->where('event_artist.artist_id', $artistId);

            $topVenues = $ticketBase()
                ->select('venues.id', 'venues.name', DB::raw('COUNT(tickets.id) as tickets_count'))
                ->groupBy('venues.id', 'venues.name')
                ->orderByDesc('tickets_count')->limit(10)->get();

            $topCities = $ticketBase()->whereNotNull('venues.city')
                ->join('orders', 'orders.id', '=', 'tickets.order_id')
                ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
                ->select('venues.city as name', DB::raw('COUNT(tickets.id) as tickets_count'), DB::raw('COUNT(DISTINCT COALESCE(orders.marketplace_customer_id, orders.customer_id)) as fans_count'))
                ->groupBy('venues.city')
                ->orderByDesc('tickets_count')->limit(10)->get();

            $topCounties = $ticketBase()->whereNotNull('venues.state')
                ->join('orders', 'orders.id', '=', 'tickets.order_id')
                ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
                ->select('venues.state as name', DB::raw('COUNT(tickets.id) as tickets_count'), DB::raw('COUNT(DISTINCT COALESCE(orders.marketplace_customer_id, orders.customer_id)) as fans_count'))
                ->groupBy('venues.state')
                ->orderByDesc('tickets_count')->limit(10)->get();

            $eventIds = $this->artistEventIds();
            $orderIds = $this->artistOrderIds($eventIds);

            $coreStats = ['total_tickets' => 0, 'unique_buyers' => 0, 'total_revenue' => 0];
            if (!empty($orderIds)) {
                $cs = DB::table('orders as o')
                    ->join('tickets as t', 't.order_id', '=', 'o.id')
                    ->whereIn('o.id', $orderIds)
                    ->select(
                        DB::raw('COUNT(t.id) as total_tickets'),
                        DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as unique_buyers'),
                    )
                    ->first();
                $accurateRevenue = DB::table('orders')->whereIn('id', $orderIds)->sum('total');
                $coreStats = [
                    'total_tickets' => (int) ($cs->total_tickets ?? 0),
                    'unique_buyers' => (int) ($cs->unique_buyers ?? 0),
                    'total_revenue' => round((float) $accurateRevenue, 2),
                ];
            }

            // Use same variable names as original ViewArtist for blade compatibility
            $months = $this->seriesMonths;
            $events = $this->seriesEvents;
            $tickets = $this->seriesTickets;
            $revenue = $this->seriesRevenue;

            return array_merge(
                compact('kpis', 'months', 'events', 'tickets', 'revenue', 'from', 'to',
                    'artistEvents', 'artistVenues', 'artistTenants',
                    'topVenues', 'topCities', 'topCounties', 'coreStats'),
                [
                    'audiencePersonas' => $this->buildAudiencePersonas($orderIds),
                    'geoIntelligence' => $this->buildGeographicIntelligence($eventIds, $orderIds),
                    'performanceDeepDive' => $this->buildPerformanceDeepDive($eventIds, $orderIds),
                    'salesIntelligence' => $this->buildSalesIntelligence($eventIds, $orderIds),
                    'expansionPlanner' => $this->buildCityExpansionPlanner($eventIds, $orderIds),
                    'upcomingAnalysis' => $this->buildUpcomingEventsAnalysis($eventIds),
                    'opportunities' => $this->buildOpportunities($eventIds, $orderIds),
                    'fanEngagement' => $this->buildFanEngagementScore($orderIds),
                    'performanceHeatmap' => $this->buildPerformanceHeatmap($eventIds),
                ]
            );
            } catch (\Exception $e) {
                \Log::error('ArtistAnalytics: getViewData failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                return $emptyData;
            }
        });
    }

    // ─── EVENT ANALYZER ────────────────────────────────────────────────

    public function analyzeEvent(int $eventId): void
    {
        $this->selectedEventId = $eventId;
        $artistId = $this->record->id;

        $event = DB::table('events as e')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as capacity, SUM(quota_sold*price_cents)/100 as revenue FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('e.id', $eventId)
            ->select('e.*', 'v.name as venue_name', 'v.city as venue_city', 'v.capacity as venue_capacity', 'ts.sold', 'ts.capacity as ticket_capacity', 'ts.revenue')
            ->first();

        if (!$event) { $this->eventAnalysis = null; return; }

        $title = $event->title;
        if ($title && str_starts_with($title, '{')) { $d = json_decode($title, true); $title = $d['en'] ?? $d['ro'] ?? reset($d) ?: $title; }
        $venueName = $event->venue_name;
        if ($venueName && str_starts_with($venueName, '{')) { $d = json_decode($venueName, true); $venueName = $d['en'] ?? $d['ro'] ?? reset($d) ?: $venueName; }

        $sold = (int) ($event->sold ?? 0);
        $cap = (int) ($event->ticket_capacity ?? 0);
        $daysUntil = $event->event_date ? max(0, (int) now()->diffInDays(Carbon::parse($event->event_date), false)) : null;

        // Find comparable past events (same artist, similar capacity ±50%)
        $comparables = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as capacity, SUM(quota_sold*price_cents)/100 as revenue FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->where('e.id', '!=', $eventId)
            ->whereNotNull('e.event_date')
            ->where('e.event_date', '<', now()->toDateString())
            ->when($cap > 0, fn ($q) => $q->whereRaw('ts.capacity BETWEEN ? AND ?', [(int) floor($cap * 0.5), (int) ceil($cap * 1.5)]))
            ->select('e.id', 'e.title', 'e.event_date', 'v.name as venue_name', 'v.city', 'ts.sold', 'ts.capacity', 'ts.revenue')
            ->orderByDesc('e.event_date')
            ->limit(5)
            ->get()
            ->map(function ($c) {
                $t = $c->title;
                if ($t && str_starts_with($t, '{')) { $d = json_decode($t, true); $t = $d['en'] ?? $d['ro'] ?? reset($d) ?: $t; }
                $vn = $c->venue_name;
                if ($vn && str_starts_with($vn, '{')) { $d = json_decode($vn, true); $vn = $d['en'] ?? $d['ro'] ?? reset($d) ?: $vn; }
                $s = (int) $c->sold; $cp = (int) $c->capacity;
                return ['title' => mb_substr($t, 0, 40), 'date' => $c->event_date, 'venue' => $vn, 'city' => $c->city, 'sold' => $s, 'capacity' => $cp, 'sell_through' => $cp > 0 ? round($s / $cp * 100, 1) : null, 'revenue' => round((float) ($c->revenue ?? 0), 0)];
            })->toArray();

        // Prediction based on comparables
        $compSellThroughs = collect($comparables)->whereNotNull('sell_through')->pluck('sell_through');
        $prediction = [
            'min_sell_through' => $compSellThroughs->isNotEmpty() ? round($compSellThroughs->min(), 1) : null,
            'avg_sell_through' => $compSellThroughs->isNotEmpty() ? round($compSellThroughs->avg(), 1) : null,
            'max_sell_through' => $compSellThroughs->isNotEmpty() ? round($compSellThroughs->max(), 1) : null,
        ];
        if ($cap > 0 && $prediction['avg_sell_through'] !== null) {
            $prediction['predicted_sold'] = round($cap * $prediction['avg_sell_through'] / 100);
            $prediction['predicted_revenue'] = $comparables ? round(collect($comparables)->avg('revenue')) : null;
        }
        if ($daysUntil !== null && $daysUntil > 0 && $sold > 0) {
            $daysSelling = max(1, 90 - $daysUntil);
            $dailyRate = $sold / $daysSelling;
            $prediction['pace_forecast'] = min($cap ?: 99999, (int) round($sold + ($dailyRate * $daysUntil)));
        }

        $this->eventAnalysis = [
            'title' => $title, 'date' => $event->event_date,
            'venue' => $venueName, 'city' => $event->venue_city,
            'sold' => $sold, 'capacity' => $cap,
            'sell_through' => $cap > 0 ? round($sold / $cap * 100, 1) : null,
            'revenue' => round((float) ($event->revenue ?? 0), 0),
            'days_until' => $daysUntil,
            'comparables' => $comparables,
            'prediction' => $prediction,
        ];
    }

    public function updatedVenueSearch(): void
    {
        if (mb_strlen($this->venueSearch) < 2) { $this->venueResults = []; return; }
        $search = $this->venueSearch;
        // Case-insensitive, diacritics-tolerant search using ILIKE (PostgreSQL) or unaccent
        $this->venueResults = \App\Models\Venue::where(function ($q) use ($search) {
                $lower = mb_strtolower($search);
                if (DB::getDriverName() === 'pgsql') {
                    $q->whereRaw("LOWER(COALESCE((name::jsonb)->>'en', (name::jsonb)->>'ro', name::text)) ILIKE ?", ["%{$lower}%"])
                      ->orWhereRaw("LOWER(city) ILIKE ?", ["%{$lower}%"]);
                } else {
                    $q->where('name', 'LIKE', "%{$search}%")->orWhere('city', 'LIKE', "%{$search}%");
                }
            })
            ->select('id', 'name', 'city', 'capacity')->orderBy('name')->limit(15)->get()
            ->map(function ($v) {
                $name = is_array($v->name) ? ($v->name['en'] ?? $v->name['ro'] ?? reset($v->name) ?: '') : $v->name;
                if (is_string($name) && str_starts_with($name, '{')) { $d = json_decode($name, true); $name = $d['en'] ?? $d['ro'] ?? reset($d) ?: $name; }
                return ['id' => $v->id, 'label' => "{$name} ({$v->city})", 'name' => $name, 'city' => $v->city, 'capacity' => (int) ($v->capacity ?? 0)];
            })->toArray();
    }

    /**
     * Search venues - returns results directly to Alpine (no re-render).
     */
    public function searchVenuesApi(string $query): array
    {
        if (mb_strlen($query) < 2) return [];
        $lower = mb_strtolower($query);
        return \App\Models\Venue::where(function ($q) use ($query, $lower) {
                if (DB::getDriverName() === 'pgsql') {
                    $q->whereRaw("LOWER(name::text) ILIKE ?", ["%{$lower}%"])
                      ->orWhereRaw("LOWER(city) ILIKE ?", ["%{$lower}%"]);
                } else {
                    $q->where('name', 'LIKE', "%{$query}%")->orWhere('city', 'LIKE', "%{$query}%");
                }
            })
            ->select('id', 'name', 'city', 'capacity')->orderBy('name')->limit(15)->get()
            ->map(function ($v) {
                $name = is_array($v->name) ? ($v->name['en'] ?? $v->name['ro'] ?? reset($v->name) ?: '') : $v->name;
                if (is_string($name) && str_starts_with($name, '{')) { $d = json_decode($name, true); $name = $d['en'] ?? $d['ro'] ?? reset($d) ?: $name; }
                return ['id' => $v->id, 'label' => "{$name} ({$v->city})", 'name' => $name, 'city' => $v->city, 'capacity' => (int) ($v->capacity ?? 0)];
            })->toArray();
    }

    /**
     * Analyze event - returns analysis directly to Alpine (no re-render).
     */
    public function analyzeEventApi(int $eventId): ?array
    {
        $this->analyzeEvent($eventId);
        return $this->eventAnalysis;
    }

    /**
     * Analyze venue - returns analysis directly to Alpine (no re-render).
     */
    public function analyzeVenueApi(int $venueId): ?array
    {
        $this->analyzeVenue($venueId);
        return $this->venueAnalysis;
    }

    public function analyzeVenue(int $venueId): void
    {
        $this->selectedVenueId = $venueId;
        $artistId = $this->record->id;
        $venue = \App\Models\Venue::find($venueId);
        if (!$venue) { $this->venueAnalysis = null; return; }

        $venueName = is_array($venue->name) ? ($venue->name['en'] ?? $venue->name['ro'] ?? reset($venue->name) ?: '') : $venue->name;
        $cap = $venue->capacity ?: $venue->capacity_total ?: 0;

        $history = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as capacity, SUM(quota_sold*price_cents)/100 as revenue FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)->where('e.venue_id', $venueId)
            ->select('e.id', 'e.title', 'e.event_date', 'ts.sold', 'ts.capacity', 'ts.revenue')
            ->orderByDesc('e.event_date')->get()
            ->map(function ($e) {
                $t = $e->title; if ($t && str_starts_with($t, '{')) { $d = json_decode($t, true); $t = $d['en'] ?? $d['ro'] ?? reset($d) ?: $t; }
                $s = (int) ($e->sold ?? 0); $c = (int) ($e->capacity ?? 0);
                return ['title' => $t, 'date' => $e->event_date, 'sold' => $s, 'capacity' => $c, 'sell_through' => $c > 0 ? round($s / $c * 100, 1) : null, 'revenue' => round((float) ($e->revenue ?? 0), 0)];
            })->toArray();

        $hasHistory = !empty($history);
        $venueStats = DB::table('ticket_types as tt')->join('events as e', 'e.id', '=', 'tt.event_id')->where('e.venue_id', $venueId)->where('tt.quota_total', '>', 0)
            ->select(DB::raw('COUNT(DISTINCT e.id) as total_events'), DB::raw('AVG(LEAST(tt.quota_sold*1.0/tt.quota_total,1.0)) as avg_fill_rate'))->first();

        $forecast = null;
        if (!$hasHistory) {
            $eventIds = $this->artistEventIds(); $orderIds = $this->artistOrderIds($eventIds);
            $fansInCity = 0;
            if (!empty($orderIds) && $venue->city) {
                $fansInCity = (int) DB::table('orders as o')->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
                    ->whereIn('o.id', $orderIds)->where(DB::raw('COALESCE(mc.city, c.city)'), $venue->city)->count(DB::raw('DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)'));
            }
            $genreIds = DB::table('artist_artist_genre')->where('artist_id', $artistId)->pluck('artist_genre_id')->toArray();
            $similarAtVenue = null;
            if (!empty($genreIds)) {
                $simIds = DB::table('artist_artist_genre')->whereIn('artist_genre_id', $genreIds)->where('artist_id', '!=', $artistId)->pluck('artist_id')->unique()->take(50)->toArray();
                if (!empty($simIds)) { $similarAtVenue = DB::table('events as e')->join('event_artist as ea', 'ea.event_id', '=', 'e.id')->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')->where('e.venue_id', $venueId)->whereIn('ea.artist_id', $simIds)->select(DB::raw('COUNT(DISTINCT e.id) as events'), DB::raw('AVG(ts.sold) as avg_sold'), DB::raw('AVG(CASE WHEN ts.cap>0 THEN LEAST(ts.sold*1.0/ts.cap,1.0) ELSE NULL END) as avg_st'))->first(); }
            }
            $estimatedDemand = $fansInCity * 3;
            $forecast = ['fans_in_city' => $fansInCity, 'estimated_demand' => $estimatedDemand, 'similar_events' => (int) ($similarAtVenue?->events ?? 0), 'similar_avg_sold' => round((float) ($similarAtVenue?->avg_sold ?? 0)), 'similar_avg_st' => round((float) ($similarAtVenue?->avg_st ?? 0) * 100, 1), 'capacity_utilization' => $cap > 0 ? round(min($estimatedDemand / $cap * 100, 100), 1) : 0];
        }

        $this->venueAnalysis = ['venue_name' => $venueName, 'city' => $venue->city, 'capacity' => $cap, 'has_history' => $hasHistory, 'history' => $history, 'history_avg_st' => $hasHistory ? round(collect($history)->whereNotNull('sell_through')->avg('sell_through'), 1) : null, 'history_avg_revenue' => $hasHistory ? round(collect($history)->avg('revenue')) : null, 'venue_total_events' => (int) ($venueStats?->total_events ?? 0), 'venue_avg_fill' => round((float) ($venueStats?->avg_fill_rate ?? 0) * 100, 1), 'forecast' => $forecast];
    }

}
