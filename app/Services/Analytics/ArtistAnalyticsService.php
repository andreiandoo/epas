<?php

namespace App\Services\Analytics;

use App\Models\Artist;
use App\Support\PiiMask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Artist analytics for the public API. Queries mirror what powers the
 * internal ViewArtist page (KPIs, personas, geographic intelligence,
 * performance deep-dive, upcoming events) but with two changes:
 *   1. All PII (buyer names, emails, birth dates) is masked via PiiMask.
 *   2. Results are cached with a shorter TTL than the internal page
 *      (5 min) — same window operators expect on the admin UI.
 *
 * The service is stateless: pass an Artist to any method and get back
 * an array of primitives suitable for JSON serialization.
 */
class ArtistAnalyticsService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * All event ids the artist appears on (via event_artist pivot).
     * The API surfaces per-artist analytics, so this is our root ID set.
     */
    private function artistEventIds(int $artistId): array
    {
        return DB::table('event_artist')
            ->where('artist_id', $artistId)
            ->pluck('event_id')
            ->toArray();
    }

    /**
     * All order ids that carry at least one ticket for one of the
     * artist's events. Uses the three ticket→event resolution paths
     * (ticket_type.event_id, ticket.event_id, ticket.marketplace_event_id)
     * so both web and app/POS sales are included.
     */
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

    /**
     * Overview: KPIs for the last 12 months + monthly time series for
     * events, tickets, revenue. Powers the top-of-page cards + the
     * yearly charts on the admin UI.
     */
    public function overview(Artist $artist): array
    {
        return Cache::remember("api:analytics:artist:{$artist->id}:overview", self::CACHE_TTL, function () use ($artist) {
            $eventIds = $this->artistEventIds($artist->id);
            $orderIds = $this->artistOrderIds($eventIds);

            $totalEvents = count($eventIds);
            $totalTickets = 0;
            $totalRevenue = 0;
            $uniqueBuyers = 0;

            if (!empty($orderIds)) {
                $cs = DB::table('orders as o')
                    ->join('tickets as t', 't.order_id', '=', 'o.id')
                    ->whereIn('o.id', $orderIds)
                    ->select(
                        DB::raw('COUNT(t.id) as total_tickets'),
                        DB::raw('COUNT(DISTINCT COALESCE(o.marketplace_customer_id, o.customer_id)) as unique_buyers')
                    )
                    ->first();
                $totalTickets = (int) ($cs->total_tickets ?? 0);
                $uniqueBuyers = (int) ($cs->unique_buyers ?? 0);
                $totalRevenue = round((float) DB::table('orders')->whereIn('id', $orderIds)->sum('total'), 2);
            }

            [$months, $eventsSeries, $ticketsSeries, $revenueSeries] = $this->yearlySeries($artist->id);

            return [
                'artist_id' => $artist->id,
                'artist_name' => $artist->name,
                'window' => [
                    'from' => now()->subDays(365)->toDateString(),
                    'to' => now()->toDateString(),
                ],
                'kpis' => [
                    'total_events' => $totalEvents,
                    'total_tickets' => $totalTickets,
                    'total_revenue' => $totalRevenue,
                    'unique_buyers' => $uniqueBuyers,
                    'avg_tickets_per_event' => $totalEvents > 0 ? round($totalTickets / $totalEvents, 1) : 0,
                    'avg_ticket_price' => $totalTickets > 0 ? round($totalRevenue / $totalTickets, 2) : 0,
                ],
                'monthly' => [
                    'months' => $months,
                    'events' => $eventsSeries,
                    'tickets' => $ticketsSeries,
                    'revenue' => $revenueSeries,
                ],
            ];
        });
    }

    /**
     * 12-month grouped series (events + tickets + revenue per month).
     * Three aggregate queries, all indexed on event_date.
     */
    private function yearlySeries(int $artistId): array
    {
        $start = now()->startOfMonth()->subMonths(11);
        $startDate = $start->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $eventsRaw = DB::table('events')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->where('event_artist.artist_id', $artistId)
            ->whereBetween('events.event_date', [$startDate, $endDate])
            ->select(DB::raw(DB::getDriverName() === 'pgsql'
                ? "TO_CHAR(events.event_date, 'YYYY-MM') as ym"
                : "DATE_FORMAT(events.event_date, '%Y-%m') as ym"), DB::raw('COUNT(*) as cnt'))
            ->groupBy('ym')
            ->pluck('cnt', 'ym');

        $ticketsRaw = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->where('event_artist.artist_id', $artistId)
            ->whereBetween('events.event_date', [$startDate, $endDate])
            ->select(
                DB::raw(DB::getDriverName() === 'pgsql'
                    ? "TO_CHAR(events.event_date, 'YYYY-MM') as ym"
                    : "DATE_FORMAT(events.event_date, '%Y-%m') as ym"),
                DB::raw('COUNT(tickets.id) as ticket_count'),
                DB::raw('COALESCE(SUM(tickets.price), 0) as revenue')
            )
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $months = [];
        $events = [];
        $tickets = [];
        $revenue = [];
        for ($i = 0; $i < 12; $i++) {
            $d = (clone $start)->addMonths($i);
            $ym = $d->format('Y-m');
            $months[] = $d->format('Y-m'); // API surfaces canonical YYYY-MM, not localized label
            $events[] = (int) ($eventsRaw[$ym] ?? 0);
            $tickets[] = (int) ($ticketsRaw[$ym]?->ticket_count ?? 0);
            $revenue[] = round((float) ($ticketsRaw[$ym]?->revenue ?? 0), 2);
        }

        return [$months, $events, $tickets, $revenue];
    }

    /**
     * Audience personas + geographic intelligence + top venues/cities.
     * All buyer-level rows are anonymized via PiiMask; only aggregate
     * counts and city/venue names leave the platform.
     */
    public function audience(Artist $artist): array
    {
        return Cache::remember("api:analytics:artist:{$artist->id}:audience", self::CACHE_TTL, function () use ($artist) {
            $eventIds = $this->artistEventIds($artist->id);
            $orderIds = $this->artistOrderIds($eventIds);

            $personas = $this->buildPersonas($orderIds);
            $topCities = $this->topCities($artist->id);
            $topCounties = $this->topCounties($artist->id);
            $topVenues = $this->topVenues($artist->id);

            return [
                'artist_id' => $artist->id,
                'personas' => $personas,
                'top_cities' => $topCities,
                'top_counties' => $topCounties,
                'top_venues' => $topVenues,
            ];
        });
    }

    private function buildPersonas(array $orderIds): array
    {
        if (empty($orderIds)) {
            return ['clusters' => [], 'total_customers' => 0, 'with_demographics' => 0];
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
                DB::raw('SUM(o.total) as total_spent'),
                DB::raw('COUNT(DISTINCT o.id) as order_count')
            )
            ->groupBy(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'),
                DB::raw('COALESCE(mc.birth_date, c.date_of_birth)'),
                'mc.gender',
                DB::raw('COALESCE(mc.city, c.city)')
            )
            ->get()
            ->unique('buyer_id')
            ->values();

        $totalCustomers = $buyers->count();
        if ($totalCustomers === 0) {
            return ['clusters' => [], 'total_customers' => 0, 'with_demographics' => 0];
        }

        $mapped = $buyers->map(fn ($b) => (object) [
            'age_bucket' => PiiMask::ageBucket($b->birth_date),
            'gender' => $b->gender ?: 'unknown',
            'city' => $b->city,
            'total_spent' => (float) $b->total_spent,
            'order_count' => (int) $b->order_count,
        ]);

        $withDemographics = $mapped->where('age_bucket', '!=', 'unknown')->count();
        $ageDistribution = $mapped->where('age_bucket', '!=', 'unknown')->countBy('age_bucket')->sortKeys()->toArray();
        $genderOverall = $mapped->where('gender', '!=', 'unknown')->countBy('gender')->toArray();

        $clusters = $mapped
            ->where('age_bucket', '!=', 'unknown')
            ->groupBy(fn ($b) => $b->age_bucket . '_' . $b->gender)
            ->map(function ($group, $key) use ($totalCustomers) {
                [$age, $gender] = array_pad(explode('_', $key, 2), 2, 'unknown');
                $topCities = $group->whereNotNull('city')->countBy('city')->sortDesc()->take(3)->toArray();
                return [
                    'age_bucket' => $age,
                    'gender' => $gender,
                    'count' => $group->count(),
                    'share_pct' => round($group->count() / $totalCustomers * 100, 1),
                    'avg_spend' => round($group->avg('total_spent'), 2),
                    'avg_orders' => round($group->avg('order_count'), 1),
                    'top_cities' => $topCities,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->take(5)
            ->toArray();

        return [
            'clusters' => $clusters,
            'total_customers' => $totalCustomers,
            'with_demographics' => $withDemographics,
            'age_distribution' => $ageDistribution,
            'gender_distribution' => $genderOverall,
        ];
    }

    private function topCities(int $artistId): array
    {
        return DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->where('event_artist.artist_id', $artistId)
            ->whereNotNull('venues.city')
            ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
            ->select(
                'venues.city',
                DB::raw('COUNT(tickets.id) as tickets_count'),
                DB::raw('COUNT(DISTINCT COALESCE(orders.marketplace_customer_id, orders.customer_id)) as unique_buyers')
            )
            ->groupBy('venues.city')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'city' => $r->city,
                'tickets' => (int) $r->tickets_count,
                'unique_buyers' => (int) $r->unique_buyers,
            ])
            ->toArray();
    }

    private function topCounties(int $artistId): array
    {
        return DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->where('event_artist.artist_id', $artistId)
            ->whereNotNull('venues.state')
            ->whereIn('orders.status', ['paid', 'confirmed', 'completed'])
            ->select(
                'venues.state as county',
                DB::raw('COUNT(tickets.id) as tickets_count'),
                DB::raw('COUNT(DISTINCT COALESCE(orders.marketplace_customer_id, orders.customer_id)) as unique_buyers')
            )
            ->groupBy('venues.state')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'county' => $r->county,
                'tickets' => (int) $r->tickets_count,
                'unique_buyers' => (int) $r->unique_buyers,
            ])
            ->toArray();
    }

    private function topVenues(int $artistId): array
    {
        return DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $artistId)
            ->select(
                'venues.id',
                'venues.name',
                'venues.city',
                DB::raw('COUNT(tickets.id) as tickets_count')
            )
            ->groupBy('venues.id', 'venues.name', 'venues.city')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'venue_id' => (int) $r->id,
                'venue_name' => $this->decodeJsonName($r->name),
                'city' => $r->city,
                'tickets' => (int) $r->tickets_count,
            ])
            ->toArray();
    }

    /**
     * Per-event performance (sold/capacity/sell-through/check-in),
     * artist role at each event, and a superfans list (top spenders
     * across ≥3 events) with hashed identifiers only.
     */
    public function performance(Artist $artist): array
    {
        return Cache::remember("api:analytics:artist:{$artist->id}:performance", self::CACHE_TTL, function () use ($artist) {
            $eventIds = $this->artistEventIds($artist->id);
            $orderIds = $this->artistOrderIds($eventIds);

            $eventsPerf = $this->eventPerformance($artist->id, $eventIds);
            $roleComparison = $this->roleComparison($eventsPerf);
            $loyalty = $this->loyaltyBreakdown($orderIds);
            $superfans = $this->superfans($orderIds);

            $withSt = collect($eventsPerf)->whereNotNull('sell_through_pct');
            $withCi = collect($eventsPerf)->whereNotNull('checkin_rate_pct');

            return [
                'artist_id' => $artist->id,
                'events' => $eventsPerf,
                'avg_sell_through_pct' => round($withSt->avg('sell_through_pct'), 1),
                'avg_checkin_rate_pct' => round($withCi->avg('checkin_rate_pct'), 1),
                'role_comparison' => $roleComparison,
                'loyalty' => $loyalty,
                'superfans' => $superfans,
            ];
        });
    }

    private function eventPerformance(int $artistId, array $eventIds): array
    {
        if (empty($eventIds)) return [];

        $rows = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total > 0 THEN quota_total ELSE 0 END) as capacity FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artistId)
            ->select('e.id', 'e.title', 'e.event_date', 'v.name as venue_name', 'v.city as venue_city', 'ea.is_headliner', 'ea.is_co_headliner', 'ts.sold', 'ts.capacity')
            ->orderByDesc('e.event_date')
            ->get();

        // Check-in rates in a separate query
        $checkins = DB::table('tickets as t')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where('t.status', 'valid')
            ->where(function ($q) use ($eventIds) {
                $q->whereIn('tt.event_id', $eventIds)
                  ->orWhereIn('t.event_id', $eventIds)
                  ->orWhereIn('t.marketplace_event_id', $eventIds);
            })
            ->select(
                DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id) as eid'),
                DB::raw('COUNT(t.id) as tot'),
                DB::raw('SUM(CASE WHEN t.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) as ci')
            )
            ->groupBy(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'))
            ->get()
            ->keyBy('eid');

        return $rows->map(function ($r) use ($checkins) {
            $sold = (int) ($r->sold ?? 0);
            $cap = (int) ($r->capacity ?? 0);
            $ci = $checkins->get($r->id);
            $ciTotal = (int) ($ci?->tot ?? 0);
            $ciCount = (int) ($ci?->ci ?? 0);
            $role = $r->is_headliner ? 'headliner' : ($r->is_co_headliner ? 'co_headliner' : 'support');

            return [
                'event_id' => (int) $r->id,
                'title' => $this->decodeJsonName($r->title),
                'date' => $r->event_date,
                'venue_name' => $this->decodeJsonName($r->venue_name),
                'city' => $r->venue_city,
                'role' => $role,
                'sold' => $sold,
                'capacity' => $cap,
                'sell_through_pct' => $cap > 0 ? round($sold / $cap * 100, 1) : null,
                'checkin_rate_pct' => $ciTotal > 0 ? round($ciCount / $ciTotal * 100, 1) : null,
            ];
        })->toArray();
    }

    private function roleComparison(array $events): array
    {
        return collect($events)
            ->groupBy('role')
            ->map(fn ($group) => [
                'events' => $group->count(),
                'avg_sold' => round($group->avg('sold'), 0),
                'avg_sell_through_pct' => round($group->whereNotNull('sell_through_pct')->avg('sell_through_pct'), 1),
                'avg_checkin_rate_pct' => round($group->whereNotNull('checkin_rate_pct')->avg('checkin_rate_pct'), 1),
            ])
            ->toArray();
    }

    private function loyaltyBreakdown(array $orderIds): array
    {
        if (empty($orderIds)) {
            return ['one_time' => 0, 'repeat' => 0, 'superfan' => 0, 'total' => 0, 'repeat_rate_pct' => 0];
        }

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

        $oneTime = $customerEvents->where('events_attended', 1)->count();
        $repeat = $customerEvents->where('events_attended', 2)->count();
        $superfan = $customerEvents->where('events_attended', '>=', 3)->count();
        $total = $oneTime + $repeat + $superfan;

        return [
            'one_time' => $oneTime,
            'repeat' => $repeat,
            'superfan' => $superfan,
            'total' => $total,
            'repeat_rate_pct' => $total > 0 ? round(($repeat + $superfan) / $total * 100, 1) : 0,
        ];
    }

    private function superfans(array $orderIds): array
    {
        if (empty($orderIds)) return [];

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

        $superfanIds = $customerEvents->where('events_attended', '>=', 3)->pluck('buyer_id')->toArray();
        if (empty($superfanIds)) return [];

        return DB::table('orders as o')
            ->leftJoin('marketplace_customers as mc', 'mc.id', '=', 'o.marketplace_customer_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereIn(DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'), $superfanIds)
            ->whereIn('o.id', $orderIds)
            ->select(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id) as buyer_id'),
                DB::raw('COALESCE(mc.city, c.city) as city'),
                DB::raw('SUM(o.total) as total_spent'),
                DB::raw('COUNT(DISTINCT o.id) as orders')
            )
            ->groupBy(
                DB::raw('COALESCE(o.marketplace_customer_id, o.customer_id)'),
                DB::raw('COALESCE(mc.city, c.city)')
            )
            ->orderByDesc('total_spent')
            ->limit(20)
            ->get()
            ->map(function ($s) use ($customerEvents) {
                $eventsAtt = $customerEvents->where('buyer_id', $s->buyer_id)->first()?->events_attended ?? 0;
                return [
                    'buyer_hash' => PiiMask::buyerHash($s->buyer_id),
                    'city' => $s->city,
                    'events_attended' => (int) $eventsAtt,
                    'orders' => (int) $s->orders,
                    'total_spent' => round((float) $s->total_spent, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Upcoming events for the artist with sell-through, historical
     * comparison, and a simple pace-based sold-count forecast.
     */
    public function upcoming(Artist $artist): array
    {
        return Cache::remember("api:analytics:artist:{$artist->id}:upcoming", self::CACHE_TTL, function () use ($artist) {
            $eventIds = $this->artistEventIds($artist->id);
            if (empty($eventIds)) {
                return ['artist_id' => $artist->id, 'upcoming_events' => []];
            }

            $upcoming = DB::table('events as e')
                ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
                ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
                ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as capacity, SUM(tt2.quota_sold * tt2.price_cents) / 100 as revenue_sold FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
                ->where('ea.artist_id', $artist->id)
                ->where(function ($q) {
                    $q->where('e.event_date', '>=', now()->toDateString())
                      ->orWhere('e.range_end_date', '>=', now()->toDateString());
                })
                ->select('e.id', 'e.title', 'e.event_date', 'e.range_start_date', 'v.name as venue_name', 'v.city as venue_city', 'v.capacity as venue_capacity', 'ea.is_headliner', 'ts.sold', 'ts.capacity', 'ts.revenue_sold')
                ->orderBy('e.event_date')
                ->limit(10)
                ->get();

            if ($upcoming->isEmpty()) {
                return ['artist_id' => $artist->id, 'upcoming_events' => []];
            }

            // Historical average for comparison
            $pastStats = DB::table('events as e')
                ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
                ->leftJoin(DB::raw('(SELECT tt2.event_id, SUM(tt2.quota_sold) as sold, SUM(CASE WHEN tt2.quota_total > 0 THEN tt2.quota_total ELSE 0 END) as capacity FROM ticket_types tt2 GROUP BY tt2.event_id) as ts'), 'ts.event_id', '=', 'e.id')
                ->where('ea.artist_id', $artist->id)
                ->whereNotNull('e.event_date')
                ->where('e.event_date', '<', now()->toDateString())
                ->select(
                    DB::raw('AVG(ts.sold) as avg_sold'),
                    DB::raw('AVG(CASE WHEN ts.capacity > 0 THEN ts.sold * 100.0 / ts.capacity ELSE NULL END) as avg_sell_through')
                )
                ->first();

            $rows = $upcoming->map(function ($e) use ($pastStats) {
                $sold = (int) ($e->sold ?? 0);
                $cap = (int) ($e->capacity ?? 0);
                $st = $cap > 0 ? round($sold / $cap * 100, 1) : null;
                $avgSt = round((float) ($pastStats?->avg_sell_through ?? 0), 1);
                $avgSold = round((float) ($pastStats?->avg_sold ?? 0));

                $daysUntil = $e->event_date ? max(0, (int) now()->diffInDays(Carbon::parse($e->event_date), false)) : null;
                $forecastSold = null;
                if ($daysUntil !== null && $daysUntil > 0 && $sold > 0 && $avgSold > 0) {
                    // Simple 90-day sales window projection
                    $daysSelling = max(1, 90 - $daysUntil);
                    $dailyRate = $sold / $daysSelling;
                    $forecastSold = min($cap ?: 99999, (int) round($sold + ($dailyRate * $daysUntil)));
                }

                return [
                    'event_id' => (int) $e->id,
                    'title' => $this->decodeJsonName($e->title),
                    'date' => $e->event_date ?? $e->range_start_date,
                    'venue_name' => $this->decodeJsonName($e->venue_name),
                    'city' => $e->venue_city,
                    'is_headliner' => (bool) $e->is_headliner,
                    'sold' => $sold,
                    'capacity' => $cap,
                    'sell_through_pct' => $st,
                    'revenue_sold' => round((float) ($e->revenue_sold ?? 0), 2),
                    'days_until' => $daysUntil,
                    'historical_avg_sold' => $avgSold,
                    'historical_avg_sell_through_pct' => $avgSt,
                    'forecast_sold' => $forecastSold,
                ];
            })->toArray();

            return [
                'artist_id' => $artist->id,
                'upcoming_events' => $rows,
            ];
        });
    }

    private function decodeJsonName(?string $val): string
    {
        if (!$val) return '';
        if (str_starts_with($val, '{')) {
            $d = json_decode($val, true);
            if (is_array($d)) {
                return (string) ($d['en'] ?? $d['ro'] ?? reset($d) ?: $val);
            }
        }
        return $val;
    }
}
