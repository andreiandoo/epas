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

    private function buildAudiencePersonas(array $orderIds): array
    {
        if (empty($orderIds)) return ['personas' => [], 'totals' => ['total_customers' => 0, 'with_demographics' => 0, 'age_distribution' => [], 'gender_overall' => []]];

        $buyers = DB::table('orders as o')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereIn('o.id', $orderIds)
            ->select(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'),
                DB::raw('COALESCE(mc.birth_date, c.date_of_birth) as birth_date'),
                'mc.gender',
                DB::raw('COALESCE(mc.city, c.city) as city'),
                DB::raw('SUM(o.total) as total_spent'),
                DB::raw('COUNT(DISTINCT o.id) as order_count')
            )
            ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'), DB::raw('COALESCE(mc.birth_date, c.date_of_birth)'), 'mc.gender', DB::raw('COALESCE(mc.city, c.city)'))
            ->get()->unique('buyer_id')->values();

        $totalCustomers = $buyers->count();
        if ($totalCustomers === 0) return ['personas' => [], 'totals' => ['total_customers' => 0, 'with_demographics' => 0, 'age_distribution' => [], 'gender_overall' => []]];

        $withAge = $buyers->map(function ($b) {
            $age = null;
            if ($b->birth_date) { try { $age = Carbon::parse($b->birth_date)->age; } catch (\Exception $e) {} }
            $ageGroup = match (true) { $age === null => 'unknown', $age < 18 => '<18', $age <= 24 => '18-24', $age <= 34 => '25-34', $age <= 44 => '35-44', $age <= 54 => '45-54', default => '55+' };
            return (object) ['buyer_id' => $b->buyer_id, 'age_group' => $ageGroup, 'gender' => $b->gender ?: 'unknown', 'city' => $b->city, 'total_spent' => (float) $b->total_spent, 'order_count' => (int) $b->order_count];
        });

        return [
            'personas' => $withAge->where('age_group', '!=', 'unknown')->groupBy(fn ($b) => $b->age_group . '_' . $b->gender)->map(function ($group, $key) use ($totalCustomers) {
                [$ageGroup, $gender] = array_pad(explode('_', $key, 2), 2, 'unknown');
                return ['age_group' => $ageGroup, 'gender' => $gender, 'count' => $group->count(), 'percentage' => round($group->count() / $totalCustomers * 100, 1), 'avg_spend' => round($group->avg('total_spent'), 2), 'avg_orders' => round($group->avg('order_count'), 1), 'top_cities' => $group->whereNotNull('city')->countBy('city')->sortDesc()->take(3)->toArray(), 'label' => ''];
            })->sortByDesc('count')->values()->take(3)->map(function ($p, $i) { $p['label'] = ['Primary Persona', 'Secondary Persona', 'Tertiary Persona'][$i] ?? 'Other'; return $p; })->toArray(),
            'totals' => ['total_customers' => $totalCustomers, 'with_demographics' => $withAge->where('age_group', '!=', 'unknown')->count(), 'age_distribution' => $withAge->where('age_group', '!=', 'unknown')->countBy('age_group')->sortKeys()->toArray(), 'gender_overall' => $withAge->where('gender', '!=', 'unknown')->countBy('gender')->toArray()],
        ];
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
        if (empty($eventIds)) return ['events' => [], 'avg_sell_through' => 0, 'avg_checkin_rate' => 0, 'role_comparison' => [], 'customer_loyalty' => ['one_time' => 0, 'repeat' => 0, 'superfan' => 0, 'repeat_rate' => 0, 'total' => 0], 'superfan_details' => []];

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
                return ['id' => $e->id, 'title' => $title, 'date' => $e->event_date, 'venue' => $vn, 'city' => $e->venue_city, 'is_headliner' => (bool) $e->is_headliner, 'is_co_headliner' => (bool) $e->is_co_headliner, 'sold' => $sold, 'capacity' => $cap, 'sell_through' => $cap > 0 ? round($sold / $cap * 100, 1) : null, 'checkin_rate' => null, 'checkin_total' => 0, 'checkin_count' => 0];
            });

        // Check-in rates
        $checkinRates = DB::table('tickets as t')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where('t.status', 'valid')
            ->where(function ($q) use ($eventIds) { $q->whereIn('tt.event_id', $eventIds)->orWhereIn('t.event_id', $eventIds)->orWhereIn('t.marketplace_event_id', $eventIds); })
            ->select(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id) as resolved_event_id'), DB::raw('COUNT(t.id) as total_tickets'), DB::raw('SUM(CASE WHEN t.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) as checked_in'))
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
            ->map(fn ($group) => ['events' => $group->count(), 'avg_sold' => round($group->avg('sold'), 0), 'avg_sell_through' => round($group->whereNotNull('sell_through')->avg('sell_through'), 1), 'avg_checkin_rate' => round($group->whereNotNull('checkin_rate')->avg('checkin_rate'), 1)])->toArray();

        // Repeat customers
        $customerEvents = !empty($orderIds) ? DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->whereIn('o.id', $orderIds)
            ->select(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'), DB::raw('COUNT(DISTINCT COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)) as events_attended'))
            ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'))
            ->get() : collect();

        $loyalty = ['one_time' => $customerEvents->where('events_attended', 1)->count(), 'repeat' => $customerEvents->where('events_attended', 2)->count(), 'superfan' => $customerEvents->where('events_attended', '>=', 3)->count()];
        $totalBuyers = array_sum($loyalty);
        $loyalty['repeat_rate'] = $totalBuyers > 0 ? round(($loyalty['repeat'] + $loyalty['superfan']) / $totalBuyers * 100, 1) : 0;
        $loyalty['total'] = $totalBuyers;

        // Superfan details
        $superfanIds = $customerEvents->where('events_attended', '>=', 3)->pluck('buyer_id')->toArray();
        $superfanDetails = [];
        if (!empty($superfanIds) && !empty($orderIds)) {
            $superfanDetails = DB::table('orders as o')
                ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
                ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
                ->whereIn(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'), $superfanIds)
                ->whereIn('o.id', $orderIds)
                ->select(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'), DB::raw("COALESCE(mc.first_name, c.first_name, '') as first_name"), DB::raw("COALESCE(mc.last_name, c.last_name, '') as last_name"), DB::raw('COALESCE(mc.email, c.email, o.customer_email) as email'), DB::raw('COALESCE(mc.city, c.city) as city'), DB::raw('SUM(o.total) as total_spent'), DB::raw('COUNT(DISTINCT o.id) as orders'))
                ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'), DB::raw("COALESCE(mc.first_name, c.first_name, '')"), DB::raw("COALESCE(mc.last_name, c.last_name, '')"), DB::raw('COALESCE(mc.email, c.email, o.customer_email)'), DB::raw('COALESCE(mc.city, c.city)'))
                ->orderByDesc('total_spent')->limit(20)->get()
                ->map(function ($s) use ($customerEvents) {
                    $evAtt = $customerEvents->where('buyer_id', $s->buyer_id)->first()?->events_attended ?? 0;
                    return ['name' => trim($s->first_name . ' ' . $s->last_name) ?: '—', 'email' => $s->email ?? '—', 'city' => $s->city ?? '—', 'events' => (int) $evAtt, 'orders' => (int) $s->orders, 'total_spent' => round((float) $s->total_spent, 2)];
                })->toArray();
        }

        $allWithSt = collect($eventsPerf)->whereNotNull('sell_through');
        $allWithCi = collect($eventsPerf)->whereNotNull('checkin_rate');
        return ['events' => $eventsPerf, 'avg_sell_through' => round($allWithSt->avg('sell_through'), 1), 'avg_checkin_rate' => round($allWithCi->avg('checkin_rate'), 1), 'superfan_details' => $superfanDetails, 'role_comparison' => $roleStats, 'customer_loyalty' => $loyalty];
    }

    private function buildSalesIntelligence(array $eventIds, array $orderIds): array
    {
        $empty = ['channels' => [], 'purchase_timing' => [], 'avg_lead_days' => 0, 'price_sensitivity' => [], 'velocity_curves' => [], 'avg_revenue_per_event' => 0, 'total_revenue' => 0, 'fee_comparison' => null];
        if (empty($orderIds)) return $empty;

        // Channel breakdown
        $channels = DB::table('orders as o')
            ->whereIn('o.id', $orderIds)
            ->select('o.source', DB::raw('COUNT(DISTINCT o.id) as orders_count'), DB::raw('SUM(o.total) as revenue'), DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as unique_customers'))
            ->groupBy('o.source')
            ->get()->keyBy('source')->toArray();
        $totalChannelOrders = collect($channels)->sum('orders_count');
        foreach ($channels as &$ch) { $ch->pct = $totalChannelOrders > 0 ? round($ch->orders_count / $totalChannelOrders * 100, 1) : 0; }

        // Revenue
        $totalRevenue = DB::table('orders')->whereIn('id', $orderIds)->sum('total');
        $perEventRevenue = DB::table('orders')->whereIn('id', $orderIds)->whereNotNull('event_id')->select('event_id', DB::raw('SUM(total) as revenue'))->groupBy('event_id')->get();
        $totalEvents = $perEventRevenue->count() ?: count($eventIds);
        $avgRevenuePerEvent = $totalEvents > 0 ? round($totalRevenue / $totalEvents, 2) : 0;

        // Fee comparison
        $feeComparison = null;
        if ($this->record->min_fee_concert || $this->record->max_fee_concert) {
            $feeComparison = ['min_fee' => $this->record->min_fee_concert, 'max_fee' => $this->record->max_fee_concert, 'avg_revenue' => $avgRevenuePerEvent, 'in_range' => $avgRevenuePerEvent >= ($this->record->min_fee_concert ?? 0) && ($this->record->max_fee_concert ? $avgRevenuePerEvent <= $this->record->max_fee_concert : true)];
        }

        // Purchase lead time
        $leadTimes = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', function ($join) { $join->on('e.id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)')); })
            ->whereIn('o.id', array_slice($orderIds, 0, 5000))
            ->whereNotNull('e.event_date')
            ->select(DB::raw(DB::getDriverName() === 'pgsql' ? '(e.event_date::date - COALESCE(o.paid_at, o.created_at)::date) as days_before' : 'DATEDIFF(e.event_date, COALESCE(o.paid_at, o.created_at)) as days_before'))
            ->get()->pluck('days_before')->filter(fn ($d) => $d !== null && $d >= 0);

        $totalLead = $leadTimes->count();
        $purchaseTiming = [
            'last_minute' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d <= 1)->count() / $totalLead * 100, 1) : 0,
            'last_week' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 2 && $d <= 7)->count() / $totalLead * 100, 1) : 0,
            'last_month' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 8 && $d <= 30)->count() / $totalLead * 100, 1) : 0,
            'early_bird' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 31 && $d <= 90)->count() / $totalLead * 100, 1) : 0,
            'super_early' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d > 90)->count() / $totalLead * 100, 1) : 0,
        ];

        // Price sensitivity with revenue per ticket
        $priceData = DB::table('ticket_types as tt')->whereIn('tt.event_id', $eventIds)->where('tt.quota_total', '>', 0)->select('tt.price_cents', 'tt.quota_sold', 'tt.quota_total')->get();
        $priceBuckets = ['0-50' => ['t' => 0, 'c' => 0, 'rev' => 0], '50-100' => ['t' => 0, 'c' => 0, 'rev' => 0], '100-200' => ['t' => 0, 'c' => 0, 'rev' => 0], '200-500' => ['t' => 0, 'c' => 0, 'rev' => 0], '500+' => ['t' => 0, 'c' => 0, 'rev' => 0]];
        foreach ($priceData as $p) { $price = $p->price_cents / 100; $bucket = match (true) { $price < 50 => '0-50', $price < 100 => '50-100', $price < 200 => '100-200', $price < 500 => '200-500', default => '500+' }; $priceBuckets[$bucket]['t'] += $p->quota_sold; $priceBuckets[$bucket]['c'] += $p->quota_total; $priceBuckets[$bucket]['rev'] += $price * $p->quota_sold; }
        $priceSensitivity = [];
        foreach ($priceBuckets as $range => $d) { if ($d['c'] > 0) $priceSensitivity[] = ['range' => $range, 'tickets' => $d['t'], 'sell_through' => round($d['t'] / $d['c'] * 100, 1), 'revenue_per_ticket' => $d['t'] > 0 ? round($d['rev'] / $d['t'], 2) : 0, 'total_revenue' => round($d['rev'], 0)]; }

        // Sales velocity for last 5 events
        $lastEventIds = DB::table('events as e')->join('event_artist as ea', 'ea.event_id', '=', 'e.id')->where('ea.artist_id', $this->record->id)->whereNotNull('e.event_date')->where('e.event_date', '<', now())->orderByDesc('e.event_date')->limit(5)->pluck('e.id')->toArray();
        $velocityCurves = [];
        if (!empty($lastEventIds)) {
            foreach ($lastEventIds as $evId) {
                $ev = DB::table('events')->where('id', $evId)->select('title', 'event_date')->first();
                if (!$ev || !$ev->event_date) continue;
                $title = $ev->title;
                if ($title && str_starts_with($title, '{')) { $d = json_decode($title, true); $title = $d['en'] ?? $d['ro'] ?? reset($d) ?: $title; }
                $evDateStr = Carbon::parse($ev->event_date)->toDateString();
                $orderDates = DB::table('orders as o')->join('tickets as t', 't.order_id', '=', 'o.id')->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                    ->where(function ($q) use ($evId) { $q->where('tt.event_id', $evId)->orWhere('t.event_id', $evId)->orWhere('t.marketplace_event_id', $evId); })
                    ->whereIn('o.status', ['paid', 'confirmed', 'completed'])
                    ->selectRaw(DB::getDriverName() === 'pgsql' ? "GREATEST(0, ('{$evDateStr}'::date - COALESCE(o.paid_at, o.created_at)::date)) as days_before, COUNT(t.id) as cnt" : "GREATEST(0, DATEDIFF('{$evDateStr}', COALESCE(o.paid_at, o.created_at))) as days_before, COUNT(t.id) as cnt")
                    ->groupBy('days_before')->orderBy('days_before')->get();
                $total = $orderDates->sum('cnt');
                if ($total === 0) continue;
                $milestones = [90, 60, 30, 7, 1];
                $points = [];
                foreach ($milestones as $m) { $soldByThen = $orderDates->where('days_before', '>=', $m)->sum('cnt'); $points[] = ['days' => $m, 'pct' => round($soldByThen / $total * 100, 1)]; }
                $velocityCurves[] = ['event_name' => mb_substr($title, 0, 30), 'total_tickets' => $total, 'points' => $points];
            }
        }

        return ['channels' => $channels, 'purchase_timing' => $purchaseTiming, 'avg_lead_days' => round($leadTimes->avg() ?? 0, 1), 'price_sensitivity' => $priceSensitivity, 'velocity_curves' => $velocityCurves, 'avg_revenue_per_event' => $avgRevenuePerEvent, 'total_revenue' => $totalRevenue, 'fee_comparison' => $feeComparison];
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

        $expCityNames = $expansionCities->pluck('city')->toArray();
        $venuesInCities = DB::table('venues')
            ->whereIn('city', $expCityNames)
            ->select('id', 'name', 'city', 'capacity', 'capacity_total')
            ->orderByDesc('capacity')
            ->get()->groupBy('city');

        return $expansionCities->map(function ($row) use ($venuesInCities) {
            $venues = ($venuesInCities->get($row->city) ?? collect())->take(3)->map(function ($v) {
                $name = $v->name;
                if ($name && str_starts_with($name, '{')) { $d = json_decode($name, true); $name = $d['en'] ?? $d['ro'] ?? reset($d) ?: $name; }
                return ['name' => $name, 'capacity' => (int) ($v->capacity ?: $v->capacity_total ?: 0)];
            })->toArray();

            return [
                'city' => $row->city, 'country' => $row->country,
                'fan_count' => 0,
                'estimated_demand' => round((float) ($row->avg_attendance ?? 0)),
                'venues' => $venues,
                'similar_events' => (int) $row->similar_events,
                'similar_avg_attendance' => round((float) ($row->avg_attendance ?? 0)),
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
            ->leftJoin(DB::raw('(SELECT tt3.event_id, SUM(tt3.quota_sold * tt3.price_cents) / 100 as revenue_sold FROM ticket_types tt3 GROUP BY tt3.event_id) as rs'), 'rs.event_id', '=', 'e.id')
            ->select('e.id', 'e.title', 'e.event_date', 'v.name as venue_name', 'v.city as venue_city', 'v.capacity as venue_capacity', 'ea.is_headliner', 'ts.sold', 'ts.capacity', 'rs.revenue_sold')
            ->orderBy('e.event_date')
            ->limit(10)
            ->get();

        if ($upcoming->isEmpty()) return [];

        // Historical averages for comparison
        $pastStats = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as capacity FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $this->record->id)
            ->whereNotNull('e.event_date')->where('e.event_date', '<', now()->toDateString())
            ->select(DB::raw('AVG(ts.sold) as avg_sold'), DB::raw('AVG(CASE WHEN ts.capacity > 0 THEN ts.sold * 100.0 / ts.capacity ELSE NULL END) as avg_sell_through'))
            ->first();

        $histAvgSold = round((float) ($pastStats?->avg_sold ?? 0));
        $histAvgSt = round((float) ($pastStats?->avg_sell_through ?? 0), 1);

        return $upcoming->map(function ($e) use ($histAvgSold, $histAvgSt) {
            $title = $e->title;
            if ($title && str_starts_with($title, '{')) { $d = json_decode($title, true); $title = $d['en'] ?? $d['ro'] ?? reset($d) ?: $title; }
            $vn = $e->venue_name;
            if ($vn && str_starts_with($vn, '{')) { $d = json_decode($vn, true); $vn = $d['en'] ?? $d['ro'] ?? reset($d) ?: $vn; }
            $sold = (int) ($e->sold ?? 0); $cap = (int) ($e->capacity ?? 0);
            $st = $cap > 0 ? round($sold / $cap * 100, 1) : null;
            $daysUntil = $e->event_date ? max(0, now()->diffInDays(Carbon::parse($e->event_date), false)) : null;

            // Forecast
            $forecastSold = null;
            if ($daysUntil !== null && $daysUntil > 0 && $sold > 0) {
                $daysSelling = max(1, 90 - $daysUntil);
                $dailyRate = $sold / $daysSelling;
                $forecastSold = min($cap ?: 99999, (int) round($sold + ($dailyRate * $daysUntil)));
            }

            // Demand Score (0-100)
            $paceScore = 0; // 0-40: sale pace vs historical
            if ($histAvgSold > 0 && $sold > 0 && $daysUntil !== null) {
                $daysSelling = max(1, 90 - $daysUntil);
                $currentPace = $sold / $daysSelling;
                $histPace = $histAvgSold / 90;
                $paceScore = min(40, round(($currentPace / max(0.01, $histPace)) * 20));
            }
            $stScore = min(30, round(($st ?? 0) / 100 * 30)); // 0-30: current sell-through
            $capScore = $cap > 0 ? min(30, round(($sold / $cap) * 30)) : 0; // 0-30: capacity utilization
            $demandScore = $paceScore + $stScore + $capScore;
            $demandLabel = match (true) { $demandScore >= 75 => 'Hot', $demandScore >= 50 => 'Strong', $demandScore >= 25 => 'Moderate', default => 'Low' };

            return [
                'id' => $e->id, 'title' => $title, 'date' => $e->event_date,
                'venue' => $vn, 'city' => $e->venue_city,
                'venue_capacity' => (int) ($e->venue_capacity ?? 0),
                'is_headliner' => (bool) $e->is_headliner,
                'sold' => $sold, 'capacity' => $cap,
                'sell_through' => $st,
                'revenue_sold' => round((float) ($e->revenue_sold ?? 0), 2),
                'days_until' => $daysUntil,
                'hist_avg_sold' => $histAvgSold,
                'hist_avg_sell_through' => $histAvgSt,
                'forecast_sold' => $forecastSold,
                'demand_score' => $demandScore,
                'demand_label' => $demandLabel,
            ];
        })->toArray();
    }

    private function buildOpportunities(array $eventIds, array $orderIds): array
    {
        if (empty($eventIds)) return [];
        $artistId = $this->record->id;

        // Day of week performance
        $dayPerformance = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)->whereNotNull('e.event_date')
            ->select(
                DB::raw(DB::getDriverName() === 'pgsql' ? 'EXTRACT(DOW FROM e.event_date)::int + 1 as dow' : 'DAYOFWEEK(e.event_date) as dow'),
                DB::raw(DB::getDriverName() === 'pgsql' ? "TO_CHAR(e.event_date, 'Day') as day_name" : 'DAYNAME(e.event_date) as day_name'),
                DB::raw('COUNT(DISTINCT e.id) as events'), DB::raw('AVG(ts.sold) as avg_sold'),
                DB::raw('AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st'),
                DB::raw('AVG(ts.sold * (SELECT AVG(tt2.price_cents) FROM ticket_types tt2 WHERE tt2.event_id = e.id) / 100) as avg_revenue')
            )->groupBy('dow', 'day_name')->orderByRaw('avg_st DESC NULLS LAST')->get();

        // Month performance
        $monthPerformance = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)->whereNotNull('e.event_date')
            ->select(
                DB::raw(DB::getDriverName() === 'pgsql' ? 'EXTRACT(MONTH FROM e.event_date)::int as month_num' : 'MONTH(e.event_date) as month_num'),
                DB::raw(DB::getDriverName() === 'pgsql' ? "TO_CHAR(e.event_date, 'Month') as month_name" : 'MONTHNAME(e.event_date) as month_name'),
                DB::raw('COUNT(DISTINCT e.id) as events'), DB::raw('AVG(ts.sold) as avg_sold'),
                DB::raw('AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st')
            )->groupBy('month_num', 'month_name')->orderByRaw('avg_st DESC NULLS LAST')->get();

        // Price performance
        $pricePerformance = DB::table('ticket_types as tt')
            ->whereIn('tt.event_id', $eventIds)->where('tt.quota_total', '>', 0)->where('tt.price_cents', '>', 0)
            ->select(
                DB::raw("CASE WHEN tt.price_cents/100 < 50 THEN '0-50' WHEN tt.price_cents/100 < 100 THEN '50-100' WHEN tt.price_cents/100 < 150 THEN '100-150' WHEN tt.price_cents/100 < 200 THEN '150-200' WHEN tt.price_cents/100 < 300 THEN '200-300' ELSE '300+' END as price_range"),
                DB::raw('AVG(tt.price_cents/100) as avg_price'), DB::raw('SUM(tt.quota_sold) as total_sold'), DB::raw('SUM(tt.quota_total) as total_cap'),
                DB::raw('AVG(LEAST(tt.quota_sold * 1.0 / tt.quota_total, 1.0)) as avg_st')
            )->groupBy('price_range')->orderByRaw('avg_st DESC NULLS LAST')->get();

        // Lead time stats
        $leadTimeStats = [];
        if (!empty($orderIds)) {
            $leadTimes = DB::table('orders as o')->join('tickets as t', 't.order_id', '=', 'o.id')->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->join('events as e', function ($join) { $join->on('e.id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)')); })
                ->whereIn('o.id', array_slice($orderIds, 0, 5000))->whereNotNull('e.event_date')
                ->selectRaw(DB::getDriverName() === 'pgsql' ? 'GREATEST(0, (e.event_date::date - COALESCE(o.paid_at, o.created_at)::date)) as days_before' : 'GREATEST(0, DATEDIFF(e.event_date, COALESCE(o.paid_at, o.created_at))) as days_before')
                ->get()->pluck('days_before')->filter(fn ($d) => $d >= 0);
            if ($leadTimes->isNotEmpty()) {
                $sorted = $leadTimes->sort()->values(); $cnt = $sorted->count();
                $leadTimeStats = ['median' => round($leadTimes->median(), 0), 'p75' => round((float) ($sorted[(int) floor($cnt * 0.75)] ?? $sorted->last()), 0), 'p90' => round((float) ($sorted[(int) floor($cnt * 0.90)] ?? $sorted->last()), 0), 'avg' => round($leadTimes->avg(), 0), 'first_sale_avg' => round($leadTimes->max(), 0)];
            }
        }

        // Venue capacity performance
        $venueCapPerf = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')->join('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)->where('v.capacity', '>', 0)
            ->select(DB::raw("CASE WHEN v.capacity < 200 THEN '< 200' WHEN v.capacity < 500 THEN '200-500' WHEN v.capacity < 1000 THEN '500-1000' WHEN v.capacity < 2000 THEN '1000-2000' WHEN v.capacity < 5000 THEN '2000-5000' ELSE '5000+' END as cap_range"),
                DB::raw('COUNT(DISTINCT e.id) as events'), DB::raw('AVG(ts.sold) as avg_sold'), DB::raw('AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st'))
            ->groupBy('cap_range')->orderByRaw('avg_st DESC NULLS LAST')->get();

        // Build recommendations
        $recommendations = [];
        $bestDay = $dayPerformance->first(); $worstDay = $dayPerformance->last();
        if ($bestDay && $dayPerformance->count() >= 2) {
            $recommendations[] = ['icon' => '📅', 'category' => 'Scheduling', 'title' => "Best day: " . trim($bestDay->day_name), 'detail' => round((float) $bestDay->avg_st, 1) . "% avg sell-through across {$bestDay->events} events" . ($worstDay ? ". Avoid " . trim($worstDay->day_name) . " (" . round((float) $worstDay->avg_st, 1) . "%)" : ''), 'confidence' => $bestDay->events >= 3 ? 'high' : 'medium'];
        }
        $bestMonths = $monthPerformance->take(3);
        if ($bestMonths->isNotEmpty()) {
            $recommendations[] = ['icon' => '🗓️', 'category' => 'Seasonality', 'title' => "Best months: " . $bestMonths->pluck('month_name')->map(fn ($m) => trim($m))->join(', '), 'detail' => $bestMonths->map(fn ($m) => trim($m->month_name) . ' (' . round((float) $m->avg_st, 1) . '%)')->join(', '), 'confidence' => $bestMonths->sum('events') >= 5 ? 'high' : 'medium'];
        }
        $bestPrice = $pricePerformance->first();
        if ($bestPrice && $pricePerformance->count() >= 2) {
            $recommendations[] = ['icon' => '💰', 'category' => 'Pricing', 'title' => "Sweet spot: {$bestPrice->price_range} RON", 'detail' => round((float) $bestPrice->avg_st * 100, 1) . "% sell-through at avg " . round((float) $bestPrice->avg_price, 0) . " RON. Sold {$bestPrice->total_sold} tickets.", 'confidence' => $bestPrice->total_sold >= 50 ? 'high' : 'medium'];
        }
        if (!empty($leadTimeStats)) {
            $startPromo = $leadTimeStats['p90'] ?? $leadTimeStats['avg'] ?? 30;
            $recommendations[] = ['icon' => '📢', 'category' => 'Promotion', 'title' => "Start promoting {$startPromo} days before", 'detail' => "90% of purchases within {$startPromo}d. Median: {$leadTimeStats['median']}d. Earliest: {$leadTimeStats['first_sale_avg']}d.", 'confidence' => 'high'];
        }
        $bestVenueCap = $venueCapPerf->first();
        if ($bestVenueCap && $venueCapPerf->count() >= 2) {
            $recommendations[] = ['icon' => '🏟️', 'category' => 'Venue Size', 'title' => "Optimal: {$bestVenueCap->cap_range}", 'detail' => round((float) $bestVenueCap->avg_st, 1) . "% sell-through. Avg attendance: " . round((float) $bestVenueCap->avg_sold), 'confidence' => $bestVenueCap->events >= 3 ? 'high' : 'medium'];
        }
        $personas = $this->buildAudiencePersonas($orderIds);
        $topPersona = $personas['personas'][0] ?? null;
        if ($topPersona) {
            $topCity = !empty($topPersona['top_cities']) ? array_key_first($topPersona['top_cities']) : null;
            $recommendations[] = ['icon' => '🎯', 'category' => 'Target Audience', 'title' => "{$topPersona['age_group']}, {$topPersona['gender']} ({$topPersona['percentage']}%)", 'detail' => "Avg spend: " . number_format($topPersona['avg_spend'], 0) . " RON" . ($topCity ? ". Top city: {$topCity}" : ''), 'confidence' => $topPersona['count'] >= 50 ? 'high' : 'medium'];
        }

        return [
            'recommendations' => $recommendations,
            'day_performance' => $dayPerformance->map(fn ($d) => ['day' => $d->day_name, 'events' => (int) $d->events, 'avg_sold' => round((float) $d->avg_sold), 'avg_st' => round((float) ($d->avg_st ?? 0), 1)])->values()->toArray(),
            'month_performance' => $monthPerformance->map(fn ($m) => ['month' => $m->month_name, 'events' => (int) $m->events, 'avg_sold' => round((float) $m->avg_sold), 'avg_st' => round((float) ($m->avg_st ?? 0), 1)])->values()->toArray(),
            'price_performance' => $pricePerformance->map(fn ($p) => ['range' => $p->price_range, 'avg_price' => round((float) $p->avg_price, 0), 'total_sold' => (int) $p->total_sold, 'avg_st' => round((float) ($p->avg_st ?? 0) * 100, 1)])->values()->toArray(),
            'venue_cap_performance' => $venueCapPerf->map(fn ($v) => ['range' => $v->cap_range, 'events' => (int) $v->events, 'avg_sold' => round((float) $v->avg_sold), 'avg_st' => round((float) ($v->avg_st ?? 0), 1)])->values()->toArray(),
            'lead_time' => $leadTimeStats,
            'announcement_window' => $this->buildAnnouncementWindow($leadTimeStats, $leadTimes ?? collect()),
        ];
    }

    private function buildPerformanceHeatmap(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        $artistId = $this->record->id;

        $data = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->whereNotNull('e.event_date')
            ->where('ts.cap', '>', 0)
            ->select(
                DB::raw(DB::getDriverName() === 'pgsql' ? 'EXTRACT(DOW FROM e.event_date)::int as dow' : 'DAYOFWEEK(e.event_date) - 1 as dow'),
                DB::raw(DB::getDriverName() === 'pgsql' ? 'EXTRACT(MONTH FROM e.event_date)::int as mon' : 'MONTH(e.event_date) as mon'),
                DB::raw('AVG(LEAST(ts.sold * 100.0 / ts.cap, 100)) as avg_st'),
                DB::raw('COUNT(DISTINCT e.id) as cnt')
            )
            ->groupBy('dow', 'mon')
            ->get();

        // Build 7x12 matrix (dow 0-6, months 1-12)
        $matrix = [];
        $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        foreach ($data as $row) {
            $d = (int) $row->dow; // 0=Sun in PG (EXTRACT DOW), 1=Sun in MySQL (DAYOFWEEK-1)
            // Normalize to Mon=0, Sun=6
            if (DB::getDriverName() === 'pgsql') { $d = $d === 0 ? 6 : $d - 1; }
            $m = (int) $row->mon - 1; // 0-indexed
            if ($d >= 0 && $d <= 6 && $m >= 0 && $m <= 11) {
                $matrix[$d][$m] = ['st' => round((float) $row->avg_st, 1), 'cnt' => (int) $row->cnt];
            }
        }

        return ['matrix' => $matrix, 'days' => $days, 'months' => $months];
    }

    private function buildAnnouncementWindow(array $leadTimeStats, $leadTimes): array
    {
        if (empty($leadTimeStats) || $leadTimes->isEmpty()) return [];

        $p90 = $leadTimeStats['p90'] ?? 60;
        $median = $leadTimeStats['median'] ?? 14;
        $optimalAnnounce = $p90 + 14;

        // Weekly purchase distribution histogram
        $weeklyBuckets = [];
        foreach ($leadTimes as $d) {
            $week = min(12, (int) floor($d / 7));
            $weeklyBuckets[$week] = ($weeklyBuckets[$week] ?? 0) + 1;
        }
        ksort($weeklyBuckets);

        // Fill gaps
        $labels = []; $values = [];
        for ($w = 0; $w <= 12; $w++) {
            $labels[] = $w === 0 ? 'Event week' : ($w === 1 ? '1w before' : "{$w}w before");
            $values[] = $weeklyBuckets[$w] ?? 0;
        }

        return [
            'optimal_announce_days' => $optimalAnnounce,
            'p90_days' => $p90,
            'median_days' => $median,
            'labels' => array_reverse($labels),
            'values' => array_reverse($values),
            'announce_week_index' => min(12, (int) ceil($optimalAnnounce / 7)),
        ];
    }

    private function buildFanEngagementScore(array $orderIds): array
    {
        $artistId = $this->record->id;

        // 1. Favorites (0-25)
        $favCount = 0;
        try {
            $favCount = (int) DB::table('marketplace_customer_favorites')
                ->where('favoriteable_type', 'LIKE', '%Artist%')
                ->where('favoriteable_id', $artistId)
                ->count();
        } catch (\Exception $e) {}
        $favScore = min(25, round($favCount / 20)); // 500 favorites = 25

        // 2. Reviews (0-25)
        $avgRating = 0; $reviewCount = 0;
        try {
            $reviewData = DB::table('marketplace_customer_reviews as r')
                ->join('marketplace_events as me', 'me.id', '=', 'r.marketplace_event_id')
                ->join('events as e', 'e.id', '=', 'me.event_id')
                ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
                ->where('ea.artist_id', $artistId)
                ->where('r.status', 'approved')
                ->select(DB::raw('AVG(r.rating) as avg_rating'), DB::raw('COUNT(*) as cnt'))
                ->first();
            $avgRating = round((float) ($reviewData->avg_rating ?? 0), 1);
            $reviewCount = (int) ($reviewData->cnt ?? 0);
        } catch (\Exception $e) {}
        $reviewScore = min(25, round($avgRating * 5)); // 5.0 rating = 25

        // 3. Repeat rate (0-25) - from existing loyalty data
        $repeatRate = 0;
        if (!empty($orderIds)) {
            $customerEvents = DB::table('orders as o')
                ->join('tickets as t', 't.order_id', '=', 'o.id')
                ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->whereIn('o.id', $orderIds)
                ->select(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'), DB::raw('COUNT(DISTINCT COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)) as events_attended'))
                ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'))
                ->get();
            $total = $customerEvents->count();
            $repeaters = $customerEvents->where('events_attended', '>=', 2)->count();
            $repeatRate = $total > 0 ? round($repeaters / $total * 100, 1) : 0;
        }
        $loyaltyScore = min(25, round($repeatRate / 4)); // 100% repeat = 25

        // 4. Referrals (0-25)
        $refCount = 0;
        try {
            $refCount = (int) DB::table('customer_points')
                ->whereIn('customer_id', function ($q) use ($orderIds) {
                    $q->select('customer_id')->from('orders')->whereIn('id', array_slice($orderIds, 0, 1000))->whereNotNull('customer_id');
                })
                ->sum('referral_count');
        } catch (\Exception $e) {}
        $refScore = min(25, round($refCount / 4)); // 100 referrals = 25

        $totalScore = $favScore + $reviewScore + $loyaltyScore + $refScore;

        return [
            'score' => $totalScore,
            'label' => match (true) { $totalScore >= 75 => 'Excellent', $totalScore >= 50 => 'Good', $totalScore >= 25 => 'Growing', default => 'Emerging' },
            'components' => [
                'favorites' => ['raw' => $favCount, 'score' => $favScore, 'max' => 25],
                'reviews' => ['raw' => $avgRating, 'count' => $reviewCount, 'score' => $reviewScore, 'max' => 25],
                'loyalty' => ['raw' => $repeatRate, 'score' => $loyaltyScore, 'max' => 25],
                'referrals' => ['raw' => $refCount, 'score' => $refScore, 'max' => 25],
            ],
        ];
    }
}
