<?php

namespace App\Services\ExtendedArtist;

use App\Models\Artist;
use App\Models\ArtistFanSegment;
use App\Models\MarketplaceCustomer;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fan CRM (Audience Analytics) — sursa unică de calcul + caching pentru cele
 * 8 tab-uri din modulul 1 al pachetului Extended Artist.
 *
 * Toate query-urile filtrate pe `event_artist.artist_id = $artist->id`,
 * cu status orders ∈ SUCCESS_ORDER_STATUSES (paid/confirmed/completed/
 * partially_refunded — vezi MarketplaceCustomer::SUCCESS_ORDER_STATUSES).
 *
 * Cache: per-artist + per-method, TTL 1h. Invalidate explicit la save segment.
 */
class FanCrmService
{
    public const CACHE_TTL = 3600; // 1h

    // 6 segmente predefinite + criterii (auto-calculate, no UI edit)
    public const SEG_VIP = 'vip';
    public const SEG_LOYAL = 'loyal';
    public const SEG_NEW = 'new';
    public const SEG_DORMANT = 'dormant';
    public const SEG_LOCAL = 'local';
    public const SEG_TRAVELERS = 'travelers';

    public static function predefinedSegments(): array
    {
        return [
            self::SEG_VIP => ['name' => 'VIP', 'description' => '3+ evenimente și top 10% spend', 'color' => '#A51C30'],
            self::SEG_LOYAL => ['name' => 'Loiali', 'description' => 'Au revenit la 2+ evenimente', 'color' => '#E67E22'],
            self::SEG_NEW => ['name' => 'Noi', 'description' => 'Primul eveniment în ultimele 6 luni', 'color' => '#10B981'],
            self::SEG_DORMANT => ['name' => 'Dormiți', 'description' => '>12 luni de la ultimul eveniment', 'color' => '#F59E0B'],
            self::SEG_LOCAL => ['name' => 'Locali', 'description' => 'Toate evenimentele în același oraș', 'color' => '#3B82F6'],
            self::SEG_TRAVELERS => ['name' => 'Călători', 'description' => 'Evenimente în 2+ orașe', 'color' => '#8B5CF6'],
        ];
    }

    // =========================================================================
    // PUBLIC API — fiecare metodă cache-ată 1h per artist
    // =========================================================================

