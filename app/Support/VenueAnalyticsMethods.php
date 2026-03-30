<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait VenueAnalyticsMethods
{
    private function venueEventIds(): array
    {
        return DB::table('events')->whereIn('venue_id', $this->venueIds)->pluck('id')->toArray();
    }

    private function venueOrderIds(array $eventIds, array $paidStatuses = ['paid', 'confirmed', 'completed']): array
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
            ->distinct()->pluck('o.id')->toArray();
    }

    private function computeVenueKpis(array $eventIds, array $orderIds): array
    {
        $totalEvents = count(array_unique($eventIds));
        $totalTickets = 0; $totalRevenue = 0;
        if (!empty($orderIds)) {
            $cs = DB::table('orders as o')->join('tickets as t', 't.order_id', '=', 'o.id')
                ->whereIn('o.id', $orderIds)
                ->select(DB::raw('COUNT(t.id) as total_tickets'), DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as unique_buyers'))
                ->first();
            $totalTickets = (int) ($cs->total_tickets ?? 0);
            $totalRevenue = round((float) DB::table('orders')->whereIn('id', $orderIds)->sum('total'), 2);
        }
        $avgOccupancy = 0;
        if (!empty($eventIds)) {
            $avgOccupancy = round((float) (DB::table('ticket_types')->whereIn('event_id', $eventIds)->where('quota_total', '>', 0)
                ->selectRaw('AVG(LEAST(quota_sold::float / quota_total, 1.0) * 100)')->value('avg') ?? 0), 1);
        }
        return [
            'total_events' => $totalEvents,
            'total_tickets' => $totalTickets,
            'total_revenue' => $totalRevenue,
            'avg_occupancy' => $avgOccupancy,
            'avg_revenue_per_event' => $totalEvents > 0 ? round($totalRevenue / $totalEvents, 2) : 0,
            'avg_ticket_price' => $totalTickets > 0 ? round($totalRevenue / $totalTickets, 2) : 0,
        ];
    }

    private function buildVenueYearlySeries(array $eventIds): array
    {
        if (empty($eventIds)) return [[], [], [], [], []];
        $raw = DB::table('events as e')
            ->leftJoin('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)
            ->where('e.event_date', '>=', now()->subMonths(12)->startOfMonth()->toDateString())
            ->selectRaw("TO_CHAR(e.event_date, 'YYYY-MM') as ym, COUNT(DISTINCT e.id) as ev, COALESCE(SUM(tt.quota_sold),0) as tix, COALESCE(SUM(tt.quota_sold * tt.price_cents / 100),0) as rev, AVG(CASE WHEN tt.quota_total > 0 THEN LEAST(tt.quota_sold::float / tt.quota_total, 1.0) * 100 ELSE NULL END) as occ")
            ->groupBy('ym')->orderBy('ym')->get()->keyBy('ym');

        $months = []; $evS = []; $txS = []; $rvS = []; $ocS = [];
        $cursor = now()->subMonths(11)->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $label = $cursor->format('M y');
            $months[] = $label;
            $row = $raw->get($key);
            $evS[] = (int) ($row->ev ?? 0);
            $txS[] = (int) ($row->tix ?? 0);
            $rvS[] = round((float) ($row->rev ?? 0), 0);
            $ocS[] = round((float) ($row->occ ?? 0), 1);
            $cursor->addMonth();
        }
        return [$months, $evS, $txS, $rvS, $ocS];
    }

    private function decodeJsonName(?string $val): string
    {
        if (!$val) return '—';
        if (str_starts_with($val, '{')) { $d = json_decode($val, true); return $d['en'] ?? $d['ro'] ?? reset($d) ?: $val; }
        return $val;
    }

    private function buildEventPerformanceTable(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        $events = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total>0 THEN tt2.quota_total ELSE 0 END) as capacity, SUM(tt2.quota_sold * tt2.price_cents / 100) as revenue FROM ticket_types tt2 GROUP BY tt2.event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->leftJoin(DB::raw("(SELECT ea.event_id, string_agg(a.name, ', ' ORDER BY ea.is_headliner DESC) as artist_names FROM event_artist ea JOIN artists a ON a.id = ea.artist_id GROUP BY ea.event_id) as ar"), 'ar.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)
            ->select('e.id', 'e.title', 'e.event_date', 'ar.artist_names', 'ts.sold', 'ts.capacity', 'ts.revenue')
            ->orderByDesc('e.event_date')->limit(50)->get();

        $checkins = DB::table('tickets as t')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where('t.status', 'valid')
            ->where(function ($q) use ($eventIds) { $q->whereIn('tt.event_id', $eventIds)->orWhereIn('t.event_id', $eventIds)->orWhereIn('t.marketplace_event_id', $eventIds); })
            ->selectRaw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id) as eid, COUNT(t.id) as tot, SUM(CASE WHEN t.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) as ci')
            ->groupBy('eid')->get()->keyBy('eid');

        return $events->map(function ($e) use ($checkins) {
            $sold = (int) ($e->sold ?? 0); $cap = (int) ($e->capacity ?? 0);
            $st = $cap > 0 ? round($sold / $cap * 100, 1) : null;
            $ci = $checkins->get($e->id);
            $ciRate = ($ci && $ci->tot > 0) ? round($ci->ci / $ci->tot * 100, 1) : null;
            $isPast = $e->event_date && $e->event_date < now()->toDateString();
            return [
                'id' => $e->id, 'title' => $this->decodeJsonName($e->title), 'date' => $e->event_date,
                'artists' => $e->artist_names ?? '—', 'sold' => $sold, 'capacity' => $cap,
                'sell_through' => $st, 'revenue' => round((float) ($e->revenue ?? 0), 0),
                'checkin_rate' => $ciRate, 'is_past' => $isPast,
            ];
        })->toArray();
    }

    private function buildRevenueBreakdown(array $eventIds, array $orderIds): array
    {
        $empty = ['revenue_by_genre' => [], 'revenue_by_channel' => [], 'revenue_by_day_type' => [], 'top_artists_by_revenue' => [], 'yoy' => []];
        if (empty($eventIds)) return $empty;

        $byGenre = DB::table('ticket_types as tt')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->join('artist_artist_genre as aag', 'aag.artist_id', '=', 'ea.artist_id')
            ->join('artist_genres as ag', 'ag.id', '=', 'aag.artist_genre_id')
            ->whereIn('e.id', $eventIds)
            ->selectRaw("ag.name as genre, COUNT(DISTINCT e.id) as events, SUM(tt.quota_sold * tt.price_cents / 100) as revenue")
            ->groupBy('ag.name')->orderByDesc('revenue')->limit(10)->get()
            ->map(function ($r) {
                $name = $this->decodeJsonName($r->genre);
                return ['genre' => $name, 'events' => (int) $r->events, 'revenue' => round((float) $r->revenue, 0)];
            })->toArray();

        $byChannel = [];
        if (!empty($orderIds)) {
            $byChannel = DB::table('orders')->whereIn('id', $orderIds)
                ->selectRaw("COALESCE(source, 'unknown') as source, COUNT(DISTINCT id) as orders, SUM(total) as revenue, COUNT(DISTINCT COALESCE(marketplace_customer_id, customer_id)) as customers")
                ->groupBy('source')->orderByDesc('revenue')->get()->toArray();
        }

        $byDayType = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold * price_cents / 100) as rev, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->whereNotNull('e.event_date')
            ->selectRaw("CASE WHEN EXTRACT(DOW FROM e.event_date) IN (0,5,6) THEN 'Weekend' ELSE 'Weekday' END as day_type, COUNT(DISTINCT e.id) as events, AVG(ts.rev) as avg_revenue, AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st")
            ->groupBy('day_type')->get()->map(fn ($r) => ['day_type' => $r->day_type, 'events' => (int) $r->events, 'avg_revenue' => round((float) ($r->avg_revenue ?? 0), 0), 'avg_st' => round((float) ($r->avg_st ?? 0), 1)])->toArray();

        $topArtists = DB::table('event_artist as ea')
            ->join('artists as a', 'a.id', '=', 'ea.artist_id')
            ->join(DB::raw("(SELECT event_id, SUM(quota_sold * price_cents / 100) as rev, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'ea.event_id')
            ->whereIn('ea.event_id', $eventIds)
            ->selectRaw("a.id, a.name, COUNT(DISTINCT ea.event_id) as events, SUM(ts.rev) as total_revenue, AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st")
            ->groupBy('a.id', 'a.name')->orderByDesc('total_revenue')->limit(10)->get()
            ->map(fn ($r) => ['name' => $r->name, 'events' => (int) $r->events, 'total_revenue' => round((float) $r->total_revenue, 0), 'avg_st' => round((float) ($r->avg_st ?? 0), 1)])->toArray();

        $last12 = (float) DB::table('ticket_types as tt')->join('events as e', 'e.id', '=', 'tt.event_id')
            ->whereIn('e.id', $eventIds)->where('e.event_date', '>=', now()->subYear()->toDateString())
            ->selectRaw('COALESCE(SUM(tt.quota_sold * tt.price_cents / 100), 0) as rev')->value('rev');
        $prev12 = (float) DB::table('ticket_types as tt')->join('events as e', 'e.id', '=', 'tt.event_id')
            ->whereIn('e.id', $eventIds)->where('e.event_date', '>=', now()->subYears(2)->toDateString())->where('e.event_date', '<', now()->subYear()->toDateString())
            ->selectRaw('COALESCE(SUM(tt.quota_sold * tt.price_cents / 100), 0) as rev')->value('rev');
        $yoy = ['last_12' => round($last12, 0), 'prev_12' => round($prev12, 0), 'change_pct' => $prev12 > 0 ? round(($last12 - $prev12) / $prev12 * 100, 1) : null];

        return ['revenue_by_genre' => $byGenre, 'revenue_by_channel' => $byChannel, 'revenue_by_day_type' => $byDayType, 'top_artists_by_revenue' => $topArtists, 'yoy' => $yoy];
    }

    private function buildPricingIntelligence(array $eventIds): array
    {
        if (empty($eventIds)) return ['price_buckets' => [], 'sweet_spot' => null, 'underpriced' => [], 'overpriced' => []];

        $priceData = DB::table('ticket_types as tt')->whereIn('tt.event_id', $eventIds)->where('tt.quota_total', '>', 0)
            ->select('tt.event_id', 'tt.price_cents', 'tt.quota_sold', 'tt.quota_total')->get();

        $buckets = ['0-50' => ['t' => 0, 'c' => 0, 'rev' => 0], '50-100' => ['t' => 0, 'c' => 0, 'rev' => 0], '100-200' => ['t' => 0, 'c' => 0, 'rev' => 0], '200-500' => ['t' => 0, 'c' => 0, 'rev' => 0], '500+' => ['t' => 0, 'c' => 0, 'rev' => 0]];
        foreach ($priceData as $p) {
            $price = $p->price_cents / 100;
            $bucket = match (true) { $price < 50 => '0-50', $price < 100 => '50-100', $price < 200 => '100-200', $price < 500 => '200-500', default => '500+' };
            $buckets[$bucket]['t'] += $p->quota_sold; $buckets[$bucket]['c'] += $p->quota_total; $buckets[$bucket]['rev'] += $price * $p->quota_sold;
        }
        $priceBuckets = [];
        $bestSt = 0; $sweetSpot = null;
        foreach ($buckets as $range => $d) {
            if ($d['c'] > 0) {
                $st = round($d['t'] / $d['c'] * 100, 1);
                $priceBuckets[] = ['range' => $range, 'tickets' => $d['t'], 'sell_through' => $st, 'total_revenue' => round($d['rev'], 0)];
                if ($st > $bestSt) { $bestSt = $st; $sweetSpot = $range; }
            }
        }

        // Under/over-priced events
        $eventPricing = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap, AVG(price_cents/100) as avg_price FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->where('ts.cap', '>', 50)
            ->select('e.id', 'e.title', 'e.event_date', 'ts.sold', 'ts.cap', 'ts.avg_price')
            ->get();

        $underpriced = $eventPricing->filter(fn ($e) => $e->cap > 0 && ($e->sold / $e->cap) > 0.9)
            ->take(5)->map(fn ($e) => ['title' => $this->decodeJsonName($e->title), 'date' => $e->event_date, 'sell_through' => round($e->sold / $e->cap * 100, 1), 'avg_price' => round((float) $e->avg_price, 0)])->values()->toArray();

        $overpriced = $eventPricing->filter(fn ($e) => $e->cap > 0 && ($e->sold / $e->cap) < 0.3)
            ->take(5)->map(fn ($e) => ['title' => $this->decodeJsonName($e->title), 'date' => $e->event_date, 'sell_through' => round($e->sold / $e->cap * 100, 1), 'avg_price' => round((float) $e->avg_price, 0)])->values()->toArray();

        return ['price_buckets' => $priceBuckets, 'sweet_spot' => $sweetSpot, 'underpriced' => $underpriced, 'overpriced' => $overpriced];
    }

    private function buildVenueAudiencePersonas(array $orderIds): array
    {
        if (empty($orderIds)) return ['personas' => [], 'totals' => ['total_customers' => 0, 'with_demographics' => 0, 'age_distribution' => [], 'gender_overall' => []]];
        $buyers = DB::table('orders as o')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereIn('o.id', array_slice($orderIds, 0, 5000))
            ->select(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'), DB::raw('COALESCE(mc.birth_date, c.date_of_birth) as birth_date'), 'mc.gender', DB::raw('COALESCE(mc.city, c.city) as city'), DB::raw('SUM(o.total) as total_spent'), DB::raw('COUNT(DISTINCT o.id) as order_count'))
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

    private function buildVenueCustomerLoyalty(array $eventIds, array $orderIds): array
    {
        $empty = ['one_time' => 0, 'repeat' => 0, 'regulars' => 0, 'superfan' => 0, 'repeat_rate' => 0, 'total' => 0, 'superfan_details' => []];
        if (empty($orderIds)) return $empty;

        $customerEvents = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->whereIn('o.id', array_slice($orderIds, 0, 5000))
            ->select(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'), DB::raw('COUNT(DISTINCT COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)) as events_attended'))
            ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'))
            ->get();

        $loyalty = [
            'one_time' => $customerEvents->where('events_attended', 1)->count(),
            'repeat' => $customerEvents->where('events_attended', 2)->count(),
            'regulars' => $customerEvents->whereBetween('events_attended', [3, 5])->count(),
            'superfan' => $customerEvents->where('events_attended', '>=', 6)->count(),
        ];
        $total = array_sum($loyalty);
        $loyalty['repeat_rate'] = $total > 0 ? round(($loyalty['repeat'] + $loyalty['regulars'] + $loyalty['superfan']) / $total * 100, 1) : 0;
        $loyalty['total'] = $total;

        $superfanIds = $customerEvents->where('events_attended', '>=', 3)->pluck('buyer_id')->toArray();
        $superfanDetails = [];
        if (!empty($superfanIds)) {
            $superfanDetails = DB::table('orders as o')
                ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
                ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
                ->whereIn(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'), $superfanIds)
                ->whereIn('o.id', array_slice($orderIds, 0, 5000))
                ->select(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'), DB::raw("COALESCE(mc.first_name, c.first_name, '') as first_name"), DB::raw("COALESCE(mc.last_name, c.last_name, '') as last_name"), DB::raw('COALESCE(mc.email, c.email, o.customer_email) as email'), DB::raw('COALESCE(mc.city, c.city) as city'), DB::raw('SUM(o.total) as total_spent'), DB::raw('COUNT(DISTINCT o.id) as orders'))
                ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'), DB::raw("COALESCE(mc.first_name, c.first_name, '')"), DB::raw("COALESCE(mc.last_name, c.last_name, '')"), DB::raw('COALESCE(mc.email, c.email, o.customer_email)'), DB::raw('COALESCE(mc.city, c.city)'))
                ->orderByDesc('total_spent')->limit(20)->get()
                ->map(function ($s) use ($customerEvents) {
                    $evAtt = $customerEvents->where('buyer_id', $s->buyer_id)->first()?->events_attended ?? 0;
                    return ['name' => trim($s->first_name . ' ' . $s->last_name) ?: '—', 'email' => $s->email ?? '—', 'city' => $s->city ?? '—', 'events' => (int) $evAtt, 'orders' => (int) $s->orders, 'total_spent' => round((float) $s->total_spent, 2)];
                })->toArray();
        }
        $loyalty['superfan_details'] = $superfanDetails;
        return $loyalty;
    }

    private function buildGeographicOrigin(array $orderIds): array
    {
        if (empty($orderIds)) return ['cities' => [], 'out_of_town_ratio' => 0];
        $venueCity = $this->venue->city ?? null;

        $cities = DB::table('orders as o')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereIn('o.id', array_slice($orderIds, 0, 5000))
            ->whereNotNull(DB::raw('COALESCE(mc.city, c.city)'))
            ->selectRaw("COALESCE(mc.city, c.city) as city, COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as customer_count, SUM(o.total) as total_revenue, AVG(o.total) as avg_spend")
            ->groupBy(DB::raw('COALESCE(mc.city, c.city)'))
            ->orderByDesc('customer_count')->limit(20)->get()
            ->map(fn ($r) => ['city' => $r->city, 'customer_count' => (int) $r->customer_count, 'total_revenue' => round((float) $r->total_revenue, 0), 'avg_spend' => round((float) $r->avg_spend, 2)])->toArray();

        $totalCust = collect($cities)->sum('customer_count');
        $localCust = collect($cities)->where('city', $venueCity)->sum('customer_count');
        $outOfTown = $totalCust > 0 ? round(($totalCust - $localCust) / $totalCust * 100, 1) : 0;

        return ['cities' => $cities, 'out_of_town_ratio' => $outOfTown];
    }

    private function buildArtistPerformanceAtVenue(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        return DB::table('event_artist as ea')
            ->join('artists as a', 'a.id', '=', 'ea.artist_id')
            ->join(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap, SUM(quota_sold * price_cents / 100) as rev FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'ea.event_id')
            ->whereIn('ea.event_id', $eventIds)
            ->selectRaw("a.id, a.name, COUNT(DISTINCT ea.event_id) as events_count, SUM(ts.sold) as total_tickets, AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_sell_through, AVG(ts.rev) as avg_revenue, MAX(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as best_sell_through")
            ->groupBy('a.id', 'a.name')->orderByDesc(DB::raw('SUM(ts.rev)'))->limit(30)->get()
            ->map(fn ($r) => ['artist_id' => $r->id, 'artist_name' => $r->name, 'events_count' => (int) $r->events_count, 'total_tickets' => (int) $r->total_tickets, 'avg_sell_through' => round((float) ($r->avg_sell_through ?? 0), 1), 'avg_revenue' => round((float) ($r->avg_revenue ?? 0), 0), 'best_sell_through' => round((float) ($r->best_sell_through ?? 0), 1)])->toArray();
    }

    private function buildGenrePerformance(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        return DB::table('event_artist as ea')
            ->join('artist_artist_genre as aag', 'aag.artist_id', '=', 'ea.artist_id')
            ->join('artist_genres as ag', 'ag.id', '=', 'aag.artist_genre_id')
            ->join(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap, SUM(quota_sold * price_cents / 100) as rev, AVG(price_cents / 100) as avg_price FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'ea.event_id')
            ->whereIn('ea.event_id', $eventIds)
            ->selectRaw("ag.name as genre, COUNT(DISTINCT ea.event_id) as events_count, AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_sell_through, AVG(ts.rev) as avg_revenue, AVG(ts.avg_price) as avg_ticket_price, SUM(ts.sold) as total_tickets")
            ->groupBy('ag.name')->orderByRaw('avg_sell_through DESC NULLS LAST')->get()
            ->map(fn ($r) => ['genre' => $this->decodeJsonName($r->genre), 'events_count' => (int) $r->events_count, 'avg_sell_through' => round((float) ($r->avg_sell_through ?? 0), 1), 'avg_revenue' => round((float) ($r->avg_revenue ?? 0), 0), 'avg_ticket_price' => round((float) ($r->avg_ticket_price ?? 0), 0), 'total_tickets' => (int) $r->total_tickets])->toArray();
    }

    private function buildNeverPlayedArtists(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        $venueCity = $this->venue->city ?? null;
        if (!$venueCity) return [];

        $playedArtistIds = DB::table('event_artist')->whereIn('event_id', $eventIds)->pluck('artist_id')->unique()->toArray();
        if (empty($playedArtistIds)) return [];

        $topGenreIds = DB::table('artist_artist_genre')->whereIn('artist_id', $playedArtistIds)->pluck('artist_genre_id')->unique()->toArray();
        if (empty($topGenreIds)) return [];

        $candidateIds = DB::table('artist_artist_genre')->whereIn('artist_genre_id', $topGenreIds)
            ->whereNotIn('artist_id', $playedArtistIds)->pluck('artist_id')->unique()->take(200)->toArray();
        if (empty($candidateIds)) return [];

        return DB::table('event_artist as ea')
            ->join('artists as a', 'a.id', '=', 'ea.artist_id')
            ->join('events as e', 'e.id', '=', 'ea.event_id')
            ->join('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('ea.artist_id', $candidateIds)
            ->whereRaw('LOWER(v.city) = LOWER(?)', [$venueCity])
            ->selectRaw("a.id, a.name, COUNT(DISTINCT e.id) as city_events, AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_sell_through, AVG(ts.sold) as avg_sold")
            ->groupBy('a.id', 'a.name')
            ->havingRaw('AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) > 40')
            ->orderByDesc('avg_sell_through')->limit(15)->get()
            ->map(fn ($r) => ['artist_name' => $r->name, 'city_events' => (int) $r->city_events, 'avg_sell_through' => round((float) ($r->avg_sell_through ?? 0), 1), 'estimated_draw' => round((float) ($r->avg_sold ?? 0))])->toArray();
    }

    private function buildSchedulingHeatmap(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        $data = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->whereNotNull('e.event_date')->where('ts.cap', '>', 0)
            ->selectRaw("EXTRACT(DOW FROM e.event_date)::int as dow, EXTRACT(MONTH FROM e.event_date)::int as mon, AVG(LEAST(ts.sold * 100.0 / ts.cap, 100)) as avg_st, COUNT(DISTINCT e.id) as cnt")
            ->groupBy('dow', 'mon')->get();

        $matrix = []; $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        foreach ($data as $row) {
            $d = (int) $row->dow; $d = $d === 0 ? 6 : $d - 1;
            $m = (int) $row->mon - 1;
            if ($d >= 0 && $d <= 6 && $m >= 0 && $m <= 11) { $matrix[$d][$m] = ['st' => round((float) $row->avg_st, 1), 'cnt' => (int) $row->cnt]; }
        }
        return ['matrix' => $matrix, 'days' => $days, 'months' => $months];
    }

    private function buildDayOfWeekAnalysis(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        return DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap, SUM(quota_sold * price_cents / 100) as rev FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->whereNotNull('e.event_date')
            ->selectRaw("EXTRACT(DOW FROM e.event_date)::int as dow, TO_CHAR(e.event_date, 'Day') as day_name, COUNT(DISTINCT e.id) as events, AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st, AVG(ts.rev) as avg_revenue, AVG(ts.sold) as avg_tickets")
            ->groupBy('dow', 'day_name')->orderByRaw('avg_st DESC NULLS LAST')->get()
            ->map(fn ($r) => ['day' => trim($r->day_name), 'events' => (int) $r->events, 'avg_sell_through' => round((float) ($r->avg_st ?? 0), 1), 'avg_revenue' => round((float) ($r->avg_revenue ?? 0), 0), 'avg_tickets' => round((float) ($r->avg_tickets ?? 0))])->toArray();
    }

    private function buildSeasonalityAnalysis(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        $monthData = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap, SUM(quota_sold * price_cents / 100) as rev FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->whereNotNull('e.event_date')
            ->selectRaw("EXTRACT(MONTH FROM e.event_date)::int as month_num, TO_CHAR(e.event_date, 'Month') as month_name, COUNT(DISTINCT e.id) as events, COUNT(DISTINCT e.event_date::date) as event_days, AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st, AVG(ts.rev) as avg_revenue")
            ->groupBy('month_num', 'month_name')->orderByRaw('avg_st DESC NULLS LAST')->get();

        return $monthData->map(function ($r) {
            $daysInMonth = 30;
            return ['month' => trim($r->month_name), 'month_num' => (int) $r->month_num, 'events' => (int) $r->events, 'avg_sell_through' => round((float) ($r->avg_st ?? 0), 1), 'avg_revenue' => round((float) ($r->avg_revenue ?? 0), 0), 'idle_days' => max(0, $daysInMonth - (int) $r->event_days)];
        })->toArray();
    }

    private function buildIdleDaysAnalysis(array $eventIds): array
    {
        $eventDates = DB::table('events')->whereIn('venue_id', $this->venueIds)
            ->where('event_date', '>=', now()->subYear()->toDateString())
            ->pluck('event_date')->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))->unique()->toArray();

        $idleWeekend = 0; $idleByMonth = [];
        $cursor = now()->subYear()->startOfDay();
        $end = now()->endOfDay();
        while ($cursor <= $end) {
            $dow = (int) $cursor->dayOfWeek;
            if (in_array($dow, [0, 5, 6])) { // Fri=5, Sat=6, Sun=0
                $dateStr = $cursor->format('Y-m-d');
                if (!in_array($dateStr, $eventDates)) {
                    $idleWeekend++;
                    $monthKey = $cursor->format('M y');
                    $idleByMonth[$monthKey] = ($idleByMonth[$monthKey] ?? 0) + 1;
                }
            }
            $cursor->addDay();
        }

        $avgRevPerEvent = 0;
        if (!empty($eventIds)) {
            $totalRev = (float) DB::table('ticket_types')->whereIn('event_id', $eventIds)->selectRaw('SUM(quota_sold * price_cents / 100) as rev')->value('rev');
            $totalEv = count(array_unique($eventIds));
            $avgRevPerEvent = $totalEv > 0 ? round($totalRev / $totalEv, 0) : 0;
        }

        return [
            'total_idle_weekend_days' => $idleWeekend,
            'avg_revenue_per_event' => $avgRevPerEvent,
            'estimated_lost_revenue' => $idleWeekend * $avgRevPerEvent,
            'idle_by_month' => $idleByMonth,
        ];
    }

    private function buildSalesIntelligence(array $eventIds, array $orderIds): array
    {
        $empty = ['purchase_timing' => [], 'avg_lead_days' => 0, 'velocity_curves' => [], 'price_sensitivity' => [], 'optimal_frequency' => []];
        if (empty($orderIds)) return $empty;

        // Purchase lead time
        $leadTimes = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', function ($join) { $join->on('e.id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)')); })
            ->whereIn('o.id', array_slice($orderIds, 0, 5000))->whereNotNull('e.event_date')
            ->selectRaw("(e.event_date::date - COALESCE(o.paid_at, o.created_at)::date) as days_before")
            ->get()->pluck('days_before')->filter(fn ($d) => $d !== null && $d >= 0);

        $totalLead = $leadTimes->count();
        $purchaseTiming = [
            'last_minute' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d <= 1)->count() / $totalLead * 100, 1) : 0,
            'last_week' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 2 && $d <= 7)->count() / $totalLead * 100, 1) : 0,
            'last_month' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 8 && $d <= 30)->count() / $totalLead * 100, 1) : 0,
            'early_bird' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d >= 31 && $d <= 90)->count() / $totalLead * 100, 1) : 0,
            'super_early' => $totalLead > 0 ? round($leadTimes->filter(fn ($d) => $d > 90)->count() / $totalLead * 100, 1) : 0,
        ];

        $sorted = $leadTimes->sort()->values(); $cnt = $sorted->count();
        $leadTimeStats = $cnt > 0 ? [
            'median' => round($leadTimes->median(), 0),
            'p75' => round((float) ($sorted[(int) floor($cnt * 0.75)] ?? $sorted->last()), 0),
            'p90' => round((float) ($sorted[(int) floor($cnt * 0.90)] ?? $sorted->last()), 0),
            'avg' => round($leadTimes->avg(), 0),
        ] : [];

        // Velocity curves for last 5 events
        $lastEventIds = DB::table('events')->whereIn('venue_id', $this->venueIds)->whereNotNull('event_date')
            ->where('event_date', '<', now())->orderByDesc('event_date')->limit(5)->pluck('id')->toArray();
        $velocityCurves = [];
        foreach ($lastEventIds as $evId) {
            $ev = DB::table('events')->where('id', $evId)->select('title', 'event_date')->first();
            if (!$ev || !$ev->event_date) continue;
            $evDateStr = Carbon::parse($ev->event_date)->toDateString();
            $orderDates = DB::table('orders as o')->join('tickets as t', 't.order_id', '=', 'o.id')->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->where(function ($q) use ($evId) { $q->where('tt.event_id', $evId)->orWhere('t.event_id', $evId)->orWhere('t.marketplace_event_id', $evId); })
                ->whereIn('o.status', ['paid', 'confirmed', 'completed'])
                ->selectRaw("GREATEST(0, ('{$evDateStr}'::date - COALESCE(o.paid_at, o.created_at)::date)) as days_before, COUNT(t.id) as cnt")
                ->groupBy('days_before')->orderBy('days_before')->get();
            $total = $orderDates->sum('cnt');
            if ($total === 0) continue;
            $milestones = [90, 60, 30, 7, 1];
            $points = [];
            foreach ($milestones as $m) { $soldByThen = $orderDates->where('days_before', '>=', $m)->sum('cnt'); $points[] = ['days' => $m, 'pct' => round($soldByThen / $total * 100, 1)]; }
            $velocityCurves[] = ['event_name' => mb_substr($this->decodeJsonName($ev->title), 0, 30), 'total_tickets' => $total, 'points' => $points];
        }

        // Optimal event frequency
        $optimalFreq = [];
        if (!empty($eventIds)) {
            $weeklyEvents = DB::table('events as e')
                ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
                ->whereIn('e.id', $eventIds)->whereNotNull('e.event_date')->where('ts.cap', '>', 0)
                ->selectRaw("DATE_TRUNC('week', e.event_date) as week_start, COUNT(DISTINCT e.id) as events_in_week, AVG(LEAST(ts.sold * 100.0 / ts.cap, 100)) as avg_st")
                ->groupBy('week_start')->get();

            $byFreq = $weeklyEvents->groupBy(fn ($w) => match (true) { $w->events_in_week == 1 => '1/week', $w->events_in_week == 2 => '2/week', default => '3+/week' });
            foreach ($byFreq as $freq => $weeks) {
                $optimalFreq[] = ['frequency' => $freq, 'weeks' => $weeks->count(), 'avg_sell_through' => round($weeks->avg('avg_st'), 1)];
            }
        }

        return [
            'purchase_timing' => $purchaseTiming, 'avg_lead_days' => round($leadTimes->avg() ?? 0, 1),
            'lead_time_stats' => $leadTimeStats, 'velocity_curves' => $velocityCurves,
            'optimal_frequency' => $optimalFreq,
        ];
    }

    private function buildOpportunities(array $eventIds, array $orderIds): array
    {
        if (empty($eventIds)) return ['recommendations' => []];
        $recommendations = [];

        // a) Best day
        $dayPerf = $this->buildDayOfWeekAnalysis($eventIds);
        $bestDay = $dayPerf[0] ?? null; $worstDay = end($dayPerf) ?: null;
        if ($bestDay && count($dayPerf) >= 2) {
            $recommendations[] = ['category' => 'Scheduling', 'title' => "Best day: {$bestDay['day']}", 'detail' => "{$bestDay['avg_sell_through']}% avg sell-through across {$bestDay['events']} events" . ($worstDay ? ". Avoid {$worstDay['day']} ({$worstDay['avg_sell_through']}%)" : ''), 'confidence' => $bestDay['events'] >= 3 ? 'high' : 'medium'];
        }

        // b) Best months
        $seasonality = $this->buildSeasonalityAnalysis($eventIds);
        $bestMonths = array_slice($seasonality, 0, 3);
        if (!empty($bestMonths)) {
            $recommendations[] = ['category' => 'Seasonality', 'title' => "Best months: " . implode(', ', array_column($bestMonths, 'month')), 'detail' => implode(', ', array_map(fn ($m) => "{$m['month']} ({$m['avg_sell_through']}%)", $bestMonths)), 'confidence' => array_sum(array_column($bestMonths, 'events')) >= 5 ? 'high' : 'medium'];
        }

        // c) Pricing
        $pricing = $this->buildPricingIntelligence($eventIds);
        if ($pricing['sweet_spot']) {
            $recommendations[] = ['category' => 'Pricing', 'title' => "Sweet spot: {$pricing['sweet_spot']} RON", 'detail' => "Highest sell-through in this price range. Consider pricing new events here.", 'confidence' => 'high'];
        }

        // d) Promotion window
        $sales = $this->buildSalesIntelligence($eventIds, $orderIds);
        $p90 = $sales['lead_time_stats']['p90'] ?? null;
        if ($p90) {
            $startPromo = $p90 + 14;
            $recommendations[] = ['category' => 'Promotion', 'title' => "Start promoting {$startPromo} days before", 'detail' => "90% of purchases within {$p90}d. Median: " . ($sales['lead_time_stats']['median'] ?? '—') . "d.", 'confidence' => 'high'];
        }

        // e) Best genre
        $genres = $this->buildGenrePerformance($eventIds);
        $bestGenre = $genres[0] ?? null;
        if ($bestGenre && count($genres) >= 2) {
            $recommendations[] = ['category' => 'Genre', 'title' => "Top genre: {$bestGenre['genre']}", 'detail' => "{$bestGenre['avg_sell_through']}% sell-through, avg revenue {$bestGenre['avg_revenue']} RON across {$bestGenre['events_count']} events", 'confidence' => $bestGenre['events_count'] >= 3 ? 'high' : 'medium'];
        }

        // f) Idle revenue
        $idle = $this->buildIdleDaysAnalysis($eventIds);
        if ($idle['total_idle_weekend_days'] > 10 && $idle['avg_revenue_per_event'] > 0) {
            $recommendations[] = ['category' => 'Revenue Opportunity', 'title' => "{$idle['total_idle_weekend_days']} idle weekend days", 'detail' => "Estimated lost revenue: " . number_format($idle['estimated_lost_revenue'], 0) . " RON ({$idle['total_idle_weekend_days']} days × " . number_format($idle['avg_revenue_per_event'], 0) . " RON avg/event)", 'confidence' => 'high'];
        }

        // g) Target audience
        $personas = $this->buildVenueAudiencePersonas($orderIds);
        $topPersona = $personas['personas'][0] ?? null;
        if ($topPersona) {
            $topCity = !empty($topPersona['top_cities']) ? array_key_first($topPersona['top_cities']) : null;
            $recommendations[] = ['category' => 'Target Audience', 'title' => "{$topPersona['age_group']}, {$topPersona['gender']} ({$topPersona['percentage']}%)", 'detail' => "Avg spend: " . number_format($topPersona['avg_spend'], 0) . " RON" . ($topCity ? ". Top city: {$topCity}" : ''), 'confidence' => $topPersona['count'] >= 50 ? 'high' : 'medium'];
        }

        // h) Repeat activation
        $loyalty = $this->buildVenueCustomerLoyalty($eventIds, $orderIds);
        if ($loyalty['total'] > 20 && $loyalty['repeat_rate'] < 20) {
            $potentialReturn = round($loyalty['one_time'] * 0.15);
            $recommendations[] = ['category' => 'Loyalty', 'title' => "Low repeat rate: {$loyalty['repeat_rate']}%", 'detail' => "{$loyalty['one_time']} one-time buyers. If 15% return = +{$potentialReturn} extra customers.", 'confidence' => 'high'];
        }

        // i) Under-pricing
        if (!empty($pricing['underpriced'])) {
            $recommendations[] = ['category' => 'Pricing Alert', 'title' => count($pricing['underpriced']) . " events sold out (>90%)", 'detail' => "Consider raising prices. Events: " . implode(', ', array_map(fn ($e) => mb_substr($e['title'], 0, 20), array_slice($pricing['underpriced'], 0, 3))), 'confidence' => 'high'];
        }

        // j) Artist suggestions
        $neverPlayed = $this->buildNeverPlayedArtists($eventIds);
        if (!empty($neverPlayed)) {
            $top3 = array_slice($neverPlayed, 0, 3);
            $recommendations[] = ['category' => 'Artist Suggestions', 'title' => "Artists to consider booking", 'detail' => implode(', ', array_map(fn ($a) => "{$a['artist_name']} ({$a['avg_sell_through']}% ST, {$a['city_events']} events in city)", $top3)), 'confidence' => count($neverPlayed) >= 5 ? 'high' : 'medium'];
        }

        return ['recommendations' => $recommendations];
    }

    private function buildPromotionPlanner(array $eventIds, array $orderIds): array
    {
        $empty = ['announcement_window' => [], 'ad_budget' => [], 'platform_strategy' => [], 'personas_for_targeting' => [], 'top_genres' => []];
        if (empty($orderIds)) return $empty;

        // Announcement window (same logic as artist)
        $leadTimes = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', function ($join) { $join->on('e.id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)')); })
            ->whereIn('o.id', array_slice($orderIds, 0, 5000))->whereNotNull('e.event_date')
            ->selectRaw("GREATEST(0, (e.event_date::date - COALESCE(o.paid_at, o.created_at)::date)) as days_before")
            ->get()->pluck('days_before')->filter(fn ($d) => $d >= 0);

        $sorted = $leadTimes->sort()->values(); $cnt = $sorted->count();
        $leadStats = $cnt > 0 ? [
            'median' => round($leadTimes->median(), 0),
            'p90' => round((float) ($sorted[(int) floor($cnt * 0.90)] ?? $sorted->last()), 0),
        ] : ['median' => 14, 'p90' => 45];

        $p90 = $leadStats['p90'];
        $optimalAnnounce = $p90 + 14;

        $weeklyBuckets = [];
        foreach ($leadTimes as $d) { $week = min(12, (int) floor($d / 7)); $weeklyBuckets[$week] = ($weeklyBuckets[$week] ?? 0) + 1; }
        ksort($weeklyBuckets);
        $labels = []; $values = [];
        for ($w = 0; $w <= 12; $w++) { $labels[] = $w === 0 ? 'Event week' : ($w === 1 ? '1w before' : "{$w}w before"); $values[] = $weeklyBuckets[$w] ?? 0; }

        $announcementWindow = [
            'optimal_announce_days' => $optimalAnnounce, 'p90_days' => $p90, 'median_days' => $leadStats['median'],
            'labels' => array_reverse($labels), 'values' => array_reverse($values),
            'announce_week_index' => min(12, (int) ceil($optimalAnnounce / 7)),
        ];

        // Ad budget
        $totalRev = (float) DB::table('ticket_types')->whereIn('event_id', $eventIds)->selectRaw('SUM(quota_sold * price_cents / 100) as rev')->value('rev');
        $totalEv = count(array_unique($eventIds));
        $avgRevPerEvent = $totalEv > 0 ? round($totalRev / $totalEv, 0) : 0;
        $recommendedBudget = round($avgRevPerEvent * 0.12, 0);

        $adBudget = [
            'estimated_revenue' => $avgRevPerEvent,
            'recommended_budget' => $recommendedBudget,
            'budget_phases' => [
                ['phase' => 'Announce', 'days_range' => '60-45 days', 'pct' => 20, 'amount' => round($recommendedBudget * 0.20)],
                ['phase' => 'Peak', 'days_range' => '45-14 days', 'pct' => 40, 'amount' => round($recommendedBudget * 0.40)],
                ['phase' => 'Urgency', 'days_range' => '14-7 days', 'pct' => 25, 'amount' => round($recommendedBudget * 0.25)],
                ['phase' => 'Last Call', 'days_range' => '7-0 days', 'pct' => 15, 'amount' => round($recommendedBudget * 0.15)],
            ],
        ];

        // Personas for targeting
        $personas = $this->buildVenueAudiencePersonas($orderIds);
        $primaryPersona = $personas['personas'][0] ?? null;
        $primaryAge = $primaryPersona['age_group'] ?? '25-34';
        $isYoungAudience = in_array($primaryAge, ['<18', '18-24', '25-34']);

        $personasForTargeting = collect($personas['personas'])->map(fn ($p) => [
            'age_range' => $p['age_group'], 'gender' => $p['gender'],
            'top_cities' => array_keys($p['top_cities'] ?? []),
        ])->toArray();

        // Genre interests for ad targeting
        $topGenres = collect($this->buildGenrePerformance($eventIds))->take(5)->pluck('genre')->toArray();

        $venueCity = $this->venue->city ?? 'N/A';
        $geoOrigin = $this->buildGeographicOrigin($orderIds);
        $topCities = collect($geoOrigin['cities'] ?? [])->take(5)->pluck('city')->toArray();

        // Platform strategy
        $platformStrategy = [
            [
                'platform' => 'Facebook & Instagram',
                'budget_pct' => $isYoungAudience ? 35 : 45,
                'recommended' => true,
                'audience' => ['age' => $primaryAge, 'location' => implode(', ', array_slice($topCities, 0, 5)), 'interests' => implode(', ', $topGenres), 'custom' => 'Lookalike 1-3% from past buyers'],
                'formats' => ['Stories (awareness)', 'Feed carousel (consideration)', 'Reels (engagement)', 'Dynamic retargeting (conversion)'],
                'phases' => ['Announce', 'Peak', 'Urgency', 'Last Call'],
                'tips' => 'Upload buyer emails as Custom Audience. Create Lookalike. Exclude existing ticket holders.',
            ],
            [
                'platform' => 'Google Ads',
                'budget_pct' => 25,
                'recommended' => true,
                'audience' => ['keywords' => "concert {$venueCity}, bilete {$venueCity}, " . implode(', ', array_map(fn ($g) => "concert {$g}", array_slice($topGenres, 0, 3))), 'location' => implode(', ', array_slice($topCities, 0, 5))],
                'formats' => ['Search (brand + event keywords)', 'Display retargeting', 'YouTube pre-roll (if sell-through < 60% at 14d)'],
                'phases' => ['Peak', 'Urgency', 'Last Call'],
                'tips' => 'Start Search at announce. Display/YouTube only from Peak phase.',
            ],
            [
                'platform' => 'TikTok',
                'budget_pct' => $isYoungAudience ? 25 : 10,
                'recommended' => $isYoungAudience,
                'audience' => ['age' => '18-34', 'location' => "{$venueCity} + 50km", 'interests' => 'Music, Entertainment, Concerts, ' . implode(', ', array_slice($topGenres, 0, 3))],
                'formats' => ['In-Feed video 15-30s', 'Spark Ads (boost organic)', 'TopView (for big events)'],
                'phases' => ['Peak', 'Urgency'],
                'tips' => $isYoungAudience ? 'High priority - audience is young. 5-8 creatives, refresh weekly.' : 'Low priority - audience is 35+. Reallocate budget to Facebook/Google.',
            ],
            [
                'platform' => 'Email & SMS',
                'budget_pct' => 15,
                'recommended' => true,
                'audience' => ['segments' => 'Past buyers, Genre fans, Last 6 months visitors, VIP customers'],
                'formats' => ['Email blast (announce)', 'SMS (urgency + last call)', 'Push notification (if app)'],
                'phases' => ['Announce', 'Urgency', 'Last Call'],
                'tips' => 'Segment by genre preference. Personalize by past event attendance.',
            ],
        ];

        return [
            'announcement_window' => $announcementWindow,
            'ad_budget' => $adBudget,
            'platform_strategy' => $platformStrategy,
            'personas_for_targeting' => $personasForTargeting,
            'top_genres' => $topGenres,
        ];
    }

    private function buildRevenueForecast(array $eventIds): array
    {
        if (empty($eventIds)) return ['monthly_trend' => [], 'forecast' => [], 'scenarios' => []];

        $monthlyRev = DB::table('events as e')
            ->leftJoin('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->whereNotNull('e.event_date')
            ->where('e.event_date', '>=', now()->subYears(2)->toDateString())
            ->selectRaw("TO_CHAR(e.event_date, 'YYYY-MM') as ym, COALESCE(SUM(tt.quota_sold * tt.price_cents / 100), 0) as rev")
            ->groupBy('ym')->orderBy('ym')->get()->keyBy('ym');

        $last12 = 0; $prev12 = 0; $trend = [];
        $cursor = now()->subMonths(23)->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $key = $cursor->format('Y-m');
            $rev = round((float) ($monthlyRev->get($key)?->rev ?? 0), 0);
            if ($i < 12) $prev12 += $rev; else { $last12 += $rev; $trend[] = ['month' => $cursor->format('M y'), 'revenue' => $rev]; }
            $cursor->addMonth();
        }

        $growthRate = $prev12 > 0 ? ($last12 - $prev12) / $prev12 : 0;
        $forecast = [];
        for ($i = 1; $i <= 6; $i++) {
            $futureMonth = now()->addMonths($i);
            $sameMonthLastYear = $futureMonth->copy()->subYear()->format('Y-m');
            $baseRev = (float) ($monthlyRev->get($sameMonthLastYear)?->rev ?? ($last12 / 12));
            $forecast[] = ['month' => $futureMonth->format('M y'), 'pessimistic' => round($baseRev * (1 + $growthRate) * 0.8), 'realistic' => round($baseRev * (1 + $growthRate)), 'optimistic' => round($baseRev * (1 + $growthRate) * 1.2)];
        }

        return [
            'last_12_revenue' => round($last12, 0), 'prev_12_revenue' => round($prev12, 0),
            'yoy_change_pct' => $prev12 > 0 ? round($growthRate * 100, 1) : null,
            'monthly_trend' => $trend, 'forecast' => $forecast,
        ];
    }

    private function buildUpcomingVenueEvents(array $eventIds): array
    {
        if (empty($eventIds)) return [];

        $pastStats = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->where('e.event_date', '<', now()->toDateString())
            ->select(DB::raw('AVG(ts.sold) as avg_sold'), DB::raw('AVG(CASE WHEN ts.cap > 0 THEN ts.sold * 100.0 / ts.cap ELSE NULL END) as avg_st'))
            ->first();
        $histAvgSold = round((float) ($pastStats?->avg_sold ?? 0));

        return DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as capacity, SUM(quota_sold * price_cents / 100) as revenue FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->leftJoin(DB::raw("(SELECT ea2.event_id, string_agg(a2.name, ', ' ORDER BY ea2.is_headliner DESC) as artists FROM event_artist ea2 JOIN artists a2 ON a2.id = ea2.artist_id GROUP BY ea2.event_id) as ar"), 'ar.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->where('e.event_date', '>=', now()->toDateString())
            ->select('e.id', 'e.title', 'e.event_date', 'ar.artists', 'ts.sold', 'ts.capacity', 'ts.revenue')
            ->orderBy('e.event_date')->limit(15)->get()
            ->map(function ($e) use ($histAvgSold) {
                $sold = (int) ($e->sold ?? 0); $cap = (int) ($e->capacity ?? 0);
                $st = $cap > 0 ? round($sold / $cap * 100, 1) : null;
                $daysUntil = $e->event_date ? max(0, now()->diffInDays(Carbon::parse($e->event_date), false)) : null;
                $paceScore = 0;
                if ($histAvgSold > 0 && $sold > 0 && $daysUntil !== null) {
                    $daysSelling = max(1, 90 - $daysUntil); $paceScore = min(40, round(($sold / $daysSelling) / max(0.01, $histAvgSold / 90) * 20));
                }
                $stScore = min(30, round(($st ?? 0) / 100 * 30));
                $capScore = $cap > 0 ? min(30, round(($sold / $cap) * 30)) : 0;
                $demandScore = $paceScore + $stScore + $capScore;
                return [
                    'id' => $e->id, 'title' => $this->decodeJsonName($e->title), 'date' => $e->event_date,
                    'artists' => $e->artists ?? '—', 'sold' => $sold, 'capacity' => $cap,
                    'sell_through' => $st, 'revenue' => round((float) ($e->revenue ?? 0), 0),
                    'days_until' => $daysUntil,
                    'demand_score' => $demandScore,
                    'demand_label' => match (true) { $demandScore >= 75 => 'Hot', $demandScore >= 50 => 'Strong', $demandScore >= 25 => 'Moderate', default => 'Low' },
                ];
            })->toArray();
    }

    private function simulateEvent(array $eventIds, array $orderIds, string $genre, string $dayOfWeek, float $ticketPrice): array
    {
        if (empty($eventIds)) return ['error' => 'No historical data', 'predicted_sell_through' => 0, 'predicted_revenue' => 0, 'demand_score' => 0];

        $cap = $this->venue->capacity ?: $this->venue->capacity_total ?: 500;

        // Genre baseline
        $genrePerf = collect($this->buildGenrePerformance($eventIds));
        $genreMatch = $genrePerf->first(fn ($g) => mb_strtolower($g['genre']) === mb_strtolower($genre));
        $genreSt = $genreMatch ? $genreMatch['avg_sell_through'] : ($genrePerf->avg('avg_sell_through') ?: 50);

        // Day of week modifier
        $dowPerf = collect($this->buildDayOfWeekAnalysis($eventIds));
        $dowMatch = $dowPerf->first(fn ($d) => mb_strtolower(trim($d['day'])) === mb_strtolower(trim($dayOfWeek)));
        $avgDowSt = $dowPerf->avg('avg_sell_through') ?: 50;
        $dowModifier = $dowMatch ? ($dowMatch['avg_sell_through'] / max($avgDowSt, 1)) : 1.0;

        // Price modifier
        $pricing = $this->buildPricingIntelligence($eventIds);
        $priceBucket = match (true) { $ticketPrice < 50 => '0-50', $ticketPrice < 100 => '50-100', $ticketPrice < 200 => '100-200', $ticketPrice < 500 => '200-500', default => '500+' };
        $bucketMatch = collect($pricing['price_buckets'])->first(fn ($b) => $b['range'] === $priceBucket);
        $avgBucketSt = collect($pricing['price_buckets'])->avg('sell_through') ?: 50;
        $priceModifier = $bucketMatch ? ($bucketMatch['sell_through'] / max($avgBucketSt, 1)) : 1.0;

        $predictedSt = min(100, round($genreSt * $dowModifier * $priceModifier, 1));
        $predictedTickets = round($cap * $predictedSt / 100);
        $predictedRevenue = round($predictedTickets * $ticketPrice, 0);

        $demandScore = min(100, round($predictedSt));
        $demandLabel = match (true) { $demandScore >= 75 => 'Hot', $demandScore >= 50 => 'Strong', $demandScore >= 25 => 'Moderate', default => 'Low' };

        // Comparable events
        $comparables = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->join('artist_artist_genre as aag', 'aag.artist_id', '=', 'ea.artist_id')
            ->join('artist_genres as ag', 'ag.id', '=', 'aag.artist_genre_id')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap, AVG(price_cents/100) as avg_price FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)
            ->whereRaw("LOWER(ag.name::text) LIKE ?", ['%' . mb_strtolower($genre) . '%'])
            ->where('e.event_date', '<', now()->toDateString())
            ->select('e.title', 'e.event_date', 'ts.sold', 'ts.cap', 'ts.avg_price')
            ->orderByDesc('e.event_date')->limit(5)->get()
            ->map(fn ($e) => ['title' => $this->decodeJsonName($e->title), 'date' => $e->event_date, 'sold' => (int) ($e->sold ?? 0), 'capacity' => (int) ($e->cap ?? 0), 'sell_through' => (int) $e->cap > 0 ? round($e->sold / $e->cap * 100, 1) : null, 'avg_price' => round((float) ($e->avg_price ?? 0), 0)])->toArray();

        // Suggested artists
        $suggestedArtists = $this->buildNeverPlayedArtists($eventIds);

        return [
            'genre' => $genre, 'day_of_week' => $dayOfWeek, 'ticket_price' => $ticketPrice,
            'venue_capacity' => $cap,
            'predicted_sell_through' => $predictedSt,
            'predicted_tickets' => $predictedTickets,
            'predicted_revenue' => $predictedRevenue,
            'demand_score' => $demandScore, 'demand_label' => $demandLabel,
            'genre_baseline_st' => round($genreSt, 1),
            'dow_modifier' => round($dowModifier, 2), 'price_modifier' => round($priceModifier, 2),
            'comparables' => array_slice($comparables, 0, 5),
            'suggested_artists' => array_slice($suggestedArtists, 0, 5),
        ];
    }

    private function buildEventSuggestions(array $eventIds, array $orderIds): array
    {
        if (empty($eventIds)) return [];

        $genrePerf = collect($this->buildGenrePerformance($eventIds))->sortByDesc('avg_sell_through')->values();
        $dowPerf = collect($this->buildDayOfWeekAnalysis($eventIds))->sortByDesc('avg_sell_through')->values();
        $seasonPerf = collect($this->buildSeasonalityAnalysis($eventIds))->sortByDesc('avg_sell_through')->values();
        $pricing = $this->buildPricingIntelligence($eventIds);
        $neverPlayed = $this->buildNeverPlayedArtists($eventIds);
        $personas = $this->buildVenueAudiencePersonas($orderIds);
        $primaryPersona = $personas['personas'][0] ?? null;

        $cap = $this->venue->capacity ?: $this->venue->capacity_total ?: 500;
        $suggestions = [];

        $topGenres = $genrePerf->take(3);
        $topDays = $dowPerf->take(2);
        $topMonths = $seasonPerf->take(3);

        foreach ($topGenres as $gi => $genre) {
            $bestDay = $topDays->first();
            $bestMonth = $topMonths[$gi] ?? $topMonths->first();

            // Find sweet spot price for this genre
            $genreEventIds = DB::table('event_artist as ea')
                ->join('artist_artist_genre as aag', 'aag.artist_id', '=', 'ea.artist_id')
                ->join('artist_genres as ag', 'ag.id', '=', 'aag.artist_genre_id')
                ->whereIn('ea.event_id', $eventIds)
                ->whereRaw("LOWER(ag.name::text) LIKE ?", ['%' . mb_strtolower(is_string($genre['genre']) ? $genre['genre'] : '') . '%'])
                ->pluck('ea.event_id')->unique()->toArray();

            $avgPrice = 0;
            if (!empty($genreEventIds)) {
                $avgPrice = round((float) (DB::table('ticket_types')->whereIn('event_id', $genreEventIds)->where('price_cents', '>', 0)->avg('price_cents') ?? 0) / 100, 0);
            }
            if ($avgPrice <= 0) $avgPrice = 100;

            $targetCap = round($cap * 0.85);
            $estRevenue = round($targetCap * $avgPrice);

            // Match artists
            $matchedArtists = collect($neverPlayed)->take(3)->map(fn ($a) => [
                'name' => $a['artist_name'],
                'avg_sell_through' => $a['avg_sell_through'],
                'estimated_draw' => $a['estimated_draw'],
            ])->toArray();

            $suggestions[] = [
                'rank' => $gi + 1,
                'when' => trim($bestDay['day'] ?? 'Saturday') . ', ' . trim($bestMonth['month'] ?? 'June'),
                'why_when' => trim($bestMonth['month'] ?? '') . " has {$bestMonth['avg_sell_through']}% avg ST. " . trim($bestDay['day'] ?? '') . " has {$bestDay['avg_sell_through']}% avg ST.",
                'genre' => $genre['genre'],
                'why_genre' => "{$genre['avg_sell_through']}% sell-through, avg revenue " . number_format($genre['avg_revenue']) . " RON across {$genre['events_count']} events.",
                'target_capacity' => $targetCap . " / {$cap} (85%)",
                'pricing' => [
                    'recommended' => $avgPrice . ' RON',
                    'early_bird' => round($avgPrice * 0.75) . ' RON (first 50 tickets)',
                    'vip' => round($avgPrice * 2.5) . ' RON',
                    'sweet_spot' => $pricing['sweet_spot'] ?? '—',
                ],
                'suggested_artists' => $matchedArtists,
                'estimated_revenue' => number_format($estRevenue) . ' RON',
                'target_audience' => $primaryPersona ? "{$primaryPersona['age_group']}, {$primaryPersona['gender']}" : '—',
                'confidence' => $genre['events_count'] >= 3 && ($bestDay['events'] ?? 0) >= 3 ? 'high' : 'medium',
            ];
        }

        return $suggestions;
    }

    private function buildCreativeCalendar(int $eventId, array $eventIds, array $orderIds): array
    {
        $event = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap, AVG(price_cents/100) as avg_price FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->leftJoin(DB::raw("(SELECT ea.event_id, string_agg(a.name, ', ') as artists FROM event_artist ea JOIN artists a ON a.id=ea.artist_id GROUP BY ea.event_id) as ar"), 'ar.event_id', '=', 'e.id')
            ->where('e.id', $eventId)
            ->select('e.id', 'e.title', 'e.event_date', 'ar.artists', 'ts.sold', 'ts.cap', 'ts.avg_price')
            ->first();

        if (!$event || !$event->event_date) return ['error' => 'Event not found'];

        $eventDate = Carbon::parse($event->event_date);
        $title = $this->decodeJsonName($event->title);
        $cap = (int) ($event->cap ?? 0);
        $sold = (int) ($event->sold ?? 0);
        $avgPrice = round((float) ($event->avg_price ?? 100), 0);
        $estRevenue = $cap > 0 ? $cap * $avgPrice : $sold * $avgPrice;

        $promo = $this->buildPromotionPlanner($eventIds, $orderIds);
        $announce = $promo['announcement_window']['optimal_announce_days'] ?? 60;
        $budget = round($estRevenue * 0.12);
        $personas = $promo['personas_for_targeting'] ?? [];
        $platforms = $promo['platform_strategy'] ?? [];

        $primaryAge = $personas[0]['age_range'] ?? '25-34';
        $isYoung = in_array($primaryAge, ['<18', '18-24', '25-34']);

        $phases = [];

        // Phase 1: Announce
        $d1 = $eventDate->copy()->subDays($announce);
        $phases[] = [
            'phase' => 'Announce', 'date' => $d1->format('d M Y'),
            'days_before' => $announce,
            'budget' => round($budget * 0.20),
            'actions' => [
                'Organic post on all socials (artwork + lineup)',
                'Email blast to full subscriber list',
                'Early bird tickets live (25% discount, limited qty)',
                'Press release / PR outreach',
            ],
            'ads' => ['FB/IG: 1 awareness video ad (daily ' . round($budget * 0.20 / max(1, $announce - 45) ) . ' RON)'],
        ];

        // Phase 2: Peak Campaign
        $d2 = $eventDate->copy()->subDays(45);
        $phases[] = [
            'phase' => 'Peak Campaign', 'date' => $d2->format('d M Y'),
            'days_before' => 45,
            'budget' => round($budget * 0.40),
            'actions' => [
                'FB/IG: 2 conversion ads (carousel + retargeting)',
                'Google Search: brand + genre keywords live',
                $isYoung ? 'TikTok: In-feed video ad, Spark Ads' : 'Skip TikTok, boost FB budget',
                'Second email blast (lineup details + early bird ending)',
                'If sell-through < 30%: consider adding support act or price adjustment',
            ],
            'ads' => [
                'FB/IG: daily ' . round($budget * 0.40 * 0.45 / 31) . ' RON (Lookalike + retargeting)',
                'Google: daily ' . round($budget * 0.40 * 0.30 / 31) . ' RON (Search)',
                $isYoung ? 'TikTok: daily ' . round($budget * 0.40 * 0.25 / 31) . ' RON' : 'Email/SMS: segment by genre',
            ],
        ];

        // Phase 3: Urgency
        $d3 = $eventDate->copy()->subDays(14);
        $phases[] = [
            'phase' => 'Urgency Push', 'date' => $d3->format('d M Y'),
            'days_before' => 14,
            'budget' => round($budget * 0.25),
            'actions' => [
                '"Ultimele bilete" creative on all platforms',
                'SMS blast to past 6-month buyers',
                'YouTube pre-roll (if sell-through < 60%)',
                'Retargeting on all channels (website visitors + cart abandoners)',
                'Influencer / partner share push',
            ],
            'ads' => [
                'FB/IG: daily ' . round($budget * 0.25 * 0.50 / 7) . ' RON (urgency creatives)',
                'Google: daily ' . round($budget * 0.25 * 0.30 / 7) . ' RON (Display + YouTube)',
                'SMS: ' . round($budget * 0.25 * 0.20) . ' RON total',
            ],
        ];

        // Phase 4: Last Call
        $d4 = $eventDate->copy()->subDays(7);
        $phases[] = [
            'phase' => 'Last Call', 'date' => $d4->format('d M Y'),
            'days_before' => 7,
            'budget' => round($budget * 0.15),
            'actions' => [
                '"Mai sunt X bilete" countdown on Stories/Reels',
                'Door price announcement (+20-30% increase)',
                'Push notification (if app exists)',
                'Aggressive retargeting: all website visitors last 30 days',
                'Final email: scarcity + social proof',
            ],
            'ads' => [
                'All platforms: daily ' . round($budget * 0.15 / 7) . ' RON total (max urgency)',
            ],
        ];

        return [
            'event_title' => $title, 'event_date' => $eventDate->format('d M Y'),
            'artists' => $event->artists ?? '—',
            'capacity' => $cap, 'sold' => $sold, 'avg_price' => $avgPrice,
            'estimated_revenue' => round($estRevenue),
            'total_ad_budget' => $budget,
            'target_audience' => $primaryAge . ($personas[0]['gender'] ?? ''),
            'phases' => $phases,
        ];
    }

    private function buildCompetitorBenchmark(array $eventIds): array
    {
        $venueCity = $this->venue->city ?? null;
        if (!$venueCity || empty($eventIds)) return [];

        // This venue's stats
        $myStats = DB::table('ticket_types as tt')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->whereIn('e.id', $eventIds)->where('tt.quota_total', '>', 0)
            ->selectRaw("AVG(LEAST(tt.quota_sold::float / tt.quota_total, 1.0) * 100) as avg_st, AVG(tt.price_cents / 100) as avg_price, AVG(tt.quota_sold * tt.price_cents / 100) as avg_rev_per_tt, COUNT(DISTINCT e.id) as events")
            ->first();

        // Other venues in same city
        $otherVenueIds = DB::table('venues')
            ->whereRaw('LOWER(city) = LOWER(?)', [$venueCity])
            ->whereNotIn('id', $this->venueIds)
            ->pluck('id')->toArray();

        if (empty($otherVenueIds)) return ['my' => ['avg_st' => round((float) ($myStats->avg_st ?? 0), 1), 'avg_price' => round((float) ($myStats->avg_price ?? 0), 0), 'events' => (int) ($myStats->events ?? 0)], 'city_avg' => null, 'competitors' => []];

        $cityStats = DB::table('ticket_types as tt')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->whereIn('e.venue_id', $otherVenueIds)->where('tt.quota_total', '>', 0)
            ->where('e.event_date', '>=', now()->subYear()->toDateString())
            ->selectRaw("AVG(LEAST(tt.quota_sold::float / tt.quota_total, 1.0) * 100) as avg_st, AVG(tt.price_cents / 100) as avg_price, COUNT(DISTINCT e.id) as events")
            ->first();

        // Top competitors
        $competitors = DB::table('venues as v')
            ->join('events as e', 'e.venue_id', '=', 'v.id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->whereIn('v.id', $otherVenueIds)->where('tt.quota_total', '>', 0)
            ->where('e.event_date', '>=', now()->subYear()->toDateString())
            ->selectRaw("v.id, v.name, v.capacity, COUNT(DISTINCT e.id) as events, AVG(LEAST(tt.quota_sold::float / tt.quota_total, 1.0) * 100) as avg_st, AVG(tt.price_cents / 100) as avg_price")
            ->groupBy('v.id', 'v.name', 'v.capacity')
            ->havingRaw('COUNT(DISTINCT e.id) >= 3')
            ->orderByDesc('events')->limit(8)->get()
            ->map(fn ($r) => ['name' => $this->decodeJsonName($r->name), 'capacity' => (int) ($r->capacity ?? 0), 'events' => (int) $r->events, 'avg_st' => round((float) ($r->avg_st ?? 0), 1), 'avg_price' => round((float) ($r->avg_price ?? 0), 0)])->toArray();

        $mySt = round((float) ($myStats->avg_st ?? 0), 1);
        $citySt = round((float) ($cityStats->avg_st ?? 0), 1);

        return [
            'my' => ['avg_st' => $mySt, 'avg_price' => round((float) ($myStats->avg_price ?? 0), 0), 'events' => (int) ($myStats->events ?? 0)],
            'city_avg' => ['avg_st' => $citySt, 'avg_price' => round((float) ($cityStats->avg_price ?? 0), 0), 'events' => (int) ($cityStats->events ?? 0)],
            'vs_city' => $citySt > 0 ? round($mySt - $citySt, 1) : null,
            'competitors' => $competitors,
        ];
    }

    private function buildChurnRiskAlerts(array $eventIds, array $orderIds): array
    {
        $upcoming = $this->buildUpcomingVenueEvents($eventIds);
        if (empty($upcoming)) return [];

        $alerts = [];
        foreach ($upcoming as $ue) {
            $daysLeft = $ue['days_until'] ?? 999;
            $st = $ue['sell_through'] ?? 0;
            $sold = $ue['sold'] ?? 0;
            $cap = $ue['capacity'] ?? 0;

            $risk = null; $suggestions = [];

            if ($daysLeft <= 14 && $st < 30 && $cap > 0) {
                $risk = 'critical';
                $suggestions = [
                    'Reduce ticket price by 20-30% for remaining tickets',
                    'Launch aggressive retargeting campaign (all website visitors last 30d)',
                    'SMS blast to past buyers with discount code',
                    'Consider adding a support act to boost appeal',
                    'Partner with influencers for last-push promotion',
                ];
            } elseif ($daysLeft <= 30 && $st < 40 && $cap > 0) {
                $risk = 'high';
                $suggestions = [
                    'Increase ad spend by 50% for next 2 weeks',
                    'Launch "early urgency" campaign — limited seats messaging',
                    'Email blast to genre-specific segment from past buyers',
                    'Consider flash sale / group discount (4+ tickets)',
                ];
            } elseif ($daysLeft <= 45 && $st < 25 && $cap > 0) {
                $risk = 'medium';
                $suggestions = [
                    'Review pricing — may be overpriced for this genre/day',
                    'Boost organic social posting frequency',
                    'Consider cross-promotion with related upcoming events',
                ];
            }

            if ($risk) {
                $alerts[] = [
                    'event_id' => $ue['id'], 'title' => $ue['title'], 'date' => $ue['date'],
                    'days_until' => $daysLeft, 'sold' => $sold, 'capacity' => $cap,
                    'sell_through' => $st, 'risk' => $risk,
                    'gap' => $cap > 0 ? $cap - $sold : 0,
                    'suggestions' => $suggestions,
                ];
            }
        }
        return $alerts;
    }

    private function buildRevenuePerSeat(array $eventIds): array
    {
        if (empty($eventIds)) return [];
        $cap = $this->venue->capacity ?: $this->venue->capacity_total ?: 0;
        if ($cap <= 0) return [];

        $totalRev = (float) DB::table('ticket_types')->whereIn('event_id', $eventIds)
            ->selectRaw('COALESCE(SUM(quota_sold * price_cents / 100), 0) as rev')->value('rev');
        $totalEvents = count(array_unique($eventIds));
        $revPerSeat = $totalEvents > 0 ? round($totalRev / ($cap * $totalEvents), 2) : 0;

        // Best event by rev/seat
        $bestEvent = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold * price_cents / 100) as rev FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->where('ts.rev', '>', 0)
            ->selectRaw("e.id, e.title, e.event_date, ts.rev, (ts.rev / {$cap}) as rev_per_seat")
            ->orderByDesc('rev_per_seat')->first();

        // Monthly trend
        $monthlyRps = DB::table('events as e')
            ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold * price_cents / 100) as rev FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
            ->whereIn('e.id', $eventIds)->whereNotNull('e.event_date')
            ->where('e.event_date', '>=', now()->subYear()->toDateString())
            ->selectRaw("TO_CHAR(e.event_date, 'YYYY-MM') as ym, SUM(ts.rev) / (COUNT(DISTINCT e.id) * {$cap}) as rps")
            ->groupBy('ym')->orderBy('ym')->get()
            ->map(fn ($r) => ['month' => $r->ym, 'rev_per_seat' => round((float) $r->rps, 2)])->toArray();

        return [
            'capacity' => $cap,
            'avg_rev_per_seat' => $revPerSeat,
            'best_event' => $bestEvent ? ['title' => $this->decodeJsonName($bestEvent->title), 'date' => $bestEvent->event_date, 'rev_per_seat' => round((float) $bestEvent->rev_per_seat, 2), 'revenue' => round((float) $bestEvent->rev, 0)] : null,
            'monthly_trend' => $monthlyRps,
        ];
    }

    private function buildEventComparison(int $eventIdA, int $eventIdB): array
    {
        $getEventData = function (int $eid) {
            $e = DB::table('events as e')
                ->leftJoin(DB::raw("(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as cap, SUM(quota_sold * price_cents / 100) as rev, AVG(price_cents/100) as avg_price FROM ticket_types GROUP BY event_id) as ts"), 'ts.event_id', '=', 'e.id')
                ->leftJoin(DB::raw("(SELECT ea.event_id, string_agg(a.name, ', ' ORDER BY ea.is_headliner DESC) as artists FROM event_artist ea JOIN artists a ON a.id=ea.artist_id GROUP BY ea.event_id) as ar"), 'ar.event_id', '=', 'e.id')
                ->where('e.id', $eid)
                ->select('e.id', 'e.title', 'e.event_date', 'ar.artists', 'ts.sold', 'ts.cap', 'ts.rev', 'ts.avg_price')
                ->first();
            if (!$e) return null;
            $sold = (int) ($e->sold ?? 0); $cap = (int) ($e->cap ?? 0);

            // Lead time
            $avgLead = DB::table('orders as o')
                ->join('tickets as t', 't.order_id', '=', 'o.id')
                ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->where(function ($q) use ($eid) { $q->where('tt.event_id', $eid)->orWhere('t.event_id', $eid); })
                ->whereIn('o.status', ['paid', 'confirmed', 'completed'])
                ->whereNotNull('o.paid_at')
                ->selectRaw("AVG(GREATEST(0, ('{$e->event_date}'::date - o.paid_at::date))) as avg_lead")
                ->value('avg_lead');

            // Check-in
            $ci = DB::table('tickets as t')
                ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->where('t.status', 'valid')
                ->where(function ($q) use ($eid) { $q->where('tt.event_id', $eid)->orWhere('t.event_id', $eid); })
                ->selectRaw("COUNT(t.id) as tot, SUM(CASE WHEN t.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) as ci")
                ->first();

            return [
                'id' => $e->id, 'title' => $this->decodeJsonName($e->title), 'date' => $e->event_date,
                'artists' => $e->artists ?? '—',
                'sold' => $sold, 'capacity' => $cap,
                'sell_through' => $cap > 0 ? round($sold / $cap * 100, 1) : null,
                'revenue' => round((float) ($e->rev ?? 0), 0),
                'avg_price' => round((float) ($e->avg_price ?? 0), 0),
                'avg_lead_days' => round((float) ($avgLead ?? 0), 0),
                'checkin_rate' => ($ci && $ci->tot > 0) ? round($ci->ci / $ci->tot * 100, 1) : null,
            ];
        };

        $a = $getEventData($eventIdA);
        $b = $getEventData($eventIdB);
        if (!$a || !$b) return ['error' => 'Event not found'];
        return ['event_a' => $a, 'event_b' => $b];
    }

    private function buildGenreLoyalty(array $eventIds, array $orderIds): array
    {
        if (empty($orderIds)) return [];

        // Get buyer → events → genres
        $buyerEvents = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('event_artist as ea', function ($join) { $join->on('ea.event_id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)')); })
            ->join('artist_artist_genre as aag', 'aag.artist_id', '=', 'ea.artist_id')
            ->join('artist_genres as ag', 'ag.id', '=', 'aag.artist_genre_id')
            ->whereIn('o.id', array_slice($orderIds, 0, 5000))
            ->selectRaw("ag.name as genre, COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id, COUNT(DISTINCT COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)) as events_attended")
            ->groupBy('ag.name', DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'))
            ->get();

        $byGenre = $buyerEvents->groupBy('genre');

        return $byGenre->map(function ($buyers, $genre) {
            $total = $buyers->count();
            $repeaters = $buyers->where('events_attended', '>=', 2)->count();
            $avgEvents = round($buyers->avg('events_attended'), 1);
            return [
                'genre' => $this->decodeJsonName($genre),
                'total_buyers' => $total,
                'repeat_buyers' => $repeaters,
                'repeat_rate' => $total > 0 ? round($repeaters / $total * 100, 1) : 0,
                'avg_events_per_buyer' => $avgEvents,
            ];
        })->sortByDesc('repeat_rate')->values()->take(10)->toArray();
    }

    private function buildCheckinTimeAnalysis(array $eventIds): array
    {
        if (empty($eventIds)) return [];

        $checkins = DB::table('tickets as t')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->whereIn(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'), $eventIds)
            ->whereNotNull('t.checked_in_at')
            ->selectRaw("EXTRACT(HOUR FROM t.checked_in_at)::int as hour, COUNT(*) as cnt")
            ->groupBy('hour')->orderBy('hour')->get();

        if ($checkins->isEmpty()) return [];

        $totalCheckins = $checkins->sum('cnt');
        $peakHour = $checkins->sortByDesc('cnt')->first();

        // Build 24h distribution
        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $row = $checkins->firstWhere('hour', $h);
            $cnt = $row ? (int) $row->cnt : 0;
            $hourly[] = ['hour' => sprintf('%02d:00', $h), 'count' => $cnt, 'pct' => $totalCheckins > 0 ? round($cnt / $totalCheckins * 100, 1) : 0];
        }

        // Door-to-peak: when did most people arrive relative to event start?
        $earlyHours = $checkins->where('hour', '>=', 12)->where('hour', '<=', 23);
        $cumulative = 0;
        $p50Hour = null; $p80Hour = null;
        foreach ($earlyHours->sortBy('hour') as $row) {
            $cumulative += $row->cnt;
            if ($p50Hour === null && $cumulative >= $totalCheckins * 0.5) $p50Hour = $row->hour;
            if ($p80Hour === null && $cumulative >= $totalCheckins * 0.8) { $p80Hour = $row->hour; break; }
        }

        return [
            'total_checkins' => $totalCheckins,
            'peak_hour' => $peakHour ? sprintf('%02d:00', $peakHour->hour) : '—',
            'peak_count' => $peakHour ? (int) $peakHour->cnt : 0,
            'p50_arrival' => $p50Hour !== null ? sprintf('%02d:00', $p50Hour) : '—',
            'p80_arrival' => $p80Hour !== null ? sprintf('%02d:00', $p80Hour) : '—',
            'hourly' => $hourly,
        ];
    }

    private function buildVenueHealthScore(array $eventIds, array $orderIds): array
    {
        if (empty($eventIds)) return ['score' => 0, 'label' => 'No Data', 'components' => []];

        $components = [];

        // 1. Occupancy Score (0-25): avg sell-through
        $avgSt = (float) (DB::table('ticket_types')->whereIn('event_id', $eventIds)->where('quota_total', '>', 0)
            ->selectRaw('AVG(LEAST(quota_sold::float / quota_total, 1.0) * 100) as avg_st')->value('avg_st') ?? 0);
        $occupancyScore = min(25, round($avgSt / 4));
        $components[] = ['name' => 'Occupancy', 'score' => $occupancyScore, 'max' => 25, 'detail' => round($avgSt, 1) . '% avg sell-through'];

        // 2. Revenue Growth (0-25): YoY trend
        $last6 = (float) DB::table('ticket_types as tt')->join('events as e', 'e.id', '=', 'tt.event_id')
            ->whereIn('e.id', $eventIds)->where('e.event_date', '>=', now()->subMonths(6)->toDateString())
            ->selectRaw('COALESCE(SUM(tt.quota_sold * tt.price_cents / 100), 0) as rev')->value('rev');
        $prev6 = (float) DB::table('ticket_types as tt')->join('events as e', 'e.id', '=', 'tt.event_id')
            ->whereIn('e.id', $eventIds)->where('e.event_date', '>=', now()->subMonths(12)->toDateString())->where('e.event_date', '<', now()->subMonths(6)->toDateString())
            ->selectRaw('COALESCE(SUM(tt.quota_sold * tt.price_cents / 100), 0) as rev')->value('rev');
        $growthRate = $prev6 > 0 ? ($last6 - $prev6) / $prev6 : 0;
        $growthScore = min(25, max(0, round(12.5 + $growthRate * 50))); // 0% growth = 12.5, +25% = 25, -25% = 0
        $growthPct = $prev6 > 0 ? round($growthRate * 100, 1) : null;
        $components[] = ['name' => 'Revenue Growth', 'score' => $growthScore, 'max' => 25, 'detail' => $growthPct !== null ? ($growthPct >= 0 ? '+' : '') . $growthPct . '% (6m vs prev 6m)' : 'No prior data'];

        // 3. Customer Loyalty (0-25): repeat rate
        $repeatRate = 0;
        if (!empty($orderIds)) {
            $ce = DB::table('orders as o')->join('tickets as t', 't.order_id', '=', 'o.id')->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                ->whereIn('o.id', array_slice($orderIds, 0, 5000))
                ->selectRaw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id, COUNT(DISTINCT COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)) as ev')
                ->groupBy(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'))->get();
            $total = $ce->count(); $repeaters = $ce->where('ev', '>=', 2)->count();
            $repeatRate = $total > 0 ? round($repeaters / $total * 100, 1) : 0;
        }
        $loyaltyScore = min(25, round($repeatRate));
        $components[] = ['name' => 'Customer Loyalty', 'score' => $loyaltyScore, 'max' => 25, 'detail' => $repeatRate . '% repeat rate'];

        // 4. Activity / Utilization (0-25): events per month + idle days
        $eventsLast6 = DB::table('events')->whereIn('id', $eventIds)->where('event_date', '>=', now()->subMonths(6)->toDateString())->count();
        $eventsPerMonth = $eventsLast6 / 6;
        $activityScore = min(25, round($eventsPerMonth * 5)); // 5 events/month = 25
        $components[] = ['name' => 'Activity', 'score' => $activityScore, 'max' => 25, 'detail' => round($eventsPerMonth, 1) . ' events/month (last 6m)'];

        $totalScore = array_sum(array_column($components, 'score'));
        $label = match (true) { $totalScore >= 80 => 'Excellent', $totalScore >= 60 => 'Good', $totalScore >= 40 => 'Average', $totalScore >= 20 => 'Below Average', default => 'Critical' };
        $color = match (true) { $totalScore >= 80 => '#22c55e', $totalScore >= 60 => '#22d3ee', $totalScore >= 40 => '#fbbf24', $totalScore >= 20 => '#f97316', default => '#ef4444' };

        return ['score' => $totalScore, 'label' => $label, 'color' => $color, 'components' => $components];
    }

    private function buildRefundAnalysis(array $eventIds): array
    {
        if (empty($eventIds)) return ['total_refunds' => 0, 'refund_rate' => 0, 'refund_revenue_lost' => 0, 'by_event' => [], 'monthly' => []];

        // Total orders vs refunded orders for venue events
        $allOrders = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where(function ($q) use ($eventIds) { $q->whereIn('tt.event_id', $eventIds)->orWhereIn('t.event_id', $eventIds)->orWhereIn('t.marketplace_event_id', $eventIds); })
            ->select(DB::raw('COUNT(DISTINCT o.id) as total_orders'), DB::raw("COUNT(DISTINCT CASE WHEN o.status IN ('refunded', 'cancelled') THEN o.id END) as refunded_orders"),
                DB::raw("SUM(CASE WHEN o.status IN ('refunded', 'cancelled') THEN o.total ELSE 0 END) as refund_amount"),
                DB::raw("SUM(CASE WHEN o.status NOT IN ('refunded', 'cancelled') THEN o.total ELSE 0 END) as valid_revenue"))
            ->first();

        $totalOrders = (int) ($allOrders->total_orders ?? 0);
        $refundedOrders = (int) ($allOrders->refunded_orders ?? 0);
        $refundAmount = round((float) ($allOrders->refund_amount ?? 0), 0);
        $validRevenue = round((float) ($allOrders->valid_revenue ?? 0), 0);
        $refundRate = $totalOrders > 0 ? round($refundedOrders / $totalOrders * 100, 1) : 0;

        // By event (top offenders)
        $byEvent = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', 'e.id', '=', DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'))
            ->whereIn(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'), $eventIds)
            ->selectRaw("e.id, e.title, e.event_date, COUNT(DISTINCT o.id) as total_orders, COUNT(DISTINCT CASE WHEN o.status IN ('refunded', 'cancelled') THEN o.id END) as refunds, SUM(CASE WHEN o.status IN ('refunded', 'cancelled') THEN o.total ELSE 0 END) as lost_rev")
            ->groupBy('e.id', 'e.title', 'e.event_date')
            ->havingRaw("COUNT(DISTINCT CASE WHEN o.status IN ('refunded', 'cancelled') THEN o.id END) > 0")
            ->orderByDesc('refunds')->limit(10)->get()
            ->map(fn ($r) => [
                'title' => $this->decodeJsonName($r->title), 'date' => $r->event_date,
                'total_orders' => (int) $r->total_orders, 'refunds' => (int) $r->refunds,
                'refund_rate' => (int) $r->total_orders > 0 ? round($r->refunds / $r->total_orders * 100, 1) : 0,
                'lost_revenue' => round((float) $r->lost_rev, 0),
            ])->toArray();

        // Monthly trend
        $monthly = DB::table('orders as o')
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where(function ($q) use ($eventIds) { $q->whereIn('tt.event_id', $eventIds)->orWhereIn('t.event_id', $eventIds); })
            ->where('o.created_at', '>=', now()->subYear()->toDateString())
            ->selectRaw("TO_CHAR(o.created_at, 'YYYY-MM') as ym, COUNT(DISTINCT o.id) as total_orders, COUNT(DISTINCT CASE WHEN o.status IN ('refunded', 'cancelled') THEN o.id END) as refunds")
            ->groupBy('ym')->orderBy('ym')->get()
            ->map(fn ($r) => ['month' => $r->ym, 'total' => (int) $r->total_orders, 'refunds' => (int) $r->refunds, 'rate' => (int) $r->total_orders > 0 ? round($r->refunds / $r->total_orders * 100, 1) : 0])->toArray();

        return [
            'total_orders' => $totalOrders, 'total_refunds' => $refundedOrders,
            'refund_rate' => $refundRate, 'refund_revenue_lost' => $refundAmount,
            'valid_revenue' => $validRevenue,
            'by_event' => $byEvent, 'monthly' => $monthly,
        ];
    }

    private function buildMonthlyMomentum(array $eventIds, array $orderIds): array
    {
        if (empty($eventIds)) return [];

        // Current month vs previous month for key metrics
        $thisMonth = now()->startOfMonth()->toDateString();
        $lastMonth = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();
        $twoMonthsAgo = now()->subMonths(2)->startOfMonth()->toDateString();
        $twoMonthsAgoEnd = now()->subMonths(2)->endOfMonth()->toDateString();

        $getMonthStats = function (string $from, string $to) use ($eventIds) {
            $mEventIds = DB::table('events')->whereIn('id', $eventIds)->whereBetween('event_date', [$from, $to])->pluck('id')->toArray();
            if (empty($mEventIds)) return ['events' => 0, 'tickets' => 0, 'revenue' => 0, 'avg_st' => 0];
            $stats = DB::table('ticket_types')->whereIn('event_id', $mEventIds)
                ->selectRaw("COUNT(DISTINCT event_id) as events, COALESCE(SUM(quota_sold), 0) as tickets, COALESCE(SUM(quota_sold * price_cents / 100), 0) as revenue, AVG(CASE WHEN quota_total > 0 THEN LEAST(quota_sold::float / quota_total, 1.0) * 100 ELSE NULL END) as avg_st")
                ->first();
            return ['events' => (int) ($stats->events ?? 0), 'tickets' => (int) ($stats->tickets ?? 0), 'revenue' => round((float) ($stats->revenue ?? 0), 0), 'avg_st' => round((float) ($stats->avg_st ?? 0), 1)];
        };

        $current = $getMonthStats($lastMonth, $lastMonthEnd); // last full month
        $previous = $getMonthStats($twoMonthsAgo, $twoMonthsAgoEnd);

        $calcTrend = function ($curr, $prev) {
            if ($prev == 0 && $curr == 0) return ['value' => 0, 'direction' => 'flat', 'pct' => 0];
            if ($prev == 0) return ['value' => $curr, 'direction' => 'up', 'pct' => 100];
            $pct = round(($curr - $prev) / $prev * 100, 1);
            return ['value' => $curr, 'direction' => $pct > 2 ? 'up' : ($pct < -2 ? 'down' : 'flat'), 'pct' => $pct];
        };

        $lastMonthLabel = now()->subMonth()->format('M Y');
        $prevMonthLabel = now()->subMonths(2)->format('M Y');

        return [
            'current_label' => $lastMonthLabel,
            'previous_label' => $prevMonthLabel,
            'metrics' => [
                ['name' => 'Events', 'current' => $current['events'], 'previous' => $previous['events'], 'trend' => $calcTrend($current['events'], $previous['events'])],
                ['name' => 'Tickets Sold', 'current' => $current['tickets'], 'previous' => $previous['tickets'], 'trend' => $calcTrend($current['tickets'], $previous['tickets'])],
                ['name' => 'Revenue', 'current' => $current['revenue'], 'previous' => $previous['revenue'], 'trend' => $calcTrend($current['revenue'], $previous['revenue']), 'format' => 'currency'],
                ['name' => 'Avg Occupancy', 'current' => $current['avg_st'], 'previous' => $previous['avg_st'], 'trend' => $calcTrend($current['avg_st'], $previous['avg_st']), 'format' => 'pct'],
            ],
        ];
    }

    private function buildActionPriority(array $eventIds, array $orderIds): array
    {
        $actions = [];

        // 1. Churn risk alerts (highest priority)
        $churn = $this->buildChurnRiskAlerts($eventIds, $orderIds);
        foreach ($churn as $ca) {
            $priority = match ($ca['risk']) { 'critical' => 1, 'high' => 2, default => 3 };
            $actions[] = [
                'priority' => $priority,
                'urgency' => $ca['risk'],
                'category' => 'Event at Risk',
                'title' => $ca['title'] . " — {$ca['sell_through']}% ST, {$ca['days_until']}d left",
                'action' => $ca['suggestions'][0] ?? 'Review event performance',
                'impact' => "Fill {$ca['gap']} seats = ~" . number_format($ca['gap'] * ($ca['capacity'] > 0 ? round(($this->computeVenueKpis($eventIds, $orderIds)['avg_ticket_price'] ?? 80), 0) : 80)) . " RON",
            ];
        }

        // 2. Idle weekend revenue opportunity
        $idle = $this->buildIdleDaysAnalysis($eventIds);
        if (($idle['total_idle_weekend_days'] ?? 0) > 15) {
            $actions[] = [
                'priority' => 3,
                'urgency' => 'medium',
                'category' => 'Revenue Opportunity',
                'title' => "{$idle['total_idle_weekend_days']} idle weekend days (last 12m)",
                'action' => 'Book events for empty Fri/Sat/Sun slots. Start with top-performing genres.',
                'impact' => "Potential: ~" . number_format($idle['estimated_lost_revenue']) . " RON",
            ];
        }

        // 3. Low repeat rate
        $loyalty = $this->buildVenueCustomerLoyalty($eventIds, $orderIds);
        if (($loyalty['total'] ?? 0) > 50 && ($loyalty['repeat_rate'] ?? 0) < 15) {
            $potentialReturn = round($loyalty['one_time'] * 0.15);
            $avgTicket = $this->computeVenueKpis($eventIds, $orderIds)['avg_ticket_price'] ?? 80;
            $actions[] = [
                'priority' => 4,
                'urgency' => 'medium',
                'category' => 'Loyalty',
                'title' => "Repeat rate only {$loyalty['repeat_rate']}% ({$loyalty['one_time']} one-time buyers)",
                'action' => 'Launch email remarketing campaign. Offer 10% loyalty discount for 2nd event.',
                'impact' => "If 15% return = +{$potentialReturn} customers (~" . number_format($potentialReturn * $avgTicket) . " RON)",
            ];
        }

        // 4. Underpriced events
        $pricing = $this->buildPricingIntelligence($eventIds);
        if (count($pricing['underpriced'] ?? []) >= 2) {
            $actions[] = [
                'priority' => 4,
                'urgency' => 'low',
                'category' => 'Pricing',
                'title' => count($pricing['underpriced']) . " events sold out too fast (>90% ST)",
                'action' => 'Raise base price by 15-20% for similar future events. Add VIP tier.',
                'impact' => 'Higher revenue per event without reducing demand',
            ];
        }

        // 5. Competitor gap
        $bench = $this->buildCompetitorBenchmark($eventIds);
        if (!empty($bench) && ($bench['vs_city'] ?? 0) < -10) {
            $actions[] = [
                'priority' => 5,
                'urgency' => 'low',
                'category' => 'Competitive',
                'title' => "Sell-through " . abs($bench['vs_city']) . "% below city average",
                'action' => 'Analyze top competitor pricing and genres. Consider adjusting event mix.',
                'impact' => "Closing gap could increase revenue by ~" . round(abs($bench['vs_city']) * 0.5) . "%",
            ];
        }

        // 6. High refund rate
        $refunds = $this->buildRefundAnalysis($eventIds);
        if (($refunds['refund_rate'] ?? 0) > 5) {
            $actions[] = [
                'priority' => 3,
                'urgency' => 'medium',
                'category' => 'Refunds',
                'title' => "Refund rate at {$refunds['refund_rate']}% ({$refunds['total_refunds']} refunds)",
                'action' => 'Investigate top-refunded events. Review event descriptions and expectations.',
                'impact' => "Reducing refunds by 50% saves ~" . number_format(round($refunds['refund_revenue_lost'] / 2)) . " RON",
            ];
        }

        // 7. Promotion timing
        $promo = $this->buildPromotionPlanner($eventIds, $orderIds);
        $p90 = $promo['announcement_window']['p90_days'] ?? null;
        if ($p90 && $p90 < 21) {
            $actions[] = [
                'priority' => 5,
                'urgency' => 'low',
                'category' => 'Marketing',
                'title' => "Most sales within {$p90} days — very short window",
                'action' => 'Start campaigns earlier. Test announcing events 8+ weeks before.',
                'impact' => 'Longer sales window = more time for organic + paid reach',
            ];
        }

        usort($actions, fn ($a, $b) => $a['priority'] <=> $b['priority']);
        return $actions;
    }
}
