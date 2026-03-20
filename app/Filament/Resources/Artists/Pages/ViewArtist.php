<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use App\Models\Artist;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ViewArtist extends Page
{
    protected static string $resource = ArtistResource::class;

    protected string $view = 'filament.artists.pages.view-artist';

    public Artist $record;

    /**
     * Get all event IDs linked to this artist via event_artist pivot.
     */
    private function artistEventIds(): array
    {
        return DB::table('event_artist')
            ->where('artist_id', $this->record->id)
            ->pluck('event_id')
            ->toArray();
    }

    /**
     * Get all order IDs for this artist's events through ALL ticket paths:
     * 1. ticket.ticket_type_id → ticket_types.event_id (web orders)
     * 2. ticket.event_id directly (POS/app orders)
     * 3. ticket.marketplace_event_id (marketplace app orders)
     */
    private function artistOrderIds(array $eventIds, array $paidStatuses = ['paid', 'confirmed', 'completed']): array
    {
        if (empty($eventIds)) return [];

        return DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where(function ($q) use ($eventIds) {
                $q->whereIn('tt.event_id', $eventIds)          // Path 1: via ticket_type
                    ->orWhereIn('t.event_id', $eventIds)       // Path 2: direct event_id
                    ->orWhereIn('t.marketplace_event_id', $eventIds); // Path 3: marketplace
            })
            ->whereIn('o.status', $paidStatuses)
            ->distinct()
            ->pluck('o.id')
            ->toArray();
    }

    // ─── Livewire properties for interactive tools ─────────────────
    public ?int $selectedEventId = null;
    public ?array $eventAnalysis = null;
    public ?int $selectedVenueId = null;
    public ?array $venueAnalysis = null;
    public string $venueSearch = '';

    public function getViewData(): array
    {
        $cacheKey = "artist_full_v3_{$this->record->id}";
        if (request()->has('refresh_analytics')) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 300, function () {
            $from = now()->subDays(365)->startOfDay();
            $to = now()->endOfDay();

            $kpis = $this->record->computeKpis($from, $to);
            [$months, $events, $tickets, $revenue] = $this->buildYearlySeriesOptimized();

            $artistEvents = $this->record->events()
                ->with(['venue', 'tenant'])
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
                ->select('venues.city as name', DB::raw('COUNT(tickets.id) as tickets_count'))
                ->groupBy('venues.city')
                ->orderByDesc('tickets_count')->limit(10)->get();

            $topCounties = $ticketBase()->whereNotNull('venues.state')
                ->select('venues.state as name', DB::raw('COUNT(tickets.id) as tickets_count'))
                ->groupBy('venues.state')
                ->orderByDesc('tickets_count')->limit(10)->get();

            // Analytics 360
            $eventIds = $this->artistEventIds();
            $paidStatuses = ['paid', 'confirmed', 'completed'];
            $orderIds = $this->artistOrderIds($eventIds, $paidStatuses);

            return array_merge(
                compact('kpis', 'months', 'events', 'tickets', 'revenue', 'from', 'to',
                    'artistEvents', 'artistVenues', 'artistTenants',
                    'topVenues', 'topCities', 'topCounties'),
                [
                    'audiencePersonas' => $this->buildAudiencePersonas($orderIds),
                    'geoIntelligence' => $this->buildGeographicIntelligence($orderIds),
                    'performanceDeepDive' => $this->buildPerformanceDeepDive($eventIds, $orderIds),
                    'salesIntelligence' => $this->buildSalesIntelligence($eventIds, $orderIds),
                    'expansionPlanner' => $this->buildCityExpansionPlanner($eventIds, $orderIds),
                    'upcomingAnalysis' => $this->buildUpcomingEventsAnalysis($eventIds),
                ]
            );
        });
    }

    // ─── EVENT ANALYZER (Livewire action) ────────────────────────────

    public function analyzeEvent(int $eventId): void
    {
        $this->selectedEventId = $eventId;
        $artistId = $this->record->id;

        // Current event data
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
            ->when($cap > 0, fn ($q) => $q->whereRaw('ts.capacity BETWEEN ? AND ?', [$cap * 0.5, $cap * 1.5]))
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
                return [
                    'title' => mb_substr($t, 0, 40), 'date' => $c->event_date, 'venue' => $vn, 'city' => $c->city,
                    'sold' => $s, 'capacity' => $cp,
                    'sell_through' => $cp > 0 ? round($s / $cp * 100, 1) : null,
                    'revenue' => round((float) ($c->revenue ?? 0), 0),
                ];
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

        // Current pace projection
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

    // ─── VENUE FORECAST (Livewire action) ────────────────────────────

    public array $venueResults = [];

    public function updatedVenueSearch(): void
    {
        if (mb_strlen($this->venueSearch) < 2) {
            $this->venueResults = [];
            return;
        }
        $this->venueResults = \App\Models\Venue::where('name', 'LIKE', "%{$this->venueSearch}%")
            ->select('id', 'name', 'city', 'capacity', 'capacity_total')
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(function ($v) {
                $name = $v->getTranslation('name', 'en') ?: $v->getTranslation('name', 'ro') ?: $v->name;
                $cap = $v->capacity ?: $v->capacity_total ?: 0;
                return ['id' => $v->id, 'label' => "{$name} ({$v->city}, cap {$cap})", 'name' => $name, 'city' => $v->city, 'capacity' => (int) $cap];
            })->toArray();
    }

    public function analyzeVenue(int $venueId): void
    {
        $this->selectedVenueId = $venueId;
        $artistId = $this->record->id;
        $venue = \App\Models\Venue::find($venueId);
        if (!$venue) { $this->venueAnalysis = null; return; }

        $venueName = $venue->getTranslation('name', 'en') ?: $venue->getTranslation('name', 'ro') ?: $venue->name;
        $cap = $venue->capacity ?: $venue->capacity_total ?: 0;

        // Check artist history at this venue
        $history = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as capacity, SUM(quota_sold*price_cents)/100 as revenue FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->where('e.venue_id', $venueId)
            ->select('e.id', 'e.title', 'e.event_date', 'ts.sold', 'ts.capacity', 'ts.revenue')
            ->orderByDesc('e.event_date')
            ->get()
            ->map(function ($e) {
                $t = $e->title;
                if ($t && str_starts_with($t, '{')) { $d = json_decode($t, true); $t = $d['en'] ?? $d['ro'] ?? reset($d) ?: $t; }
                $s = (int) ($e->sold ?? 0); $c = (int) ($e->capacity ?? 0);
                return ['title' => $t, 'date' => $e->event_date, 'sold' => $s, 'capacity' => $c, 'sell_through' => $c > 0 ? round($s / $c * 100, 1) : null, 'revenue' => round((float) ($e->revenue ?? 0), 0)];
            })->toArray();

        $hasHistory = !empty($history);

        // Venue general stats (all artists)
        $venueStats = DB::table('ticket_types as tt')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->where('e.venue_id', $venueId)
            ->where('tt.quota_total', '>', 0)
            ->select(DB::raw('COUNT(DISTINCT e.id) as total_events'), DB::raw('AVG(LEAST(tt.quota_sold*1.0/tt.quota_total,1.0)) as avg_fill_rate'), DB::raw('AVG(tt.quota_sold) as avg_sold'))
            ->first();

        $forecast = null;
        if (!$hasHistory) {
            // Fans in venue's city
            $eventIds = $this->artistEventIds();
            $orderIds = $this->artistOrderIds($eventIds);
            $fansInCity = 0;
            if (!empty($orderIds) && $venue->city) {
                $fansInCity = (int) DB::table('orders as o')
                    ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
                    ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
                    ->whereIn('o.id', $orderIds)
                    ->where(DB::raw('COALESCE(mc.city, c.city)'), $venue->city)
                    ->count(DB::raw('DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)'));
            }

            // Similar artists at this venue
            $genreIds = DB::table('artist_artist_genre')->where('artist_id', $artistId)->pluck('artist_genre_id')->toArray();
            $similarAtVenue = null;
            if (!empty($genreIds)) {
                $simIds = DB::table('artist_artist_genre')->whereIn('artist_genre_id', $genreIds)->where('artist_id', '!=', $artistId)->pluck('artist_id')->unique()->take(50)->toArray();
                if (!empty($simIds)) {
                    $similarAtVenue = DB::table('events as e')
                        ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
                        ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
                        ->where('e.venue_id', $venueId)
                        ->whereIn('ea.artist_id', $simIds)
                        ->select(DB::raw('COUNT(DISTINCT e.id) as events'), DB::raw('AVG(ts.sold) as avg_sold'), DB::raw('AVG(CASE WHEN ts.cap>0 THEN LEAST(ts.sold*1.0/ts.cap,1.0) ELSE NULL END) as avg_st'))
                        ->first();
                }
            }

            $estimatedDemand = $fansInCity * 3;
            $forecast = [
                'fans_in_city' => $fansInCity,
                'estimated_demand' => $estimatedDemand,
                'similar_events' => (int) ($similarAtVenue?->events ?? 0),
                'similar_avg_sold' => round((float) ($similarAtVenue?->avg_sold ?? 0)),
                'similar_avg_st' => round((float) ($similarAtVenue?->avg_st ?? 0) * 100, 1),
                'capacity_utilization' => $cap > 0 ? round(min($estimatedDemand / $cap * 100, 100), 1) : 0,
            ];
        }

        $this->venueAnalysis = [
            'venue_name' => $venueName, 'city' => $venue->city, 'capacity' => $cap,
            'has_history' => $hasHistory,
            'history' => $history,
            'history_avg_st' => $hasHistory ? round(collect($history)->whereNotNull('sell_through')->avg('sell_through'), 1) : null,
            'history_avg_revenue' => $hasHistory ? round(collect($history)->avg('revenue')) : null,
            'venue_total_events' => (int) ($venueStats?->total_events ?? 0),
            'venue_avg_fill' => round((float) ($venueStats?->avg_fill_rate ?? 0) * 100, 1),
            'forecast' => $forecast,
        ];
    }

    // ─── OPTIMIZED YEARLY SERIES (3 queries instead of 36) ─────────

    private function buildYearlySeriesOptimized(): array
    {
        $artistId = $this->record->id;
        $startDate = now()->startOfMonth()->subMonths(11)->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        // 1. Events per month (1 query)
        $eventsRaw = DB::table('events')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->where('event_artist.artist_id', $artistId)
            ->whereBetween('events.event_date', [$startDate, $endDate])
            ->select(DB::raw("DATE_FORMAT(events.event_date, '%Y-%m') as ym"), DB::raw('COUNT(*) as cnt'))
            ->groupBy('ym')
            ->pluck('cnt', 'ym');

        // 2. Tickets + revenue per month (1 query)
        $ticketsRaw = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->where('event_artist.artist_id', $artistId)
            ->whereBetween('events.event_date', [$startDate, $endDate])
            ->select(
                DB::raw("DATE_FORMAT(events.event_date, '%Y-%m') as ym"),
                DB::raw('COUNT(tickets.id) as ticket_count'),
                DB::raw('COALESCE(SUM(tickets.price), 0) as revenue')
            )
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        // Build arrays
        $months = []; $events = []; $tickets = []; $revenue = [];
        $start = now()->startOfMonth()->subMonths(11);
        for ($i = 0; $i < 12; $i++) {
            $d = (clone $start)->addMonths($i);
            $ym = $d->format('Y-m');
            $months[] = $d->format('M Y');
            $events[] = (int) ($eventsRaw[$ym] ?? 0);
            $tickets[] = (int) ($ticketsRaw[$ym]?->ticket_count ?? 0);
            $revenue[] = round((float) ($ticketsRaw[$ym]?->revenue ?? 0), 2);
        }

        return [$months, $events, $tickets, $revenue];
    }

    // ─── AUDIENCE DNA ────────────────────────────────────────────────

    private function buildAudiencePersonas(array $orderIds): array
    {
        if (empty($orderIds)) {
            return ['personas' => [], 'totals' => ['total_customers' => 0, 'with_demographics' => 0, 'age_distribution' => [], 'gender_overall' => []]];
        }

        $buyers = DB::table('orders as o')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereIn('o.id', $orderIds)
            ->select(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'),
                DB::raw('COALESCE(mc.birth_date, c.date_of_birth) as birth_date'),
                'mc.gender',
                DB::raw('COALESCE(mc.city, c.city) as city'),
                DB::raw('COALESCE(mc.country, c.country) as country'),
                DB::raw('SUM(o.total) as total_spent'),
                DB::raw('COUNT(DISTINCT o.id) as order_count')
            )
            ->groupBy(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'),
                DB::raw('COALESCE(mc.birth_date, c.date_of_birth)'),
                'mc.gender',
                DB::raw('COALESCE(mc.city, c.city)'),
                DB::raw('COALESCE(mc.country, c.country)')
            )
            ->get();

        if ($buyers->isEmpty()) {
            return ['personas' => [], 'totals' => ['total_customers' => 0, 'with_demographics' => 0, 'age_distribution' => [], 'gender_overall' => []]];
        }

        $totalCustomers = $buyers->count();

        $withAge = $buyers->map(function ($b) {
            $age = null;
            if ($b->birth_date) {
                try { $age = Carbon::parse($b->birth_date)->age; } catch (\Exception $e) {}
            }
            $ageGroup = match (true) {
                $age === null => 'unknown',
                $age < 18 => '<18',
                $age <= 24 => '18-24',
                $age <= 34 => '25-34',
                $age <= 44 => '35-44',
                $age <= 54 => '45-54',
                default => '55+',
            };
            return (object) [
                'buyer_id' => $b->buyer_id, 'age_group' => $ageGroup,
                'gender' => $b->gender ?: 'unknown', 'city' => $b->city,
                'total_spent' => (float) $b->total_spent, 'order_count' => (int) $b->order_count,
            ];
        });

        $ageDistribution = $withAge->where('age_group', '!=', 'unknown')->countBy('age_group')->sortKeys()->toArray();
        $genderOverall = $withAge->where('gender', '!=', 'unknown')->countBy('gender')->toArray();

        // Top cities overall
        $topCitiesAudience = $withAge->whereNotNull('city')->countBy('city')->sortDesc()->take(10)->toArray();

        $clusters = $withAge->where('age_group', '!=', 'unknown')
            ->groupBy(fn ($b) => $b->age_group . '_' . $b->gender);

        $personas = $clusters->map(function ($group, $key) use ($totalCustomers) {
            [$ageGroup, $gender] = array_pad(explode('_', $key, 2), 2, 'unknown');
            $topCities = $group->whereNotNull('city')->countBy('city')->sortDesc()->take(3)->toArray();
            return [
                'age_group' => $ageGroup, 'gender' => $gender,
                'count' => $group->count(),
                'percentage' => round($group->count() / $totalCustomers * 100, 1),
                'avg_spend' => round($group->avg('total_spent'), 2),
                'avg_orders' => round($group->avg('order_count'), 1),
                'top_cities' => $topCities,
            ];
        })->sortByDesc('count')->values()->take(3)->toArray();

        $labels = ['Primary Persona', 'Secondary Persona', 'Tertiary Persona'];
        foreach ($personas as $i => &$p) { $p['label'] = $labels[$i] ?? 'Other'; }

        return [
            'personas' => $personas,
            'totals' => [
                'total_customers' => $totalCustomers,
                'with_demographics' => $withAge->where('age_group', '!=', 'unknown')->count(),
                'age_distribution' => $ageDistribution,
                'gender_overall' => $genderOverall,
                'top_cities' => $topCitiesAudience,
            ],
        ];
    }

    // ─── GEOGRAPHIC INTELLIGENCE ─────────────────────────────────────

    private function buildGeographicIntelligence(array $orderIds): array
    {
        if (empty($orderIds)) return [];

        $fansByCity = DB::table('orders as o')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereIn('o.id', $orderIds)
            ->whereNotNull(DB::raw('COALESCE(mc.city, c.city)'))
            ->select(
                DB::raw('COALESCE(mc.city, c.city) as city'),
                DB::raw('COALESCE(mc.country, c.country) as country'),
                DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as fans_count'),
                DB::raw('SUM(o.total) as total_revenue'),
                DB::raw('COUNT(DISTINCT o.id) as orders_count')
            )
            ->groupBy(DB::raw('COALESCE(mc.city, c.city)'), DB::raw('COALESCE(mc.country, c.country)'))
            ->orderByDesc('fans_count')
            ->limit(20)
            ->get();

        if ($fansByCity->isEmpty()) return [];

        $cities = $fansByCity->pluck('city')->filter()->unique()->values()->toArray();

        $favoritesByCity = DB::table('marketplace_customer_favorites as mcf')
            ->join('marketplace_customers as mc', 'mc.id', '=', 'mcf.marketplace_customer_id')
            ->where('mcf.favoriteable_id', $this->record->id)
            ->where('mcf.favoriteable_type', 'LIKE', '%Artist%')
            ->whereNotNull('mc.city')
            ->select('mc.city', DB::raw('COUNT(*) as fav_count'))
            ->groupBy('mc.city')
            ->get()
            ->keyBy('city');

        $venuesByCity = DB::table('venues')
            ->whereIn('city', $cities)
            ->select('id', 'name', 'city', 'capacity', 'capacity_total', 'capacity_standing', 'capacity_seated')
            ->orderBy('city')->orderByDesc('capacity')
            ->get()->groupBy('city');

        $venueIds = $venuesByCity->flatten()->pluck('id')->toArray();
        $fillRates = collect();
        if (!empty($venueIds)) {
            $fillRates = DB::table('ticket_types as tt')
                ->join('events as e', 'e.id', '=', 'tt.event_id')
                ->whereIn('e.venue_id', $venueIds)
                ->where('tt.quota_total', '>', 0)
                ->select('e.venue_id', DB::raw('COUNT(DISTINCT e.id) as events_count'), DB::raw('AVG(LEAST(tt.quota_sold * 1.0 / tt.quota_total, 1.0)) as avg_fill_rate'))
                ->groupBy('e.venue_id')
                ->get()->keyBy('venue_id');
        }

        return $fansByCity->map(function ($row) use ($favoritesByCity, $venuesByCity, $fillRates) {
            $city = $row->city;
            $favCount = $favoritesByCity->get($city)?->fav_count ?? 0;

            $venues = ($venuesByCity->get($city) ?? collect())->take(3)->map(function ($v) use ($fillRates) {
                $cap = $v->capacity ?: $v->capacity_total ?: ($v->capacity_standing + $v->capacity_seated);
                $name = $v->name;
                if ($name && str_starts_with($name, '{')) {
                    $d = json_decode($name, true); $name = $d['en'] ?? $d['ro'] ?? reset($d) ?: $name;
                }
                return ['name' => $name, 'capacity' => (int) $cap, 'fill_rate' => round(($fillRates->get($v->id)?->avg_fill_rate ?? 0) * 100, 1)];
            })->values()->toArray();

            $recommended = collect($venues)->filter(fn ($v) => $v['capacity'] > 0)->sortBy(fn ($v) => abs($v['capacity'] - ($row->fans_count + $favCount * 2)))->first();

            return [
                'city' => $city, 'country' => $row->country,
                'fans_count' => (int) $row->fans_count, 'favorites_count' => (int) $favCount,
                'total_revenue' => round((float) $row->total_revenue, 2),
                'potential_buyers' => $row->fans_count + ($favCount * 2),
                'venues' => $venues,
                'recommended_venue' => $recommended['name'] ?? null,
                'recommended_capacity' => $recommended['capacity'] ?? null,
            ];
        })->toArray();
    }

    // ─── PERFORMANCE DEEP-DIVE ───────────────────────────────────────

    private function buildPerformanceDeepDive(array $eventIds, array $orderIds): array
    {
        if (empty($eventIds)) return ['events' => [], 'avg_sell_through' => 0, 'avg_checkin_rate' => 0, 'role_comparison' => [], 'customer_loyalty' => ['one_time' => 0, 'repeat' => 0, 'superfan' => 0, 'repeat_rate' => 0, 'total' => 0]];

        $eventsPerf = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as capacity FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $this->record->id)
            ->select('e.id', 'e.title', 'e.event_date', 'v.name as venue_name', 'v.city as venue_city', 'ea.is_headliner', 'ea.is_co_headliner', 'ts.sold', 'ts.capacity')
            ->orderByDesc('e.event_date')
            ->get()
            ->map(function ($e) {
                $title = $e->title;
                if ($title && str_starts_with($title, '{')) { $d = json_decode($title, true); $title = $d['en'] ?? $d['ro'] ?? reset($d) ?: $title; }
                $vn = $e->venue_name;
                if ($vn && str_starts_with($vn, '{')) { $d = json_decode($vn, true); $vn = $d['en'] ?? $d['ro'] ?? reset($d) ?: $vn; }
                $sold = (int) $e->sold; $cap = (int) $e->capacity;
                return [
                    'id' => $e->id, 'title' => $title, 'date' => $e->event_date,
                    'venue' => $vn, 'city' => $e->venue_city,
                    'is_headliner' => (bool) $e->is_headliner, 'is_co_headliner' => (bool) $e->is_co_headliner,
                    'sold' => $sold, 'capacity' => $cap,
                    'sell_through' => $cap > 0 ? round($sold / $cap * 100, 1) : null,
                    'checkin_rate' => null, 'checkin_total' => 0, 'checkin_count' => 0,
                ];
            });

        // Check-in rates
        $checkinRates = DB::table('tickets as t')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where('t.status', 'valid')
            ->where(function ($q) use ($eventIds) {
                $q->whereIn('tt.event_id', $eventIds)
                    ->orWhereIn('t.event_id', $eventIds)
                    ->orWhereIn('t.marketplace_event_id', $eventIds);
            })
            ->select(
                DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id) as resolved_event_id'),
                DB::raw('COUNT(t.id) as total_tickets'),
                DB::raw('SUM(CASE WHEN t.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) as checked_in')
            )
            ->groupBy(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'))
            ->get()->keyBy('resolved_event_id');

        $eventsPerf = $eventsPerf->map(function ($e) use ($checkinRates) {
            $ci = $checkinRates->get($e['id']);
            $e['checkin_total'] = (int) ($ci?->total_tickets ?? 0);
            $e['checkin_count'] = (int) ($ci?->checked_in ?? 0);
            $e['checkin_rate'] = $e['checkin_total'] > 0 ? round($e['checkin_count'] / $e['checkin_total'] * 100, 1) : null;
            return $e;
        })->toArray();

        // Role comparison
        $roleStats = collect($eventsPerf)->groupBy(fn ($e) => $e['is_headliner'] ? 'Headliner' : ($e['is_co_headliner'] ? 'Co-Headliner' : 'Support'))
            ->map(fn ($group) => [
                'events' => $group->count(),
                'avg_sold' => round($group->avg('sold'), 0),
                'avg_sell_through' => round($group->whereNotNull('sell_through')->avg('sell_through'), 1),
                'avg_checkin_rate' => round($group->whereNotNull('checkin_rate')->avg('checkin_rate'), 1),
            ])->toArray();

        // Repeat customers (using pre-computed orderIds)
        $customerEvents = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->whereIn('o.id', $orderIds)
            ->select(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'),
                DB::raw('COUNT(DISTINCT COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)) as events_attended')
            )
            ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'))
            ->get();

        $loyalty = [
            'one_time' => $customerEvents->where('events_attended', 1)->count(),
            'repeat' => $customerEvents->where('events_attended', 2)->count(),
            'superfan' => $customerEvents->where('events_attended', '>=', 3)->count(),
        ];
        $totalBuyers = array_sum($loyalty);
        $loyalty['repeat_rate'] = $totalBuyers > 0 ? round(($loyalty['repeat'] + $loyalty['superfan']) / $totalBuyers * 100, 1) : 0;
        $loyalty['total'] = $totalBuyers;

        $allWithSt = collect($eventsPerf)->whereNotNull('sell_through');
        $allWithCi = collect($eventsPerf)->whereNotNull('checkin_rate');

        return [
            'events' => $eventsPerf,
            'avg_sell_through' => round($allWithSt->avg('sell_through'), 1),
            'avg_checkin_rate' => round($allWithCi->avg('checkin_rate'), 1),
            'role_comparison' => $roleStats,
            'customer_loyalty' => $loyalty,
        ];
    }

    // ─── SALES INTELLIGENCE ──────────────────────────────────────────

    private function buildSalesIntelligence(array $eventIds, array $orderIds): array
    {
        if (empty($orderIds)) return ['channels' => [], 'purchase_timing' => [], 'avg_lead_days' => 0, 'price_sensitivity' => [], 'velocity_curves' => []];

        // Channel breakdown
        $channels = DB::table('orders as o')
            ->whereIn('o.id', $orderIds)
            ->select('o.source', DB::raw('COUNT(DISTINCT o.id) as orders_count'), DB::raw('SUM(o.total) as revenue'), DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as unique_customers'))
            ->groupBy('o.source')
            ->get()->keyBy('source')->toArray();

        $totalChannelOrders = collect($channels)->sum('orders_count');
        foreach ($channels as &$ch) { $ch->pct = $totalChannelOrders > 0 ? round($ch->orders_count / $totalChannelOrders * 100, 1) : 0; }

        // Purchase lead time
        $leadTimes = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->leftJoin('events as e', 'e.id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'))
            ->whereIn('o.id', $orderIds)
            ->whereNotNull('o.paid_at')->whereNotNull('e.event_date')
            ->select(DB::raw('DATEDIFF(e.event_date, o.paid_at) as days_before'))
            ->get()->pluck('days_before');

        $totalLead = $leadTimes->count();
        $purchaseTiming = [
            'last_minute' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 0 && $d <= 1)->count() / $totalLead * 100, 1) : 0,
            'last_week' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 2 && $d <= 7)->count() / $totalLead * 100, 1) : 0,
            'last_month' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 8 && $d <= 30)->count() / $totalLead * 100, 1) : 0,
            'early_bird' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 31 && $d <= 90)->count() / $totalLead * 100, 1) : 0,
            'super_early' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d > 90)->count() / $totalLead * 100, 1) : 0,
        ];

        // Price sensitivity
        $priceData = DB::table('ticket_types as tt')
            ->whereIn('tt.event_id', $eventIds)
            ->where('tt.quota_total', '>', 0)
            ->select('tt.price_cents', 'tt.quota_sold', 'tt.quota_total')
            ->get();

        $priceBuckets = ['0-50' => ['t' => 0, 'c' => 0], '50-100' => ['t' => 0, 'c' => 0], '100-200' => ['t' => 0, 'c' => 0], '200-500' => ['t' => 0, 'c' => 0], '500+' => ['t' => 0, 'c' => 0]];
        foreach ($priceData as $p) {
            $price = $p->price_cents / 100;
            $bucket = match (true) { $price < 50 => '0-50', $price < 100 => '50-100', $price < 200 => '100-200', $price < 500 => '200-500', default => '500+' };
            $priceBuckets[$bucket]['t'] += $p->quota_sold;
            $priceBuckets[$bucket]['c'] += $p->quota_total;
        }
        $priceSensitivity = [];
        foreach ($priceBuckets as $range => $d) {
            if ($d['c'] > 0) $priceSensitivity[] = ['range' => $range, 'tickets' => $d['t'], 'sell_through' => round($d['t'] / $d['c'] * 100, 1)];
        }

        // Sales velocity for last 5 events
        $lastEventIds = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->where('ea.artist_id', $this->record->id)
            ->whereNotNull('e.event_date')
            ->where('e.event_date', '<', now())
            ->orderByDesc('e.event_date')
            ->limit(5)
            ->pluck('e.id')->toArray();

        $velocityCurves = [];
        if (!empty($lastEventIds)) {
            $vData = DB::table('orders as o')
                ->join('tickets as t', 't.order_id', '=', 'o.id')
                ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->join('events as e', 'e.id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'))
                ->whereIn('e.id', $lastEventIds)
                ->whereIn('o.status', ['paid', 'confirmed', 'completed'])
                ->whereNotNull('o.paid_at')
                ->select('e.id', 'e.title', 'e.event_date', 'o.paid_at', DB::raw('COUNT(t.id) as ticket_count'))
                ->groupBy('e.id', 'e.title', 'e.event_date', 'o.paid_at')
                ->orderBy('o.paid_at')
                ->get()->groupBy('id');

            foreach ($vData as $eventId => $rows) {
                $title = $rows->first()->title;
                if ($title && str_starts_with($title, '{')) { $d = json_decode($title, true); $title = $d['en'] ?? $d['ro'] ?? reset($d) ?: $title; }
                $eventDate = Carbon::parse($rows->first()->event_date);
                $total = $rows->sum('ticket_count');
                $cum = 0; $points = [];
                foreach ($rows as $r) {
                    $days = max(0, (int) $eventDate->diffInDays(Carbon::parse($r->paid_at), false));
                    $cum += $r->ticket_count;
                    $points[] = ['days' => $days, 'pct' => $total > 0 ? round($cum / $total * 100, 1) : 0];
                }
                $velocityCurves[] = ['event_name' => mb_substr($title, 0, 30), 'total_tickets' => $total, 'points' => $points];
            }
        }

        return ['channels' => $channels, 'purchase_timing' => $purchaseTiming, 'avg_lead_days' => round($leadTimes->filter(fn ($d) => $d >= 0)->avg() ?? 0, 1), 'price_sensitivity' => $priceSensitivity, 'velocity_curves' => $velocityCurves];
    }

    // ─── CITY EXPANSION PLANNER ──────────────────────────────────────

    private function buildCityExpansionPlanner(array $eventIds, array $orderIds): array
    {
        if (empty($orderIds)) return [];

        $performedCities = DB::table('events as e')
            ->join('venues as v', 'v.id', '=', 'e.venue_id')
            ->whereIn('e.id', $eventIds)
            ->whereNotNull('v.city')
            ->pluck('v.city')->unique()->values()->toArray();

        $query = DB::table('orders as o')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereIn('o.id', $orderIds)
            ->whereNotNull(DB::raw('COALESCE(mc.city, c.city)'));

        if (!empty($performedCities)) {
            $query->whereNotIn(DB::raw('COALESCE(mc.city, c.city)'), $performedCities);
        }

        $expansionCities = $query
            ->select(DB::raw('COALESCE(mc.city, c.city) as city'), DB::raw('COALESCE(mc.country, c.country) as country'), DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as fan_count'))
            ->groupBy(DB::raw('COALESCE(mc.city, c.city)'), DB::raw('COALESCE(mc.country, c.country)'))
            ->havingRaw('fan_count >= 2')
            ->orderByDesc('fan_count')
            ->limit(20)
            ->get();

        if ($expansionCities->isEmpty()) return [];

        $expCityNames = $expansionCities->pluck('city')->toArray();

        $venuesInCities = DB::table('venues')
            ->whereIn('city', $expCityNames)
            ->select('id', 'name', 'city', 'capacity', 'capacity_total')
            ->orderByDesc('capacity')
            ->get()->groupBy('city');

        // Similar artists
        $genreIds = DB::table('artist_artist_genre')->where('artist_id', $this->record->id)->pluck('artist_genre_id')->toArray();
        $similarArtistData = collect();
        if (!empty($genreIds)) {
            $similarIds = DB::table('artist_artist_genre')
                ->whereIn('artist_genre_id', $genreIds)
                ->where('artist_id', '!=', $this->record->id)
                ->pluck('artist_id')->unique()->take(50)->toArray();

            if (!empty($similarIds)) {
                $similarArtistData = DB::table('events as e')
                    ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
                    ->join('venues as v', 'v.id', '=', 'e.venue_id')
                    ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as cap FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
                    ->whereIn('ea.artist_id', $similarIds)
                    ->whereIn('v.city', $expCityNames)
                    ->select('v.city', DB::raw('COUNT(DISTINCT e.id) as events_count'), DB::raw('AVG(ts.sold) as avg_attendance'), DB::raw('AVG(CASE WHEN ts.cap > 0 THEN LEAST(ts.sold * 1.0 / ts.cap, 1.0) ELSE NULL END) as avg_sell_through'))
                    ->groupBy('v.city')
                    ->get()->keyBy('city');
            }
        }

        return $expansionCities->map(function ($row) use ($venuesInCities, $similarArtistData) {
            $venues = ($venuesInCities->get($row->city) ?? collect())->take(3)->map(function ($v) {
                $name = $v->name;
                if ($name && str_starts_with($name, '{')) { $d = json_decode($name, true); $name = $d['en'] ?? $d['ro'] ?? reset($d) ?: $name; }
                return ['name' => $name, 'capacity' => (int) ($v->capacity ?: $v->capacity_total ?: 0)];
            })->toArray();
            $sim = $similarArtistData->get($row->city);
            $confidence = 'low';
            if ($row->fan_count >= 10 && ($sim?->events_count ?? 0) >= 3) $confidence = 'high';
            elseif ($row->fan_count >= 5 || ($sim?->events_count ?? 0) >= 2) $confidence = 'medium';

            return [
                'city' => $row->city, 'country' => $row->country,
                'fan_count' => (int) $row->fan_count, 'estimated_demand' => $row->fan_count * 3,
                'venues' => $venues,
                'similar_events' => (int) ($sim?->events_count ?? 0),
                'similar_avg_attendance' => round((float) ($sim?->avg_attendance ?? 0)),
                'similar_sell_through' => round((float) ($sim?->avg_sell_through ?? 0) * 100, 1),
                'confidence' => $confidence,
            ];
        })->toArray();
    }

    // ─── UPCOMING EVENTS ANALYSIS ────────────────────────────────────

    private function buildUpcomingEventsAnalysis(array $eventIds): array
    {
        if (empty($eventIds)) return [];

        $upcoming = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as capacity, SUM(tt2.quota_sold * tt2.price_cents) / 100 as revenue_sold FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $this->record->id)
            ->where(function ($q) {
                $q->where('e.event_date', '>=', now()->toDateString())
                    ->orWhere('e.range_end_date', '>=', now()->toDateString())
                    ->orWhere('e.range_start_date', '>=', now()->toDateString());
            })
            ->select('e.id', 'e.title', 'e.event_date', 'e.range_start_date', 'v.name as venue_name', 'v.city as venue_city', 'v.capacity as venue_capacity', 'ea.is_headliner', 'ts.sold', 'ts.capacity', 'ts.revenue_sold')
            ->orderBy('e.event_date')
            ->limit(10)
            ->get();

        if ($upcoming->isEmpty()) return [];

        // Historical averages for comparison
        $pastStats = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as capacity FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $this->record->id)
            ->whereNotNull('e.event_date')
            ->where('e.event_date', '<', now()->toDateString())
            ->select(DB::raw('AVG(ts.sold) as avg_sold'), DB::raw('AVG(CASE WHEN ts.capacity > 0 THEN ts.sold * 100.0 / ts.capacity ELSE NULL END) as avg_sell_through'), DB::raw('AVG(ts.capacity) as avg_capacity'))
            ->first();

        return $upcoming->map(function ($e) use ($pastStats) {
            $title = $e->title;
            if ($title && str_starts_with($title, '{')) { $d = json_decode($title, true); $title = $d['en'] ?? $d['ro'] ?? reset($d) ?: $title; }
            $vn = $e->venue_name;
            if ($vn && str_starts_with($vn, '{')) { $d = json_decode($vn, true); $vn = $d['en'] ?? $d['ro'] ?? reset($d) ?: $vn; }
            $sold = (int) ($e->sold ?? 0);
            $cap = (int) ($e->capacity ?? 0);
            $st = $cap > 0 ? round($sold / $cap * 100, 1) : null;
            $avgSt = round((float) ($pastStats?->avg_sell_through ?? 0), 1);
            $avgSold = round((float) ($pastStats?->avg_sold ?? 0));

            // Forecast: extrapolate based on days until event and historical pattern
            $daysUntil = $e->event_date ? max(0, now()->diffInDays(Carbon::parse($e->event_date), false)) : null;
            $forecastSold = null;
            if ($daysUntil !== null && $daysUntil > 0 && $sold > 0 && $avgSold > 0) {
                // Simple linear projection: current pace vs historical average
                $daysSelling = max(1, 90 - $daysUntil); // assume 90-day sales window
                $dailyRate = $sold / $daysSelling;
                $forecastSold = min($cap ?: 99999, (int) round($sold + ($dailyRate * $daysUntil)));
            }

            return [
                'id' => $e->id, 'title' => $title,
                'date' => $e->event_date ?? $e->range_start_date,
                'venue' => $vn, 'city' => $e->venue_city,
                'venue_capacity' => (int) ($e->venue_capacity ?? 0),
                'is_headliner' => (bool) $e->is_headliner,
                'sold' => $sold, 'capacity' => $cap,
                'sell_through' => $st,
                'revenue_sold' => round((float) ($e->revenue_sold ?? 0), 2),
                'days_until' => $daysUntil,
                'hist_avg_sold' => $avgSold,
                'hist_avg_sell_through' => $avgSt,
                'forecast_sold' => $forecastSold,
            ];
        })->toArray();
    }

    /** Serii pentru chart-uri (ultimele 12 luni) */
    public array $seriesMonths = [];
    public array $seriesEvents = [];
    public array $seriesTickets = [];
    public array $seriesRevenue = [];

    public function mount(\App\Models\Artist $record): void
    {
        $this->record = $record->load(['artistTypes:id,name,slug', 'artistGenres:id,name,slug']);
        if (method_exists($this->record, 'buildYearlySeries')) {
            [$months, $events, $tickets, $revenue] = $this->record->buildYearlySeries();
            $this->seriesMonths = $months;
            $this->seriesEvents = $events;
            $this->seriesTickets = $tickets;
            $this->seriesRevenue = $revenue;
        }
    }

    public function getHeading(): string
    {
        return $this->record->name;
    }
}
