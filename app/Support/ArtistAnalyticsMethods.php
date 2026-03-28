<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared analytics methods for artist dashboards.
 * Used by both admin ViewArtist page and tenant ArtistAnalytics page.
 * Expects $this->record to be an Artist model.
 */
trait ArtistAnalyticsMethods
{
    private function artistEventIds(): array
    {
        return DB::table('event_artist')
            ->where('artist_id', $this->record->id)
            ->pluck('event_id')
            ->toArray();
    }

    private function artistOrderIds(array $eventIds, array $paidStatuses = ['paid', 'confirmed', 'completed']): array
    {
        if (empty($eventIds)) return [];

        return DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where(function ($q) use ($eventIds) {
                $q->whereIn('tt.event_id', $eventIds)
                    ->orWhereIn('t.event_id', $eventIds)
                    ->orWhereIn('t.marketplace_event_id', $eventIds);
            })
            ->whereIn('o.status', $paidStatuses)
            ->distinct()
            ->pluck('o.id')
            ->toArray();
    }

    private function buildGeographicIntelligence(array $eventIds, array $orderIds): array
    {
        $artistId = $this->record->id;

        $fansByCity = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->join('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->whereNotNull('v.city')
            ->select(
                'v.city as city', 'v.country as country',
                DB::raw('SUM(COALESCE(tt.quota_sold, 0)) as tickets_sold'),
                DB::raw('SUM(COALESCE(tt.quota_sold * tt.price_cents / 100, 0)) as total_revenue'),
                DB::raw('COUNT(DISTINCT e.id) as events_count'),
                DB::raw('MAX(v.lat) as lat'), DB::raw('MAX(v.lng) as lng')
            )
            ->groupBy('v.city', 'v.country')
            ->orderByDesc('tickets_sold')
            ->limit(20)
            ->get();

        if ($fansByCity->isEmpty()) return [];

        $cities = $fansByCity->pluck('city')->filter()->unique()->values()->toArray();

        $favoritesByCity = collect();
        try {
            $favoritesByCity = DB::table('marketplace_customer_favorites as mcf')
                ->join('marketplace_customers as mc', 'mc.id', '=', 'mcf.marketplace_customer_id')
                ->where('mcf.favoriteable_id', $this->record->id)
                ->where('mcf.favoriteable_type', 'LIKE', '%Artist%')
                ->whereNotNull('mc.city')
                ->select('mc.city', DB::raw('COUNT(*) as fav_count'))
                ->groupBy('mc.city')
                ->get()
                ->keyBy('city');
        } catch (\Exception $e) {}

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
                if ($name && str_starts_with($name, '{')) { $d = json_decode($name, true); $name = $d['en'] ?? $d['ro'] ?? reset($d) ?: $name; }
                return ['name' => $name, 'capacity' => (int) $cap, 'fill_rate' => round(($fillRates->get($v->id)?->avg_fill_rate ?? 0) * 100, 1)];
            })->values()->toArray();

            $ticketsSold = (int) $row->tickets_sold;
            $recommended = collect($venues)->filter(fn ($v) => $v['capacity'] > 0)->sortBy(fn ($v) => abs($v['capacity'] - $ticketsSold))->first();

