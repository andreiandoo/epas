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

    public function getViewData(): array
    {
        $from = request()->date_from
            ? Carbon::parse(request()->date_from)->startOfDay()
            : now()->subDays(365)->startOfDay();

        $to = request()->date_to
            ? Carbon::parse(request()->date_to)->endOfDay()
            : now()->endOfDay();

        // KPI-urile
        $kpis = $this->record->computeKpis($from, $to);

        // Serii (dacă le folosești în grafic)
        [$months, $events, $tickets, $revenue] = $this->record->buildYearlySeries();

        // Events list for this artist
        $artistEvents = $this->record->events()
            ->with(['venue', 'tenant'])
            ->orderBy('event_date', 'desc')
            ->get();

        // Unique venues from events
        $artistVenues = $artistEvents
            ->pluck('venue')
            ->filter()
            ->unique('id')
            ->values();

        // Unique tenants from events
        $artistTenants = $artistEvents
            ->pluck('tenant')
            ->filter()
            ->unique('id')
            ->values();

        // Top venues by ticket sales for this artist
        $topVenues = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $this->record->id)
            ->select('venues.id', 'venues.name', DB::raw('COUNT(tickets.id) as tickets_count'))
            ->groupBy('venues.id', 'venues.name')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get();

        // Top cities by ticket sales
        $topCities = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $this->record->id)
            ->whereNotNull('venues.city')
            ->select('venues.city as name', DB::raw('COUNT(tickets.id) as tickets_count'))
            ->groupBy('venues.city')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get();

        // Top counties/states by ticket sales
        $topCounties = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $this->record->id)
            ->whereNotNull('venues.state')
            ->select('venues.state as name', DB::raw('COUNT(tickets.id) as tickets_count'))
            ->groupBy('venues.state')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get();

        // === ARTIST 360 ANALYTICS (cached 5 min) ===
        $cacheKey = "artist_360_{$this->record->id}";
        if (request()->has('refresh_analytics')) {
            Cache::forget($cacheKey);
        }

        $analytics360 = Cache::remember($cacheKey, 300, function () {
            return [
                'audiencePersonas' => $this->buildAudiencePersonas(),
                'geoIntelligence' => $this->buildGeographicIntelligence(),
                'performanceDeepDive' => $this->buildPerformanceDeepDive(),
                'salesIntelligence' => $this->buildSalesIntelligence(),
                'expansionPlanner' => $this->buildCityExpansionPlanner(),
            ];
        });

        return array_merge(
            compact(
                'kpis', 'months', 'events', 'tickets', 'revenue', 'from', 'to',
                'artistEvents', 'artistVenues', 'artistTenants',
                'topVenues', 'topCities', 'topCounties'
            ),
            $analytics360
        );
    }

    // ─── AUDIENCE DNA (Customer Personas) ────────────────────────────

    private function buildAudiencePersonas(): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // Get all buyers with demographics
        $buyers = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'tt.event_id')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('ea.artist_id', $this->record->id)
            ->whereIn('o.status', $paidStatuses)
            ->select(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'),
                DB::raw('COALESCE(mc.birth_date, c.date_of_birth) as birth_date'),
                DB::raw('mc.gender'),
                DB::raw('COALESCE(mc.city, c.city) as city'),
                DB::raw('COALESCE(mc.country, c.country) as country'),
                DB::raw('SUM(o.total) as total_spent'),
                DB::raw('COUNT(DISTINCT o.id) as order_count')
            )
            ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'), DB::raw('COALESCE(mc.birth_date, c.date_of_birth)'), 'mc.gender', DB::raw('COALESCE(mc.city, c.city)'), DB::raw('COALESCE(mc.country, c.country)'))
            ->get();

        if ($buyers->isEmpty()) {
            return ['personas' => [], 'totals' => ['total_customers' => 0, 'with_demographics' => 0, 'age_distribution' => [], 'gender_overall' => []]];
        }

        $totalCustomers = $buyers->count();
        $now = now();

        // Compute age groups and gender
        $withAge = $buyers->map(function ($b) use ($now) {
            $age = null;
            if ($b->birth_date) {
                try {
                    $age = Carbon::parse($b->birth_date)->age;
                } catch (\Exception $e) {
                }
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
            $gender = $b->gender ?: 'unknown';
            return (object) [
                'buyer_id' => $b->buyer_id,
                'age' => $age,
                'age_group' => $ageGroup,
                'gender' => $gender,
                'city' => $b->city,
                'country' => $b->country,
                'total_spent' => (float) $b->total_spent,
                'order_count' => (int) $b->order_count,
            ];
        });

        // Age distribution
        $ageDistribution = $withAge->where('age_group', '!=', 'unknown')
            ->countBy('age_group')
            ->sortKeys()
            ->toArray();

        // Gender overall
        $genderOverall = $withAge->where('gender', '!=', 'unknown')
            ->countBy('gender')
            ->toArray();

        // Build personas by clustering (age_group, gender)
        $clusters = $withAge->where('age_group', '!=', 'unknown')
            ->groupBy(fn ($b) => $b->age_group . '_' . $b->gender);

        $personas = $clusters->map(function ($group, $key) use ($totalCustomers) {
            $parts = explode('_', $key);
            $ageGroup = $parts[0];
            $gender = $parts[1] ?? 'unknown';

            $topCities = $group->whereNotNull('city')
                ->countBy('city')
                ->sortDesc()
                ->take(3)
                ->toArray();

            return [
                'age_group' => $ageGroup,
                'gender' => $gender,
                'count' => $group->count(),
                'percentage' => round($group->count() / $totalCustomers * 100, 1),
                'avg_spend' => round($group->avg('total_spent'), 2),
                'avg_orders' => round($group->avg('order_count'), 1),
                'top_cities' => $topCities,
            ];
        })
            ->sortByDesc('count')
            ->values()
            ->take(3)
            ->toArray();

        // Label them
        $labels = ['Primary Persona', 'Secondary Persona', 'Tertiary Persona'];
        foreach ($personas as $i => &$p) {
            $p['label'] = $labels[$i] ?? 'Other';
        }

        return [
            'personas' => $personas,
            'totals' => [
                'total_customers' => $totalCustomers,
                'with_demographics' => $withAge->where('age_group', '!=', 'unknown')->count(),
                'age_distribution' => $ageDistribution,
                'gender_overall' => $genderOverall,
            ],
        ];
    }

    // ─── GEOGRAPHIC INTELLIGENCE ─────────────────────────────────────

    private function buildGeographicIntelligence(): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];
        $artistId = $this->record->id;

        // Fans per city (from order buyers)
        $fansByCity = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'tt.event_id')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('ea.artist_id', $artistId)
            ->whereIn('o.status', $paidStatuses)
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

        if ($fansByCity->isEmpty()) {
            return [];
        }

        $cities = $fansByCity->pluck('city')->filter()->unique()->values()->toArray();

        // Favorites per city
        $favoritesByCity = DB::table('marketplace_customer_favorites as mcf')
            ->join('marketplace_customers as mc', 'mc.id', '=', 'mcf.marketplace_customer_id')
            ->where('mcf.favoriteable_id', $artistId)
            ->where('mcf.favoriteable_type', 'LIKE', '%Artist%')
            ->whereNotNull('mc.city')
            ->select('mc.city', DB::raw('COUNT(*) as fav_count'))
            ->groupBy('mc.city')
            ->get()
            ->keyBy('city');

        // Venues per city
        $venuesByCity = DB::table('venues')
            ->whereIn('city', $cities)
            ->select('id', 'name', 'city', 'capacity', 'capacity_total', 'capacity_standing', 'capacity_seated')
            ->orderBy('city')
            ->orderByDesc('capacity')
            ->get()
            ->groupBy('city');

        // Fill rate per venue (avg sell-through from events at that venue)
        $venueIds = $venuesByCity->flatten()->pluck('id')->toArray();
        $fillRates = collect();
        if (!empty($venueIds)) {
            $fillRates = DB::table('ticket_types as tt')
                ->join('events as e', 'e.id', '=', 'tt.event_id')
                ->whereIn('e.venue_id', $venueIds)
                ->where('tt.quota_total', '>', 0)
                ->select(
                    'e.venue_id',
                    DB::raw('COUNT(DISTINCT e.id) as events_count'),
                    DB::raw('AVG(LEAST(tt.quota_sold * 1.0 / tt.quota_total, 1.0)) as avg_fill_rate')
                )
                ->groupBy('e.venue_id')
                ->get()
                ->keyBy('venue_id');
        }

        // Artist events per venue
        $artistVenueEvents = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->whereIn('e.venue_id', $venueIds)
            ->select('e.venue_id', DB::raw('COUNT(*) as artist_events'))
            ->groupBy('e.venue_id')
            ->get()
            ->keyBy('venue_id');

        // Build results
        $results = $fansByCity->map(function ($row) use ($favoritesByCity, $venuesByCity, $fillRates, $artistVenueEvents) {
            $city = $row->city;
            $favCount = $favoritesByCity->get($city)?->fav_count ?? 0;
            $potential = $row->fans_count + ($favCount * 2);

            $venues = ($venuesByCity->get($city) ?? collect())->map(function ($v) use ($fillRates, $artistVenueEvents) {
                $cap = $v->capacity ?: $v->capacity_total ?: ($v->capacity_standing + $v->capacity_seated);
                return [
                    'id' => $v->id,
                    'name' => is_string($v->name) ? $v->name : (is_array(json_decode($v->name, true)) ? (json_decode($v->name, true)['en'] ?? json_decode($v->name, true)['ro'] ?? $v->name) : $v->name),
                    'capacity' => (int) $cap,
                    'fill_rate' => round(($fillRates->get($v->id)?->avg_fill_rate ?? 0) * 100, 1),
                    'events_hosted' => (int) ($fillRates->get($v->id)?->events_count ?? 0),
                    'artist_events' => (int) ($artistVenueEvents->get($v->id)?->artist_events ?? 0),
                ];
            })->values()->toArray();

            // Recommend venue: capacity closest to potential without being < 70%
            $recommended = collect($venues)
                ->filter(fn ($v) => $v['capacity'] > 0)
                ->sortBy(fn ($v) => abs($v['capacity'] - $potential))
                ->first();

            return [
                'city' => $city,
                'country' => $row->country,
                'fans_count' => (int) $row->fans_count,
                'favorites_count' => (int) $favCount,
                'total_revenue' => round((float) $row->total_revenue, 2),
                'orders_count' => (int) $row->orders_count,
                'potential_buyers' => $potential,
                'venues' => $venues,
                'recommended_venue' => $recommended['name'] ?? null,
                'recommended_capacity' => $recommended['capacity'] ?? null,
            ];
        })->toArray();

        return $results;
    }

    // ─── PERFORMANCE DEEP-DIVE ───────────────────────────────────────

    private function buildPerformanceDeepDive(): array
    {
        $artistId = $this->record->id;

        // Sell-through per event
        $eventsPerf = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as capacity FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->select(
                'e.id', 'e.title', 'e.event_date',
                'v.name as venue_name', 'v.city as venue_city',
                'ea.is_headliner', 'ea.is_co_headliner',
                'ts.sold', 'ts.capacity'
            )
            ->orderByDesc('e.event_date')
            ->get()
            ->map(function ($e) {
                $title = $e->title;
                if ($title && str_starts_with($title, '{')) {
                    $decoded = json_decode($title, true);
                    $title = $decoded['en'] ?? $decoded['ro'] ?? reset($decoded) ?: $title;
                }
                $venueName = $e->venue_name;
                if ($venueName && str_starts_with($venueName, '{')) {
                    $decoded = json_decode($venueName, true);
                    $venueName = $decoded['en'] ?? $decoded['ro'] ?? reset($decoded) ?: $venueName;
                }
                $sold = (int) $e->sold;
                $cap = (int) $e->capacity;
                return [
                    'id' => $e->id,
                    'title' => $title,
                    'date' => $e->event_date,
                    'venue' => $venueName,
                    'city' => $e->venue_city,
                    'is_headliner' => (bool) $e->is_headliner,
                    'is_co_headliner' => (bool) $e->is_co_headliner,
                    'sold' => $sold,
                    'capacity' => $cap,
                    'sell_through' => $cap > 0 ? round($sold / $cap * 100, 1) : null,
                ];
            });

        // Check-in rates per event
        $checkinRates = DB::table('tickets as t')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'tt.event_id')
            ->where('ea.artist_id', $artistId)
            ->where('t.status', 'valid')
            ->select(
                'tt.event_id',
                DB::raw('COUNT(t.id) as total_tickets'),
                DB::raw('SUM(CASE WHEN t.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) as checked_in')
            )
            ->groupBy('tt.event_id')
            ->get()
            ->keyBy('event_id');

        // Merge check-in data
        $eventsPerf = $eventsPerf->map(function ($e) use ($checkinRates) {
            $ci = $checkinRates->get($e['id']);
            $e['checkin_total'] = (int) ($ci?->total_tickets ?? 0);
            $e['checkin_count'] = (int) ($ci?->checked_in ?? 0);
            $e['checkin_rate'] = $e['checkin_total'] > 0 ? round($e['checkin_count'] / $e['checkin_total'] * 100, 1) : null;
            return $e;
        })->toArray();

        // Role comparison
        $roleStats = collect($eventsPerf)->groupBy(function ($e) {
            if ($e['is_headliner']) return 'Headliner';
            if ($e['is_co_headliner']) return 'Co-Headliner';
            return 'Support';
        })->map(function ($group, $role) {
            $withSt = collect($group)->whereNotNull('sell_through');
            $withCi = collect($group)->whereNotNull('checkin_rate');
            return [
                'events' => $group->count(),
                'avg_sold' => round($group->avg('sold'), 0),
                'avg_sell_through' => round($withSt->avg('sell_through'), 1),
                'avg_checkin_rate' => round($withCi->avg('checkin_rate'), 1),
            ];
        })->toArray();

        // Repeat customers
        $paidStatuses = ['paid', 'confirmed', 'completed'];
        $customerEvents = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'tt.event_id')
            ->where('ea.artist_id', $artistId)
            ->whereIn('o.status', $paidStatuses)
            ->select(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'),
                DB::raw('COUNT(DISTINCT ea.event_id) as events_attended')
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

        // Averages
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

    private function buildSalesIntelligence(): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];
        $artistId = $this->record->id;

        // Channel breakdown
        $channels = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'tt.event_id')
            ->where('ea.artist_id', $artistId)
            ->whereIn('o.status', $paidStatuses)
            ->select(
                'o.source',
                DB::raw('COUNT(DISTINCT o.id) as orders_count'),
                DB::raw('SUM(o.total) as revenue'),
                DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as unique_customers')
            )
            ->groupBy('o.source')
            ->get()
            ->keyBy('source')
            ->toArray();

        $totalChannelOrders = collect($channels)->sum('orders_count');
        foreach ($channels as &$ch) {
            $ch->pct = $totalChannelOrders > 0 ? round($ch->orders_count / $totalChannelOrders * 100, 1) : 0;
        }

        // Purchase lead time
        $leadTimes = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->whereIn('o.status', $paidStatuses)
            ->whereNotNull('o.paid_at')
            ->whereNotNull('e.event_date')
            ->select(DB::raw('DATEDIFF(e.event_date, o.paid_at) as days_before'))
            ->get()
            ->pluck('days_before');

        $totalLeadOrders = $leadTimes->count();
        $purchaseTiming = [
            'last_minute' => $totalLeadOrders > 0 ? round($leadTimes->filter(fn ($d) => $d >= 0 && $d <= 1)->count() / $totalLeadOrders * 100, 1) : 0,
            'last_week' => $totalLeadOrders > 0 ? round($leadTimes->filter(fn ($d) => $d >= 2 && $d <= 7)->count() / $totalLeadOrders * 100, 1) : 0,
            'last_month' => $totalLeadOrders > 0 ? round($leadTimes->filter(fn ($d) => $d >= 8 && $d <= 30)->count() / $totalLeadOrders * 100, 1) : 0,
            'early_bird' => $totalLeadOrders > 0 ? round($leadTimes->filter(fn ($d) => $d >= 31 && $d <= 90)->count() / $totalLeadOrders * 100, 1) : 0,
            'super_early' => $totalLeadOrders > 0 ? round($leadTimes->filter(fn ($d) => $d > 90)->count() / $totalLeadOrders * 100, 1) : 0,
        ];
        $avgLeadDays = $leadTimes->filter(fn ($d) => $d >= 0)->avg() ?? 0;

        // Price sensitivity
        $priceData = DB::table('ticket_types as tt')
            ->join('event_artist as ea', 'ea.event_id', '=', 'tt.event_id')
            ->where('ea.artist_id', $artistId)
            ->where('tt.quota_total', '>', 0)
            ->select('tt.price_cents', 'tt.quota_sold', 'tt.quota_total')
            ->get();

        $priceBuckets = [
            '0-50' => ['tickets' => 0, 'capacity' => 0],
            '50-100' => ['tickets' => 0, 'capacity' => 0],
            '100-200' => ['tickets' => 0, 'capacity' => 0],
            '200-500' => ['tickets' => 0, 'capacity' => 0],
            '500+' => ['tickets' => 0, 'capacity' => 0],
        ];

        foreach ($priceData as $p) {
            $price = $p->price_cents / 100;
            $bucket = match (true) {
                $price < 50 => '0-50',
                $price < 100 => '50-100',
                $price < 200 => '100-200',
                $price < 500 => '200-500',
                default => '500+',
            };
            $priceBuckets[$bucket]['tickets'] += $p->quota_sold;
            $priceBuckets[$bucket]['capacity'] += $p->quota_total;
        }

        $priceSensitivity = [];
        foreach ($priceBuckets as $range => $data) {
            $priceSensitivity[] = [
                'range' => $range,
                'tickets' => $data['tickets'],
                'sell_through' => $data['capacity'] > 0 ? round($data['tickets'] / $data['capacity'] * 100, 1) : 0,
            ];
        }

        // Sales velocity for last 5 events
        $lastEventIds = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->whereNotNull('e.event_date')
            ->orderByDesc('e.event_date')
            ->limit(5)
            ->pluck('e.id')
            ->toArray();

        $velocityCurves = [];
        if (!empty($lastEventIds)) {
            $velocityData = DB::table('orders as o')
                ->join('tickets as t', 't.order_id', '=', 'o.id')
                ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->join('events as e', 'e.id', '=', 'tt.event_id')
                ->whereIn('e.id', $lastEventIds)
                ->whereIn('o.status', $paidStatuses)
                ->whereNotNull('o.paid_at')
                ->select('e.id', 'e.title', 'e.event_date', 'o.paid_at', DB::raw('COUNT(t.id) as ticket_count'))
                ->groupBy('e.id', 'e.title', 'e.event_date', 'o.paid_at')
                ->orderBy('o.paid_at')
                ->get();

            $byEvent = $velocityData->groupBy('id');
            foreach ($byEvent as $eventId => $rows) {
                $title = $rows->first()->title;
                if ($title && str_starts_with($title, '{')) {
                    $decoded = json_decode($title, true);
                    $title = $decoded['en'] ?? $decoded['ro'] ?? reset($decoded) ?: $title;
                }
                $eventDate = Carbon::parse($rows->first()->event_date);
                $totalTickets = $rows->sum('ticket_count');
                $cumulative = 0;
                $points = [];
                foreach ($rows as $r) {
                    $daysBefore = $eventDate->diffInDays(Carbon::parse($r->paid_at), false);
                    $cumulative += $r->ticket_count;
                    $pct = $totalTickets > 0 ? round($cumulative / $totalTickets * 100, 1) : 0;
                    $points[] = ['days' => max(0, (int) $daysBefore), 'pct' => $pct];
                }
                $velocityCurves[] = [
                    'event_name' => mb_substr($title, 0, 30),
                    'total_tickets' => $totalTickets,
                    'points' => $points,
                ];
            }
        }

        return [
            'channels' => $channels,
            'purchase_timing' => $purchaseTiming,
            'avg_lead_days' => round($avgLeadDays, 1),
            'price_sensitivity' => $priceSensitivity,
            'velocity_curves' => $velocityCurves,
        ];
    }

    // ─── CITY EXPANSION PLANNER ──────────────────────────────────────

    private function buildCityExpansionPlanner(): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];
        $artistId = $this->record->id;

        // Cities where artist has performed
        $performedCities = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->join('venues as v', 'v.id', '=', 'e.venue_id')
            ->where('ea.artist_id', $artistId)
            ->whereNotNull('v.city')
            ->pluck('v.city')
            ->unique()
            ->values()
            ->toArray();

        // Fans in cities where artist has NOT performed
        $expansionCities = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'tt.event_id')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('ea.artist_id', $artistId)
            ->whereIn('o.status', $paidStatuses)
            ->whereNotNull(DB::raw('COALESCE(mc.city, c.city)'));

        if (!empty($performedCities)) {
            $expansionCities = $expansionCities->whereNotIn(DB::raw('COALESCE(mc.city, c.city)'), $performedCities);
        }

        $expansionCities = $expansionCities
            ->select(
                DB::raw('COALESCE(mc.city, c.city) as city'),
                DB::raw('COALESCE(mc.country, c.country) as country'),
                DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as fan_count')
            )
            ->groupBy(DB::raw('COALESCE(mc.city, c.city)'), DB::raw('COALESCE(mc.country, c.country)'))
            ->havingRaw('fan_count >= 2')
            ->orderByDesc('fan_count')
            ->limit(20)
            ->get();

        if ($expansionCities->isEmpty()) {
            return [];
        }

        $expCityNames = $expansionCities->pluck('city')->toArray();

        // Venues in expansion cities
        $venuesInCities = DB::table('venues')
            ->whereIn('city', $expCityNames)
            ->select('id', 'name', 'city', 'capacity', 'capacity_total')
            ->orderByDesc('capacity')
            ->get()
            ->groupBy('city');

        // Similar artists (share genres) performance in these cities
        $genreIds = DB::table('artist_artist_genre')
            ->where('artist_id', $artistId)
            ->pluck('artist_genre_id')
            ->toArray();

        $similarArtistData = collect();
        if (!empty($genreIds) && !empty($expCityNames)) {
            $similarIds = DB::table('artist_artist_genre')
                ->whereIn('artist_genre_id', $genreIds)
                ->where('artist_id', '!=', $artistId)
                ->pluck('artist_id')
                ->unique()
                ->take(50)
                ->toArray();

            if (!empty($similarIds)) {
                $similarArtistData = DB::table('events as e')
                    ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
                    ->join('venues as v', 'v.id', '=', 'e.venue_id')
                    ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as cap FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
                    ->whereIn('ea.artist_id', $similarIds)
                    ->whereIn('v.city', $expCityNames)
                    ->select(
                        'v.city',
                        DB::raw('COUNT(DISTINCT e.id) as events_count'),
                        DB::raw('AVG(ts.sold) as avg_attendance'),
                        DB::raw('AVG(CASE WHEN ts.cap > 0 THEN LEAST(ts.sold * 1.0 / ts.cap, 1.0) ELSE NULL END) as avg_sell_through')
                    )
                    ->groupBy('v.city')
                    ->get()
                    ->keyBy('city');
            }
        }

        // Build results
        return $expansionCities->map(function ($row) use ($venuesInCities, $similarArtistData) {
            $city = $row->city;
            $venues = ($venuesInCities->get($city) ?? collect())->take(3)->map(function ($v) {
                $name = $v->name;
                if ($name && str_starts_with($name, '{')) {
                    $decoded = json_decode($name, true);
                    $name = $decoded['en'] ?? $decoded['ro'] ?? reset($decoded) ?: $name;
                }
                return [
                    'name' => $name,
                    'capacity' => (int) ($v->capacity ?: $v->capacity_total ?: 0),
                ];
            })->toArray();

            $similar = $similarArtistData->get($city);
            $simEvents = (int) ($similar?->events_count ?? 0);
            $simAvgAttendance = round((float) ($similar?->avg_attendance ?? 0));
            $simSellThrough = round((float) ($similar?->avg_sell_through ?? 0) * 100, 1);

            $estimatedDemand = $row->fan_count * 3; // fans * multiplier

            // Confidence
            $confidence = 'low';
            if ($row->fan_count >= 10 && $simEvents >= 3) $confidence = 'high';
            elseif ($row->fan_count >= 5 || $simEvents >= 2) $confidence = 'medium';

            return [
                'city' => $city,
                'country' => $row->country,
                'fan_count' => (int) $row->fan_count,
                'estimated_demand' => $estimatedDemand,
                'venues' => $venues,
                'similar_events' => $simEvents,
                'similar_avg_attendance' => $simAvgAttendance,
                'similar_sell_through' => $simSellThrough,
                'confidence' => $confidence,
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
        $this->record = $record->load([
            'artistTypes:id,name,slug',
            'artistGenres:id,name,slug',
        ]);

        if (method_exists($this->record, 'buildYearlySeries')) {
            [$months, $events, $tickets, $revenue] = $this->record->buildYearlySeries();
            $this->seriesMonths  = $months;
            $this->seriesEvents  = $events;
            $this->seriesTickets = $tickets;
            $this->seriesRevenue = $revenue;
        }
    }

    public function getHeading(): string
    {
        return $this->record->name;
    }
}