    public function overview(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'overview'),
            self::CACHE_TTL,
            fn () => $this->computeOverview($artist)
        );
    }

    public function mapData(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'map'),
            self::CACHE_TTL,
            fn () => $this->computeMapData($artist)
        );
    }

    public function predefinedSegmentsCounts(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'predefined_counts'),
            self::CACHE_TTL,
            fn () => $this->computePredefinedCounts($artist)
        );
    }

    public function customSegments(Artist $artist): Collection
    {
        return ArtistFanSegment::where('artist_id', $artist->id)
            ->orderByDesc('id')
            ->get();
    }

    public function fansList(Artist $artist, array $filters, int $page = 1, int $perPage = 25): array
    {
        $cacheKey = $this->cacheKey($artist->id, 'fans', array_merge($filters, ['page' => $page, 'per' => $perPage]));
        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->computeFansList($artist, $filters, $page, $perPage));
    }

    public function cohortMatrix(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'cohort'),
            self::CACHE_TTL,
            fn () => $this->computeCohortMatrix($artist)
        );
    }

    public function demographics(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'demographics'),
            self::CACHE_TTL,
            fn () => $this->computeDemographics($artist)
        );
    }

    public function comparison(Artist $artist, string $type = 'period', $aId = null, $bId = null): array
    {
        $cacheKey = $this->cacheKey($artist->id, 'compare', compact('type', 'aId', 'bId'));
        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->computeComparison($artist, $type, $aId, $bId));
    }

    public function topVips(Artist $artist, int $limit = 10): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'vips', ['limit' => $limit]),
            self::CACHE_TTL,
            fn () => $this->computeTopVips($artist, $limit)
        );
    }

    public function flushCache(Artist $artist): void
    {
        // Best-effort flush — la save segment, golim cache-ul pentru artistul curent
        foreach (['overview', 'map', 'predefined_counts', 'cohort', 'demographics', 'compare', 'vips'] as $m) {
            Cache::forget($this->cacheKey($artist->id, $m));
        }
        // fans list și compare pot avea multiple chei (per filter combo) — Cache::forget cu wildcard
        // nu e suportat de toate driver-ele. În practică expiră în 1h. Pentru acuratețe: cache tags
        // (Redis) ar permite Cache::tags(['artist:'.$id])->flush() — dar driverul actual e file/db.
    }

    // =========================================================================
    // AGREGARI — implementări private
    // =========================================================================

    /**
     * Query de bază: fans = customers care au comandat tickete la evenimente
     * unde artist_id = X cu order status în SUCCESS_ORDER_STATUSES.
     *
     * Trece prin `tickets` (NU `order_items`) pentru că importurile istorice
     * AmBilet populeaza doar tickets, nu order_items. Vezi
     * ImportAmbiletTicketsCommand. Live checkout populeaza ambele.
     */
    public function baseFansQuery(Artist $artist): QueryBuilder
    {
        return DB::table('marketplace_customers as mc')
            ->join('orders as o', function ($j) {
                $j->on('o.marketplace_customer_id', '=', 'mc.id')
                  ->whereIn('o.status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES);
            })
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->where('ea.artist_id', $artist->id)
            ->whereNull('mc.deleted_at');
    }

    /**
     * Per-customer aggregated stats pentru artistul X. Returnează un sub-query
     * compatibil cu join-uri ulterioare. Coloane: customer_id, events_count,
     * total_spent, first_event_date, last_event_date, cities_count.
     */
    protected function fansAggregateQuery(Artist $artist): \Illuminate\Database\Query\Builder
    {
        return $this->baseFansQuery($artist)
            ->select(
                'mc.id as customer_id',
                DB::raw('COUNT(DISTINCT e.id) as events_count'),
                DB::raw('SUM(COALESCE(t.price, tt.price_cents / 100.0, 0)) as total_spent'),
                DB::raw('MIN(e.event_date) as first_event_date'),
                DB::raw('MAX(e.event_date) as last_event_date'),
                DB::raw('COUNT(DISTINCT mc.city) as cities_count')
            )
            ->groupBy('mc.id');
    }

    protected function computeOverview(Artist $artist): array
    {
        $now = now();
        $sixMonthsAgo = $now->copy()->subMonths(6)->toDateString();
        $oneYearAgo = $now->copy()->subYear()->toDateString();
        $previousSixMonths = $now->copy()->subMonths(12)->toDateString();

        // Total fans (distinct customers)
        $totalFans = (int) DB::query()
            ->fromSub($this->fansAggregateQuery($artist), 'fa')
            ->count();

        // New fans (first_event in last 6 months) vs previous 6 months
        $newFansCurrent = $this->countFirstEventBetween($artist, $sixMonthsAgo, $now->toDateString());
        $newFansPrev = $this->countFirstEventBetween($artist, $previousSixMonths, $sixMonthsAgo);
        $newFansTrend = $newFansPrev > 0
            ? round((($newFansCurrent - $newFansPrev) / $newFansPrev) * 100, 1)
            : ($newFansCurrent > 0 ? 100 : 0);

        // Avg LTV
        $avgLtv = (float) (DB::query()
            ->fromSub($this->fansAggregateQuery($artist), 'fa')
            ->avg('fa.total_spent') ?? 0);

        // Retention: % din fani cu 2+ events
        $loyalCount = (int) DB::query()
            ->fromSub($this->fansAggregateQuery($artist), 'fa')
            ->where('fa.events_count', '>=', 2)
            ->count();
        $retentionRate = $totalFans > 0 ? round(($loyalCount / $totalFans) * 100, 1) : 0;

        // Cities covered
        $citiesCount = $this->baseFansQuery($artist)
            ->whereNotNull('mc.city')
            ->where('mc.city', '!=', '')
            ->distinct()
            ->count('mc.city');

        // Top 10 cities by fan count
        $topCities = $this->baseFansQuery($artist)
            ->whereNotNull('mc.city')
            ->where('mc.city', '!=', '')
            ->select('mc.city', DB::raw('COUNT(DISTINCT mc.id) as fans'), DB::raw('COUNT(DISTINCT e.id) as events'))
            ->groupBy('mc.city')
            ->orderByDesc('fans')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name' => $r->city,
                'fans' => (int) $r->fans,
                'events' => (int) $r->events,
                'trend' => 'flat', // calculul real cu 2 ferestre temporale e prea costisitor pentru v1
                'trendValue' => 0,
            ])
            ->toArray();

        // Top 5 countries
        $topCountries = $this->baseFansQuery($artist)
            ->whereNotNull('mc.country')
            ->where('mc.country', '!=', '')
            ->select('mc.country', DB::raw('COUNT(DISTINCT mc.id) as fans'))
            ->groupBy('mc.country')
            ->orderByDesc('fans')
            ->limit(5)
            ->get();
        $totalCountriesFans = $topCountries->sum('fans') ?: 1;
        $countries = $topCountries->map(fn ($r) => [
            'name' => $r->country,
            'flag' => $this->countryToFlag($r->country),
            'fans' => (int) $r->fans,
            'pct' => round(((int) $r->fans / $totalCountriesFans) * 100),
        ])->toArray();

        // Dormant cities — orașe unde nu am mai cântat de >12 luni
        $dormantCities = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->where('ea.artist_id', $artist->id)
            ->whereNotNull('v.city')
            ->select('v.city as name', DB::raw('MAX(e.event_date) as last_event'))
            ->groupBy('v.city')
            ->havingRaw('MAX(e.event_date) < ?', [$oneYearAgo])
            ->orderBy('last_event', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($r) use ($now) {
                $lastEvent = $r->last_event ? Carbon::parse($r->last_event) : null;
                $monthsAgo = $lastEvent ? max(0, (int) $lastEvent->diffInMonths($now)) : null;
                // Câți fani avem din acel oraș (din baza marketplace_customers)
                $fans = (int) DB::table('marketplace_customers')
                    ->where('city', $r->name)
                    ->whereNull('deleted_at')
                    ->count();
                return [
                    'name' => $r->name,
                    'fans' => $fans,
                    'lastEvent' => $lastEvent?->translatedFormat('M Y'),
                    'monthsAgo' => $monthsAgo,
                ];
            })
            ->toArray();

        // Growth chart 12 months — new fans + returning fans
        $months = [];
        $newFansSeries = [];
        $returningSeries = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $months[] = $monthStart->translatedFormat('M Y');
            $newFansSeries[] = $this->countFirstEventBetween($artist, $monthStart->toDateString(), $monthEnd->toDateString());
            // Returning = customers who attended event in this month AND had attended before
            $returningSeries[] = $this->countReturningInMonth($artist, $monthStart, $monthEnd);
        }

        // Fan type breakdown (donut)
        $counts = $this->computePredefinedCounts($artist);
        $fanTypes = [
            ['label' => 'VIP', 'value' => $counts[self::SEG_VIP] ?? 0, 'color' => '#A51C30'],
            ['label' => 'Loiali', 'value' => $counts[self::SEG_LOYAL] ?? 0, 'color' => '#E67E22'],
            ['label' => 'Noi', 'value' => $counts[self::SEG_NEW] ?? 0, 'color' => '#10B981'],
            ['label' => 'Dormiți', 'value' => $counts[self::SEG_DORMANT] ?? 0, 'color' => '#F59E0B'],
        ];

        // Insight cards (auto-generated)
        $insights = $this->generateInsights($artist, $totalFans, $newFansCurrent, $retentionRate, $dormantCities);

        return [
            'kpis' => [
                'total_fans' => $totalFans,
                'new_fans' => $newFansCurrent,
                'new_fans_trend' => $newFansTrend,
                'retention_rate' => $retentionRate,
                'avg_ltv' => round($avgLtv, 2),
                'cities_covered' => (int) $citiesCount,
            ],
            'top_cities' => $topCities,
            'countries' => $countries,
            'dormant_cities' => $dormantCities,
            'growth_chart' => [
                'labels' => $months,
                'new_fans' => $newFansSeries,
                'returning' => $returningSeries,
            ],
            'fan_types' => $fanTypes,
            'insights' => $insights,
        ];
    }

    protected function computeMapData(Artist $artist): array
    {
        $citiesGeo = config('cities_geo');
        // Build lookup case-insensitive + diacritics-stripped
        $lookup = [];
        foreach ($citiesGeo as $name => $coords) {
            $key = $this->normalizeCityKey($name);
            $lookup[$key] = ['name' => $name] + $coords;
        }

        // Fans per city
        $cityCounts = $this->baseFansQuery($artist)
            ->whereNotNull('mc.city')
            ->where('mc.city', '!=', '')
            ->select('mc.city', DB::raw('COUNT(DISTINCT mc.id) as fans'), DB::raw('COUNT(DISTINCT e.id) as events'))
            ->groupBy('mc.city')
            ->get();

        $points = [];
        foreach ($cityCounts as $row) {
            $key = $this->normalizeCityKey($row->city);
            if (isset($lookup[$key])) {
                $geo = $lookup[$key];
                $points[] = [
                    'name' => $geo['name'],
                    'lat' => $geo['lat'],
                    'lng' => $geo['lng'],
                    'country' => $geo['country'],
                    'fans' => (int) $row->fans,
                    'events' => (int) $row->events,
                ];
            }
        }

        return [
            'points' => $points,
            'center' => ['lat' => 45.9432, 'lng' => 24.9668], // RO geographic center
            'zoom' => 6,
        ];
    }

    protected function computePredefinedCounts(Artist $artist): array
    {
        $aggregateSql = $this->fansAggregateQuery($artist);

        // Re-build per segment
        $now = now();
        $sixMonthsAgo = $now->copy()->subMonths(6)->toDateString();
        $twelveMonthsAgo = $now->copy()->subMonths(12)->toDateString();

        $counts = [];

        // VIP: events>=3 AND total_spent in top 10%
        $vipCandidates = DB::query()
            ->fromSub($aggregateSql, 'fa')
            ->where('fa.events_count', '>=', 3)
            ->orderByDesc('fa.total_spent')
            ->get();
        $top10pctCutoff = max(1, (int) ceil($vipCandidates->count() * 0.10));
        $counts[self::SEG_VIP] = min($vipCandidates->count(), $top10pctCutoff);

        // Loiali: events>=2
        $counts[self::SEG_LOYAL] = (int) DB::query()
            ->fromSub($aggregateSql, 'fa')
            ->where('fa.events_count', '>=', 2)
            ->count();

        // Noi: first_event in last 6 months
        $counts[self::SEG_NEW] = (int) DB::query()
            ->fromSub($aggregateSql, 'fa')
            ->where('fa.first_event_date', '>=', $sixMonthsAgo)
            ->count();

        // Dormiți: last_event > 12 months ago
        $counts[self::SEG_DORMANT] = (int) DB::query()
            ->fromSub($aggregateSql, 'fa')
            ->where('fa.last_event_date', '<', $twelveMonthsAgo)
            ->count();

        // Locali: cities_count = 1
        $counts[self::SEG_LOCAL] = (int) DB::query()
            ->fromSub($aggregateSql, 'fa')
            ->where('fa.cities_count', '=', 1)
            ->count();

        // Călători: cities_count >= 2
        $counts[self::SEG_TRAVELERS] = (int) DB::query()
            ->fromSub($aggregateSql, 'fa')
            ->where('fa.cities_count', '>=', 2)
            ->count();

        return $counts;
    }

    protected function computeFansList(Artist $artist, array $filters, int $page, int $perPage): array
    {
        $aggregateSql = $this->fansAggregateQuery($artist);

        // Base: join customers cu agregatele
        $query = DB::table('marketplace_customers as mc')
            ->joinSub($aggregateSql, 'fa', 'fa.customer_id', '=', 'mc.id')
            ->whereNull('mc.deleted_at');

        // Apply segment filter
        if (!empty($filters['segment'])) {
            $query = $this->applySegmentFilterToQuery($query, $artist, (string) $filters['segment']);
        }

        // Apply custom segment by ID
        if (!empty($filters['custom_segment_id'])) {
            $segment = ArtistFanSegment::where('artist_id', $artist->id)
                ->where('id', $filters['custom_segment_id'])
                ->first();
            if ($segment) {
                $query = $this->applyCustomCriteriaToQuery($query, (array) $segment->criteria);
            }
        }

        // Search (name, email, city)
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('mc.first_name', 'ILIKE', $term)
                  ->orWhere('mc.last_name', 'ILIKE', $term)
                  ->orWhere('mc.email', 'ILIKE', $term)
                  ->orWhere('mc.city', 'ILIKE', $term);
            });
        }

        $total = (clone $query)->count();

        $query->select(
            'mc.id', 'mc.first_name', 'mc.last_name', 'mc.email', 'mc.phone',
            'mc.city', 'mc.country', 'mc.accepts_marketing',
            'fa.events_count', 'fa.total_spent', 'fa.last_event_date', 'fa.first_event_date'
        )->orderByDesc('fa.total_spent')->offset(($page - 1) * $perPage)->limit($perPage);

        $rows = $query->get();

        $data = $rows->map(fn ($r) => [
            'id' => $r->id,
            'name' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: '—',
            'initials' => $this->makeInitials($r->first_name, $r->last_name),
            'email' => $r->email,
            'phone' => $r->phone,
            'city' => $r->city ?: '—',
            'country' => $r->country,
            'segment' => $this->classifySegment($r),
            'events' => (int) $r->events_count,
            'ltv' => round((float) $r->total_spent, 2),
            'last_event' => $r->last_event_date ? Carbon::parse($r->last_event_date)->diffForHumans() : '—',
            'opt_in' => (bool) $r->accepts_marketing,
        ])->toArray();

        return [
            'fans' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    protected function computeCohortMatrix(Artist $artist): array
    {
        $now = now();
        $cohortStart = $now->copy()->subMonths(11)->startOfMonth();

        // Pentru fiecare cohort month, calculează retenție la M+1, M+3, M+6, M+12, M+24
        $matrix = [];
        $offsets = [1, 3, 6, 12, 24];

        for ($m = 0; $m < 12; $m++) {
            $cohortMonthStart = $cohortStart->copy()->addMonths($m);
            $cohortMonthEnd = $cohortMonthStart->copy()->endOfMonth();

            // Fani care au avut PRIMUL event în acest cohort month.
            // Folosim fromSub deoarece first_event_date este alias din SELECT (MIN(e.event_date))
            // — nu poate fi referit în WHERE direct pe query-ul cu GROUP BY.
            $cohortFansIds = collect(
                DB::query()
                    ->fromSub($this->fansAggregateQuery($artist), 'fa')
                    ->whereBetween('fa.first_event_date', [$cohortMonthStart->toDateString(), $cohortMonthEnd->toDateString()])
                    ->pluck('customer_id')
            );

            $cohortSize = $cohortFansIds->count();

            $row = [
                'month' => $cohortMonthStart->translatedFormat('M Y'),
                'size' => $cohortSize,
                'values' => [],
            ];

            foreach ($offsets as $offset) {
                $offsetEnd = $cohortMonthStart->copy()->addMonths($offset);
                if ($offsetEnd->isFuture()) {
                    $row['values'][] = null;
                    continue;
                }
                $offsetStart = $cohortMonthEnd->copy()->addDay();
                if ($cohortSize === 0) {
                    $row['values'][] = 0;
                    continue;
                }
                // Câți din cohortFansIds au revenit între offsetStart și offsetEnd
                $returned = DB::table('marketplace_customers as mc')
                    ->join('orders as o', function ($j) {
                        $j->on('o.marketplace_customer_id', '=', 'mc.id')
                          ->whereIn('o.status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES);
                    })
                    ->join('tickets as t', 't.order_id', '=', 'o.id')
                    ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                    ->join('events as e', 'e.id', '=', 'tt.event_id')
                    ->join('event_artist as ea', function ($j) use ($artist) {
                        $j->on('ea.event_id', '=', 'e.id')->where('ea.artist_id', $artist->id);
                    })
                    ->whereIn('mc.id', $cohortFansIds)
                    ->whereBetween('e.event_date', [$offsetStart->toDateString(), $offsetEnd->toDateString()])
                    ->distinct()
                    ->count('mc.id');
                $row['values'][] = round(($returned / $cohortSize) * 100, 1);
            }

            $matrix[] = $row;
        }

        // Summary KPIs
        $m3Values = collect($matrix)->pluck('values')->map(fn ($v) => $v[1] ?? null)->filter()->all();
        $m12Values = collect($matrix)->pluck('values')->map(fn ($v) => $v[3] ?? null)->filter()->all();

        return [
            'matrix' => $matrix,
            'avg_m3' => count($m3Values) > 0 ? round(array_sum($m3Values) / count($m3Values), 1) : 0,
            'avg_m12' => count($m12Values) > 0 ? round(array_sum($m12Values) / count($m12Values), 1) : 0,
            'best_cohort' => collect($matrix)
                ->filter(fn ($r) => isset($r['values'][3]) && $r['values'][3] !== null)
                ->sortByDesc(fn ($r) => $r['values'][3])
                ->first()['month'] ?? null,
        ];
    }

    protected function computeDemographics(Artist $artist): array
    {
        // Age buckets — distinct customers, with non-null birth_date
        $ageBuckets = ['18-24' => 0, '25-34' => 0, '35-44' => 0, '45-54' => 0, '55+' => 0];

        $customers = $this->baseFansQuery($artist)
            ->whereNotNull('mc.birth_date')
            ->select('mc.id', 'mc.birth_date', 'mc.gender')
            ->distinct()
            ->get();

        $genderCounts = ['Femei' => 0, 'Bărbați' => 0, 'Altele' => 0];

        foreach ($customers as $c) {
            $age = $c->birth_date ? Carbon::parse($c->birth_date)->age : null;
            if ($age !== null) {
                if ($age < 25) $ageBuckets['18-24']++;
                elseif ($age < 35) $ageBuckets['25-34']++;
                elseif ($age < 45) $ageBuckets['35-44']++;
                elseif ($age < 55) $ageBuckets['45-54']++;
                else $ageBuckets['55+']++;
            }
            $g = mb_strtolower((string) $c->gender);
            if (in_array($g, ['f', 'female', 'femeie', 'feminin'], true)) {
                $genderCounts['Femei']++;
            } elseif (in_array($g, ['m', 'male', 'masculin', 'barbat'], true)) {
                $genderCounts['Bărbați']++;
            } else {
                $genderCounts['Altele']++;
            }
        }

        $totalAges = max(1, array_sum($ageBuckets));
        $totalGenders = max(1, array_sum($genderCounts));

        return [
            'age_buckets' => collect($ageBuckets)->map(fn ($v, $k) => [
                'label' => $k,
                'pct' => round(($v / $totalAges) * 100, 1),
                'count' => $v,
            ])->values()->all(),
            'gender_split' => collect($genderCounts)->map(fn ($v, $k) => [
                'label' => $k,
                'pct' => round(($v / $totalGenders) * 100, 1),
                'count' => $v,
            ])->values()->all(),
            'has_age_data' => array_sum($ageBuckets) > 0,
            'has_gender_data' => array_sum($genderCounts) > 0,
        ];
    }

    protected function computeComparison(Artist $artist, string $type, $aId, $bId): array
    {
        // V1: doar 'period' suportat (an X vs an Y). Restul → placeholder
        if ($type !== 'period') {
            return [
                'supported' => false,
                'message' => 'Tipul "' . $type . '" va fi disponibil în versiunea următoare. Folosește "period".',
            ];
        }

        $yearA = is_numeric($aId) ? (int) $aId : (int) date('Y');
        $yearB = is_numeric($bId) ? (int) $bId : ($yearA - 1);

        $kpisA = $this->kpisForYear($artist, $yearA);
        $kpisB = $this->kpisForYear($artist, $yearB);

        // Monthly chart 12 luni pentru anul A vs anul B (new fans per month)
        $months = [];
        $aSeries = [];
        $bSeries = [];
        for ($m = 1; $m <= 12; $m++) {
            $startA = Carbon::create($yearA, $m, 1)->startOfMonth();
            $endA = $startA->copy()->endOfMonth();
            $startB = Carbon::create($yearB, $m, 1)->startOfMonth();
            $endB = $startB->copy()->endOfMonth();
            $months[] = $startA->translatedFormat('M');
            $aSeries[] = $this->countFirstEventBetween($artist, $startA->toDateString(), $endA->toDateString());
            $bSeries[] = $this->countFirstEventBetween($artist, $startB->toDateString(), $endB->toDateString());
        }

        return [
            'supported' => true,
            'type' => $type,
            'a_label' => (string) $yearA,
            'b_label' => (string) $yearB,
            'a_kpis' => $kpisA,
            'b_kpis' => $kpisB,
            'chart' => ['labels' => $months, 'a' => $aSeries, 'b' => $bSeries],
        ];
    }

    protected function computeTopVips(Artist $artist, int $limit): array
    {
        $aggregateSql = $this->fansAggregateQuery($artist);

        $rows = DB::table('marketplace_customers as mc')
            ->joinSub($aggregateSql, 'fa', 'fa.customer_id', '=', 'mc.id')
            ->whereNull('mc.deleted_at')
            ->where('fa.events_count', '>=', 3)
            ->orderByDesc('fa.total_spent')
            ->limit($limit)
            ->select(
                'mc.id', 'mc.first_name', 'mc.last_name', 'mc.city',
                'fa.events_count', 'fa.total_spent', 'fa.first_event_date'
            )
            ->get();

        return $rows->map(function ($r) {
            $firstEvent = $r->first_event_date ? Carbon::parse($r->first_event_date) : null;
            $sinceYear = $firstEvent?->year;
            $tenureYears = $sinceYear ? max(0, now()->year - $sinceYear) : 0;
            return [
                'id' => $r->id,
                'name' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: '—',
                'initials' => $this->makeInitials($r->first_name, $r->last_name),
                'city' => $r->city ?: '—',
                'since' => $sinceYear ? (string) $sinceYear : '—',
                'years' => $tenureYears,
                'events' => (int) $r->events_count,
                'ltv' => round((float) $r->total_spent, 2),
            ];
        })->toArray();
    }

    /**
     * CSV streaming pentru fans list — folosește memory-efficient writer.
     */
    public function exportFansCsv(Artist $artist, array $filters): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'fans-' . $artist->slug . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($artist, $filters) {
            $out = fopen('php://output', 'w');
            // BOM pentru Excel UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nume', 'Email', 'Telefon', 'Oraș', 'Țară', 'Segment', 'Evenimente', 'LTV (RON)', 'Ultim eveniment', 'Opt-in']);

            // Fetch in chunks of 500
            $page = 1;
            do {
                $batch = $this->fansList($artist, $filters, $page, 500);
                foreach ($batch['fans'] as $f) {
                    fputcsv($out, [
                        $f['name'],
                        $f['email'],
                        $f['phone'],
                        $f['city'],
                        $f['country'],
                        $f['segment'],
                        $f['events'],
                        $f['ltv'],
                        $f['last_event'],
                        $f['opt_in'] ? 'DA' : 'NU',
                    ]);
                }
                $page++;
            } while (!empty($batch['fans']) && $page <= $batch['pages']);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public function cacheKey(int $artistId, string $method, array $params = []): string
    {
        // Bump CACHE_VERSION when query semantics change to invalidate stale entries.
        $version = 'v3';
        $hash = empty($params) ? '' : ':' . substr(md5(json_encode($params)), 0, 8);
        return "artist:{$artistId}:fan-crm:{$version}:{$method}{$hash}";
    }

    protected function countFirstEventBetween(Artist $artist, string $from, string $to): int
    {
        return (int) DB::query()
            ->fromSub($this->fansAggregateQuery($artist), 'fa')
            ->whereBetween('fa.first_event_date', [$from, $to])
            ->count();
    }

    protected function countReturningInMonth(Artist $artist, Carbon $start, Carbon $end): int
    {
        return (int) DB::table('marketplace_customers as mc')
            ->join('orders as o', function ($j) {
                $j->on('o.marketplace_customer_id', '=', 'mc.id')
                  ->whereIn('o.status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES);
            })
            ->join('tickets as t', 't.order_id', '=', 'o.id')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->join('event_artist as ea', function ($j) use ($artist) {
                $j->on('ea.event_id', '=', 'e.id')->where('ea.artist_id', $artist->id);
            })
            ->whereBetween('e.event_date', [$start->toDateString(), $end->toDateString()])
            ->whereExists(function ($q) use ($artist, $start) {
                $q->select(DB::raw(1))
                  ->from('orders as o2')
                  ->join('tickets as t2', 't2.order_id', '=', 'o2.id')
                  ->join('ticket_types as tt2', 'tt2.id', '=', 't2.ticket_type_id')
                  ->join('events as e2', 'e2.id', '=', 'tt2.event_id')
                  ->join('event_artist as ea2', function ($j) use ($artist) {
                      $j->on('ea2.event_id', '=', 'e2.id')->where('ea2.artist_id', $artist->id);
                  })
                  ->whereColumn('o2.marketplace_customer_id', 'mc.id')
                  ->whereIn('o2.status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES)
                  ->where('e2.event_date', '<', $start->toDateString());
            })
            ->distinct()
            ->count('mc.id');
    }

    protected function kpisForYear(Artist $artist, int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay()->toDateString();
        $end = Carbon::create($year, 12, 31)->endOfDay()->toDateString();

        $totalFans = (int) DB::query()
            ->fromSub($this->fansAggregateQuery($artist), 'fa')
            ->whereBetween('fa.first_event_date', [$start, $end])
            ->count();

        $eventsCount = (int) DB::table('events')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->where('event_artist.artist_id', $artist->id)
            ->whereBetween('events.event_date', [$start, $end])
            ->count();

        $ticketsSold = (int) $this->baseFansQuery($artist)
            ->whereBetween('e.event_date', [$start, $end])
            ->count();

        $avgLtv = (float) DB::query()
            ->fromSub($this->fansAggregateQuery($artist), 'fa')
            ->whereBetween('fa.first_event_date', [$start, $end])
            ->avg('fa.total_spent') ?? 0;

        return [
            ['label' => 'Total fani', 'value' => $totalFans],
            ['label' => 'Evenimente', 'value' => $eventsCount],
            ['label' => 'Bilete vândute', 'value' => $ticketsSold],
            ['label' => 'LTV mediu (RON)', 'value' => round($avgLtv, 2)],
        ];
    }

    /**
     * Aplică filter SQL pentru un segment predefinit pe query-ul fans + agregate.
     */
    protected function applySegmentFilterToQuery($query, Artist $artist, string $segmentId): \Illuminate\Database\Query\Builder
    {
        $now = now();
        $sixMonthsAgo = $now->copy()->subMonths(6)->toDateString();
        $twelveMonthsAgo = $now->copy()->subMonths(12)->toDateString();

        switch ($segmentId) {
            case self::SEG_VIP:
                // VIP: events>=3 AND top 10% spend
                // Pentru filter live, simplificăm: events>=3 + ordoneaza desc spend
                return $query->where('fa.events_count', '>=', 3);
            case self::SEG_LOYAL:
                return $query->where('fa.events_count', '>=', 2);
            case self::SEG_NEW:
                return $query->where('fa.first_event_date', '>=', $sixMonthsAgo);
            case self::SEG_DORMANT:
                return $query->where('fa.last_event_date', '<', $twelveMonthsAgo);
            case self::SEG_LOCAL:
                return $query->where('fa.cities_count', '=', 1);
            case self::SEG_TRAVELERS:
                return $query->where('fa.cities_count', '>=', 2);
            default:
                return $query;
        }
    }

    /**
     * Aplică criterii custom (din tabelul artist_fan_segments) pe query.
     */
    protected function applyCustomCriteriaToQuery($query, array $criteria): \Illuminate\Database\Query\Builder
    {
        if (isset($criteria['events_min'])) $query->where('fa.events_count', '>=', $criteria['events_min']);
        if (isset($criteria['events_max'])) $query->where('fa.events_count', '<=', $criteria['events_max']);
        if (isset($criteria['spend_min'])) $query->where('fa.total_spent', '>=', $criteria['spend_min']);
        if (isset($criteria['spend_max'])) $query->where('fa.total_spent', '<=', $criteria['spend_max']);
        if (!empty($criteria['cities'])) $query->whereIn('mc.city', $criteria['cities']);
        if (isset($criteria['last_event_after'])) $query->where('fa.last_event_date', '>=', $criteria['last_event_after']);
        if (isset($criteria['last_event_before'])) $query->where('fa.last_event_date', '<=', $criteria['last_event_before']);
        return $query;
    }

    /**
     * Clasifică un rând de fan (cu fa.* fields) într-unul din segmentele predefinite,
     * afișat în coloana "segment" a tabelului Fan List.
     */
    protected function classifySegment($row): string
    {
        $now = now();
        $eventsCount = (int) ($row->events_count ?? 0);
        $firstEvent = $row->first_event_date ? Carbon::parse($row->first_event_date) : null;
        $lastEvent = $row->last_event_date ? Carbon::parse($row->last_event_date) : null;

        if ($lastEvent && $lastEvent->copy()->diffInMonths($now) > 12) return 'dormant';
        if ($firstEvent && $firstEvent->copy()->diffInMonths($now) <= 6) return 'new';
        if ($eventsCount >= 3) return 'vip';
        if ($eventsCount >= 2) return 'loyal';
        return 'casual';
    }

    protected function makeInitials(?string $first, ?string $last): string
    {
        $f = trim((string) $first);
        $l = trim((string) $last);
        $a = $f ? mb_strtoupper(mb_substr($f, 0, 1)) : '';
        $b = $l ? mb_strtoupper(mb_substr($l, 0, 1)) : '';
        return $a . $b ?: '?';
    }

    protected function normalizeCityKey(string $city): string
    {
        // Ascii lowercase: "București" → "bucuresti"
        $ascii = Str::ascii($city);
        return mb_strtolower(trim($ascii));
    }

    protected function countryToFlag(string $countryCode): string
    {
        $code = strtoupper(trim($countryCode));
        $map = [
            'RO' => '🇷🇴', 'BG' => '🇧🇬', 'HU' => '🇭🇺', 'RS' => '🇷🇸', 'MD' => '🇲🇩',
            'PL' => '🇵🇱', 'CZ' => '🇨🇿', 'SK' => '🇸🇰', 'AT' => '🇦🇹', 'GR' => '🇬🇷',
            'TR' => '🇹🇷', 'SI' => '🇸🇮', 'HR' => '🇭🇷', 'BA' => '🇧🇦', 'GB' => '🇬🇧',
            'ES' => '🇪🇸', 'IT' => '🇮🇹', 'DE' => '🇩🇪', 'FR' => '🇫🇷', 'BE' => '🇧🇪',
        ];
        return $map[$code] ?? '🌍';
    }

    protected function generateInsights(Artist $artist, int $totalFans, int $newFans, float $retention, array $dormantCities): array
    {
        $insights = [];

        if ($newFans > 0 && $totalFans > 0) {
            $newPct = round(($newFans / $totalFans) * 100, 1);
            $insights[] = [
                'type' => 'info',
                'icon' => '✨',
                'title' => 'Creștere de bazá',
                'text' => "Ai atras {$newFans} fani noi în ultimele 6 luni (" . $newPct . "% din baza totală).",
            ];
        }

        if ($retention < 30 && $totalFans > 50) {
            $insights[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => 'Retenție scăzută',
                'text' => "Doar {$retention}% din fani au revenit la 2+ evenimente. Considerá strategii de retenție.",
            ];
        }

        if (count($dormantCities) > 0) {
            $first = $dormantCities[0];
            $insights[] = [
                'type' => 'opportunity',
                'icon' => '💡',
                'title' => 'Oraș cu fani inactivi',
                'text' => "Nu ai mai cântat în {$first['name']} de " . ($first['monthsAgo'] ?? '?') . " luni — {$first['fans']} fani locali așteaptă o revenire.",
            ];
        }

        return $insights;
    }
}