            return [
                'city' => $city, 'country' => $row->country,
                'tickets_sold' => $ticketsSold, 'events_count' => (int) $row->events_count,
                'favorites_count' => (int) $favCount,
                'total_revenue' => round((float) $row->total_revenue, 2),
                'lat' => (float) ($row->lat ?? 0), 'lng' => (float) ($row->lng ?? 0),
                'venues' => $venues,
                'recommended_venue' => $recommended['name'] ?? null,
                'recommended_capacity' => $recommended['capacity'] ?? null,
            ];
        })->toArray();
    }

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
            })->toArray();

        // Role comparison
        $roleStats = collect($eventsPerf)->groupBy(fn ($e) => $e['is_headliner'] ? 'Headliner' : ($e['is_co_headliner'] ? 'Co-Headliner' : 'Support'))
            ->map(fn ($group) => [
                'events' => $group->count(),
                'avg_sold' => round($group->avg('sold'), 0),
                'avg_sell_through' => round($group->whereNotNull('sell_through')->avg('sell_through'), 1),
            ])->toArray();

        // Repeat customers
        $loyalty = ['one_time' => 0, 'repeat' => 0, 'superfan' => 0, 'repeat_rate' => 0, 'total' => 0];
        if (!empty($orderIds)) {
            $customerEvents = DB::table('orders as o')
                ->join('tickets as t', 't.order_id', '=', 'o.id')
                ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->whereIn('o.id', $orderIds)
                ->select(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'), DB::raw('COUNT(DISTINCT COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)) as events_attended'))
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
        }

        $allWithSt = collect($eventsPerf)->whereNotNull('sell_through');
        return [
            'events' => $eventsPerf,
            'avg_sell_through' => round($allWithSt->avg('sell_through'), 1),
            'role_comparison' => $roleStats,
            'customer_loyalty' => $loyalty,
        ];
    }

    private function buildSalesIntelligence(array $eventIds, array $orderIds): array
    {
        $empty = ['channels' => [], 'purchase_timing' => [], 'avg_lead_days' => 0, 'price_sensitivity' => [], 'velocity_curves' => [], 'avg_revenue_per_event' => 0, 'fee_comparison' => null];
        if (empty($orderIds)) return $empty;

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

        $totalRevenue = DB::table('orders')->whereIn('id', $orderIds)->sum('total');
        $totalEvents = count($eventIds);
        $avgRevenuePerEvent = $totalEvents > 0 ? round($totalRevenue / $totalEvents, 2) : 0;

        // Fee comparison
        $feeComparison = null;
        if ($this->record->min_fee_concert || $this->record->max_fee_concert) {
            $feeComparison = [
                'min_fee' => $this->record->min_fee_concert,
                'max_fee' => $this->record->max_fee_concert,
                'avg_revenue' => $avgRevenuePerEvent,
                'in_range' => $avgRevenuePerEvent >= ($this->record->min_fee_concert ?? 0) && ($this->record->max_fee_concert ? $avgRevenuePerEvent <= $this->record->max_fee_concert : true),
            ];
        }

        // Purchase timing
        $purchaseTiming = [];
        $avgLeadDays = 0;
        try {
            $leadTimes = DB::table('orders as o')
                ->join('tickets as t', 't.order_id', '=', 'o.id')
                ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->join('events as e', function ($join) {
                    $join->on('e.id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'));
                })
                ->whereIn('o.id', array_slice($orderIds, 0, 5000))
                ->whereNotNull('e.event_date')
                ->select(DB::raw(DB::getDriverName() === 'pgsql'
                    ? '(e.event_date::date - COALESCE(o.paid_at, o.created_at)::date) as days_before'
                    : 'DATEDIFF(e.event_date, COALESCE(o.paid_at, o.created_at)) as days_before'))
                ->get()
                ->pluck('days_before')
                ->filter(fn ($d) => $d !== null && $d >= 0);

            $totalLead = $leadTimes->count();
            $purchaseTiming = [
                'last_minute' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d <= 1)->count() / $totalLead * 100, 1) : 0,
                'last_week' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 2 && $d <= 7)->count() / $totalLead * 100, 1) : 0,
                'last_month' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 8 && $d <= 30)->count() / $totalLead * 100, 1) : 0,
                'early_bird' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 31 && $d <= 90)->count() / $totalLead * 100, 1) : 0,
                'super_early' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d > 90)->count() / $totalLead * 100, 1) : 0,
            ];
            $avgLeadDays = round($leadTimes->avg() ?? 0, 1);
        } catch (\Exception $e) {}

        return [
            'channels' => [], 'purchase_timing' => $purchaseTiming,
            'avg_lead_days' => $avgLeadDays,
            'price_sensitivity' => $priceSensitivity,
            'velocity_curves' => [],
            'avg_revenue_per_event' => $avgRevenuePerEvent,
            'fee_comparison' => $feeComparison,
        ];
    }

    private function buildCityExpansionPlanner(array $eventIds, array $orderIds): array
    {
        $artistId = $this->record->id;

        $performedCities = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->join('venues as v', 'v.id', '=', 'e.venue_id')
            ->where('ea.artist_id', $artistId)
            ->whereNotNull('v.city')
            ->pluck('v.city')->unique()->values()->toArray();

        $genreIds = DB::table('artist_artist_genre')->where('artist_id', $artistId)->pluck('artist_genre_id')->toArray();
        if (empty($genreIds)) return [];

        $similarIds = DB::table('artist_artist_genre')
            ->whereIn('artist_genre_id', $genreIds)
            ->where('artist_id', '!=', $artistId)
            ->pluck('artist_id')->unique()->take(50)->toArray();

        if (empty($similarIds)) return [];

        $query = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->join('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->whereIn('ea.artist_id', $similarIds)
            ->whereNotNull('v.city');

        if (!empty($performedCities)) { $query->whereNotIn('v.city', $performedCities); }

        $expansionCities = $query
            ->select('v.city as city', 'v.country as country', DB::raw('COUNT(DISTINCT e.id) as similar_events'), DB::raw('AVG(ts.sold) as avg_attendance'), DB::raw('AVG(CASE WHEN ts.cap > 0 THEN LEAST(ts.sold * 1.0 / ts.cap, 1.0) ELSE NULL END) as avg_sell_through'))
            ->groupBy('v.city', 'v.country')
            ->havingRaw('COUNT(DISTINCT e.id) >= 2')
            ->orderByDesc(DB::raw('COUNT(DISTINCT e.id)'))
            ->limit(15)
            ->get();

        if ($expansionCities->isEmpty()) return [];

        return $expansionCities->map(function ($row) {
            return [
                'city' => $row->city, 'country' => $row->country,
                'similar_events' => (int) $row->similar_events,
                'estimated_demand' => round((float) ($row->avg_attendance ?? 0)),
                'similar_sell_through' => round((float) ($row->avg_sell_through ?? 0) * 100, 1),
                'confidence' => $row->similar_events >= 5 ? 'high' : ($row->similar_events >= 3 ? 'medium' : 'low'),
            ];
        })->toArray();
    }

    private function buildUpcomingEventsAnalysis(array $eventIds): array
    {
        if (empty($eventIds)) return [];

        $upcoming = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as capacity FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $this->record->id)
            ->where('e.event_date', '>=', now()->toDateString())
            ->select('e.id', 'e.title', 'e.event_date', 'v.name as venue_name', 'v.city as venue_city', 'ea.is_headliner', 'ts.sold', 'ts.capacity')
            ->orderBy('e.event_date')
            ->limit(10)
            ->get();

        if ($upcoming->isEmpty()) return [];

        return $upcoming->map(function ($e) {
            $title = $e->title;
            if ($title && str_starts_with($title, '{')) { $d = json_decode($title, true); $title = $d['en'] ?? $d['ro'] ?? reset($d) ?: $title; }
            $vn = $e->venue_name;
            if ($vn && str_starts_with($vn, '{')) { $d = json_decode($vn, true); $vn = $d['en'] ?? $d['ro'] ?? reset($d) ?: $vn; }
            $sold = (int) ($e->sold ?? 0); $cap = (int) ($e->capacity ?? 0);
            $daysUntil = $e->event_date ? max(0, now()->diffInDays(Carbon::parse($e->event_date), false)) : null;

            return [
                'id' => $e->id, 'title' => $title, 'date' => $e->event_date,
                'venue' => $vn, 'city' => $e->venue_city,
                'is_headliner' => (bool) $e->is_headliner,
                'sold' => $sold, 'capacity' => $cap,
                'sell_through' => $cap > 0 ? round($sold / $cap * 100, 1) : null,
                'days_until' => $daysUntil,
            ];
        })->toArray();
    }

    private function buildOpportunities(array $eventIds, array $orderIds): array
    {
        if (empty($eventIds)) return [];
        $artistId = $this->record->id;

        $recommendations = [];

        // Best day of week
        $dayPerformance = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)->whereNotNull('e.event_date')
            ->select(
                DB::raw(DB::getDriverName() === 'pgsql' ? "TO_CHAR(e.event_date, 'Day') as day_name" : 'DAYNAME(e.event_date) as day_name'),
                DB::raw('COUNT(DISTINCT e.id) as events'),
                DB::raw('AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st')
            )
            ->groupBy('day_name')
            ->orderByDesc('avg_st')
            ->get();

        $bestDay = $dayPerformance->first();
        if ($bestDay && $dayPerformance->count() >= 2) {
            $recommendations[] = ['icon' => 'calendar', 'category' => 'Scheduling', 'title' => "Best day: " . trim($bestDay->day_name), 'detail' => round((float) $bestDay->avg_st, 1) . "% avg sell-through across {$bestDay->events} events", 'confidence' => $bestDay->events >= 3 ? 'high' : 'medium'];
        }

        // Best months
        $monthPerformance = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)->whereNotNull('e.event_date')
            ->select(
                DB::raw(DB::getDriverName() === 'pgsql' ? "TO_CHAR(e.event_date, 'Month') as month_name" : 'MONTHNAME(e.event_date) as month_name'),
                DB::raw('COUNT(DISTINCT e.id) as events'),
                DB::raw('AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st')
            )
            ->groupBy('month_name')
            ->orderByDesc('avg_st')
            ->get();

        $bestMonths = $monthPerformance->take(3);
        if ($bestMonths->isNotEmpty()) {
            $recommendations[] = ['icon' => 'trend', 'category' => 'Seasonality', 'title' => 'Best months: ' . $bestMonths->pluck('month_name')->map(fn ($m) => trim($m))->join(', '), 'detail' => $bestMonths->map(fn ($m) => trim($m->month_name) . ' (' . round((float) $m->avg_st, 1) . '%)')->join(', '), 'confidence' => 'medium'];
        }

        return ['recommendations' => $recommendations];
    }
}
