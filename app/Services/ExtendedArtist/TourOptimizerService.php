<?php

namespace App\Services\ExtendedArtist;

use App\Models\Artist;
use App\Models\ArtistTourScenario;
use App\Models\MarketplaceCustomer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tour Optimizer (Modulul 3 din Extended Artist).
 *
 * Reutilizează lanțul de query din FanCrm: tickets → ticket_types → events → event_artist
 * + filtru status orders ∈ SUCCESS_ORDER_STATUSES.
 *
 * 4 calcule majore:
 *  1. Status oraș (active/warm/sleeping/new) din last_event_date
 *  2. Distanțe Haversine între lat/lng pairs
 *  3. TSP nearest-neighbor pentru rută optimă
 *  4. Predicție bilete cu venue size + sezonalitate + day-of-week
 */
class TourOptimizerService
{
    public const TTL_OPPORTUNITIES = 86400;  // 24h
    public const TTL_PREDICTIONS = 86400;    // 24h
    public const TTL_WEEKDAY = 604800;       // 7d
    public const TTL_SEASONALITY = 604800;   // 7d

    // Venue size factors — % din fanii locali care vin la un venue de mărimea X
    public const VENUE_FACTORS = [
        'small' => 0.45,   // 300-500 capacity
        'medium' => 0.72,  // 800-1500
        'large' => 0.92,   // 2500+
    ];

    // Day-of-week multipliers (1 = Lun ... 7 = Dum)
    public const DOW_FACTORS = [
        1 => 0.55, // Luni
        2 => 0.65, // Marți
        3 => 0.78, // Miercuri
        4 => 0.95, // Joi
        5 => 1.20, // Vineri
        6 => 1.35, // Sâmbătă
        7 => 0.85, // Duminică
    ];

    // Public API ----------------------------------------------------------------

    public function opportunityMap(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'opportunities'),
            self::TTL_OPPORTUNITIES,
            fn () => $this->computeOpportunityMap($artist)
        );
    }

    public function predictions(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'predictions'),
            self::TTL_PREDICTIONS,
            fn () => $this->computePredictions($artist)
        );
    }

    public function weekdayPerformance(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'weekday'),
            self::TTL_WEEKDAY,
            fn () => $this->computeWeekdayPerformance($artist)
        );
    }

    public function seasonality(Artist $artist): array
    {
        return Cache::remember(
            $this->cacheKey($artist->id, 'seasonality'),
            self::TTL_SEASONALITY,
            fn () => $this->computeSeasonality($artist)
        );
    }

    public function scenarios(Artist $artist): EloquentCollection
    {
        return ArtistTourScenario::where('artist_id', $artist->id)
            ->orderByDesc('updated_at')
            ->get();
    }

    public function compareScenarios(Artist $artist, int $aId, int $bId): array
    {
        $a = ArtistTourScenario::where('artist_id', $artist->id)->findOrFail($aId);
        $b = ArtistTourScenario::where('artist_id', $artist->id)->findOrFail($bId);

        $sumA = $a->summary ?? [];
        $sumB = $b->summary ?? [];

        $delta = function ($valA, $valB) {
            if (!is_numeric($valA) || !is_numeric($valB) || $valA == 0) return 0;
            return round((($valB - $valA) / $valA) * 100, 1);
        };

        return [
            'a' => [
                'id' => $a->id,
                'name' => $a->name,
                'date_range' => $this->formatDateRange($a),
                'summary' => $sumA,
                'cities_count' => count($a->cities ?? []),
                'route' => $a->optimized_route,
            ],
            'b' => [
                'id' => $b->id,
                'name' => $b->name,
                'date_range' => $this->formatDateRange($b),
                'summary' => $sumB,
                'cities_count' => count($b->cities ?? []),
                'route' => $b->optimized_route,
            ],
            'delta' => [
                'cities' => count($b->cities ?? []) - count($a->cities ?? []),
                'duration_days' => ($sumB['duration_days'] ?? 0) - ($sumA['duration_days'] ?? 0),
                'total_distance_km' => round(($sumB['total_distance_km'] ?? 0) - ($sumA['total_distance_km'] ?? 0)),
                'total_cost_ron' => round(($sumB['total_cost_ron'] ?? 0) - ($sumA['total_cost_ron'] ?? 0)),
                'predicted_tickets' => ($sumB['predicted_tickets'] ?? 0) - ($sumA['predicted_tickets'] ?? 0),
                'predicted_revenue_ron' => round(($sumB['predicted_revenue_ron'] ?? 0) - ($sumA['predicted_revenue_ron'] ?? 0)),
                'tickets_pct' => $delta($sumA['predicted_tickets'] ?? 0, $sumB['predicted_tickets'] ?? 0),
                'cost_pct' => $delta($sumA['total_cost_ron'] ?? 0, $sumB['total_cost_ron'] ?? 0),
                'revenue_pct' => $delta($sumA['predicted_revenue_ron'] ?? 0, $sumB['predicted_revenue_ron'] ?? 0),
            ],
        ];
    }

    /**
     * Optimize a route given a list of cities + constraints. Returns the optimized
     * scenario in-memory (does NOT save). Controller separately persists if user clicks save.
     */
    public function optimizeRoute(Artist $artist, array $citiesInput, array $constraints, ?Carbon $startDate = null): array
    {
        $citiesGeo = config('cities_geo', []);
        $cityStats = $this->cityStatsLookup($artist); // [normalized_key => {fans, events, last_event}]

        // Resolve coordinates and metadata for input cities
        $resolved = [];
        foreach ($citiesInput as $c) {
            $name = is_array($c) ? ($c['name'] ?? '') : (string) $c;
            $fixed = is_array($c) ? (bool) ($c['fixed'] ?? false) : false;
            if ($name === '') continue;
            $geo = $this->resolveCityGeo($name, $citiesGeo);
            if (!$geo) continue;
            $key = $this->normalizeKey($geo['name']);
            $stats = $cityStats[$key] ?? ['fans' => 0, 'events' => 0, 'last_event' => null];
            $resolved[] = [
                'name' => $geo['name'],
                'lat' => $geo['lat'],
                'lng' => $geo['lng'],
                'fixed' => $fixed,
                'fans' => (int) $stats['fans'],
                'events' => (int) $stats['events'],
                'last_event' => $stats['last_event'],
            ];
        }

        if (count($resolved) < 2) {
            return [
                'route' => $resolved,
                'summary' => [
                    'total_distance_km' => 0,
                    'duration_days' => 0,
                    'total_cost_ron' => 0,
                    'predicted_tickets' => 0,
                    'predicted_revenue_ron' => 0,
                    'roi' => 0,
                ],
                'message' => 'Adaugă cel puțin 2 orașe pentru a optimiza ruta.',
            ];
        }

        // TSP nearest-neighbor: start from city with highest fan count (or first fixed city)
        $startIdx = 0;
        $maxFans = -1;
        foreach ($resolved as $i => $r) {
            if ($r['fixed']) {
                $startIdx = $i;
                break;
            }
            if ($r['fans'] > $maxFans) {
                $maxFans = $r['fans'];
                $startIdx = $i;
            }
        }

        $orderedRoute = [$resolved[$startIdx]];
        $remaining = array_values(array_filter($resolved, fn ($_, $i) => $i !== $startIdx, ARRAY_FILTER_USE_BOTH));

        while (count($remaining) > 0) {
            $last = end($orderedRoute);
            $nearestIdx = 0;
            $nearestDist = INF;
            foreach ($remaining as $i => $r) {
                $d = $this->haversineKm($last['lat'], $last['lng'], $r['lat'], $r['lng']);
                if ($d < $nearestDist) {
                    $nearestDist = $d;
                    $nearestIdx = $i;
                }
            }
            $orderedRoute[] = $remaining[$nearestIdx];
            array_splice($remaining, $nearestIdx, 1);
        }

        // Schedule dates: start from $startDate (or today + 7d), spacing >= minDaysBetween
        $minDaysBetween = max(1, (int) ($constraints['min_days_between'] ?? 2));
        $current = ($startDate ?? now()->addWeek())->startOfDay();
        $totalDistance = 0;
        $monthlyMultipliers = $this->seasonalityMultipliers($artist);

        $finalRoute = [];
        foreach ($orderedRoute as $i => $stop) {
            $next = $orderedRoute[$i + 1] ?? null;
            $distanceToNext = $next ? $this->haversineKm($stop['lat'], $stop['lng'], $next['lat'], $next['lng']) : null;
            if ($distanceToNext !== null) $totalDistance += $distanceToNext;

            $eventDate = $current->copy();
            $dow = (int) $eventDate->isoWeekday();
            $dowFactor = self::DOW_FACTORS[$dow] ?? 1.0;
            $monthFactor = $monthlyMultipliers[(int) $eventDate->month - 1] ?? 1.0;
            $prediction = $this->predictTickets($stop['fans'], $stop['events'], 'medium', $dowFactor, $monthFactor);

            $finalRoute[] = [
                'city' => $stop['name'],
                'lat' => $stop['lat'],
                'lng' => $stop['lng'],
                'date' => $eventDate->translatedFormat('j M Y'),
                'date_iso' => $eventDate->toDateString(),
                'day' => $eventDate->translatedFormat('l'),
                'fixed' => $stop['fixed'],
                'fans' => $stop['fans'],
                'prediction' => $prediction['estimate'],
                'confidence' => $prediction['confidence'],
                'distance_to_next_km' => $distanceToNext !== null ? (int) round($distanceToNext) : null,
            ];

            // Advance date for next stop
            if ($next) {
                $current = $current->addDays($minDaysBetween);
            }
        }

        $duration = max(1, $current->diffInDays($orderedRoute === [] ? $current : ($startDate ?? now()->addWeek())));
        $totalCost = $this->transportCostEstimate($totalDistance, count($finalRoute));
        $totalTickets = (int) collect($finalRoute)->sum('prediction');
        // Revenue estimate: assume 80 RON average ticket price (placeholder)
        $avgTicketPrice = 80;
        $revenue = $totalTickets * $avgTicketPrice;
        $roi = $totalCost > 0 ? round($revenue / $totalCost, 1) : 0;

        return [
            'route' => $finalRoute,
            'summary' => [
                'total_distance_km' => (int) round($totalDistance),
                'duration_days' => (int) abs($duration),
                'total_cost_ron' => (int) round($totalCost),
                'predicted_tickets' => $totalTickets,
                'predicted_revenue_ron' => (int) round($revenue),
                'roi' => $roi,
            ],
            'cities' => array_map(fn ($r) => [
                'name' => $r['name'],
                'fixed' => $r['fixed'],
                'lat' => $r['lat'],
                'lng' => $r['lng'],
            ], $resolved),
        ];
    }

    public function flushCache(Artist $artist): void
    {
        foreach (['opportunities', 'predictions', 'weekday', 'seasonality'] as $m) {
            Cache::forget($this->cacheKey($artist->id, $m));
        }
    }

    // Compute methods -----------------------------------------------------------

    protected function computeOpportunityMap(Artist $artist): array
    {
        $citiesGeo = config('cities_geo', []);
        $stats = $this->cityStatsLookup($artist);
        $now = now();

        $cities = [];
        $totalFansAcross = 0;
        foreach ($stats as $key => $s) {
            // Find lat/lng for this city
            $geo = $this->resolveCityGeo($s['name'], $citiesGeo);
            if (!$geo) continue;

            $monthsAgo = $s['last_event']
                ? max(0, (int) round(Carbon::parse($s['last_event'])->diffInMonths($now)))
                : null;
            $status = $this->classifyCityStatus($s['last_event'], (int) $s['events']);

            $cities[] = [
                'name' => $geo['name'],
                'lat' => $geo['lat'],
                'lng' => $geo['lng'],
                'country' => $geo['country'] ?? 'RO',
                'fans' => (int) $s['fans'],
                'events_count' => (int) $s['events'],
                'last_event' => $s['last_event'],
                'months_ago' => $monthsAgo,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
            ];
            $totalFansAcross += (int) $s['fans'];
        }

        // Sort by fans desc
        usort($cities, fn ($a, $b) => $b['fans'] <=> $a['fans']);

        // Recommendations: top cities with potential (warm/sleeping/new with fans > 100)
        $recommendations = [];
        foreach ($cities as $c) {
            if (in_array($c['status'], ['warm', 'sleeping', 'new'], true) && $c['fans'] >= 50) {
                $reason = match ($c['status']) {
                    'warm' => "Ultim concert acum {$c['months_ago']} luni. Audiența se răcește.",
                    'sleeping' => "Risc churn ridicat — {$c['fans']} fani locali n-au mai venit de {$c['months_ago']} luni.",
                    'new' => "Niciodată cântat acolo. {$c['fans']} fani câștigați prin festivaluri vecine.",
                    default => '',
                };
                $recommendations[] = [
                    'city' => $c['name'],
                    'fans' => $c['fans'],
                    'prediction' => $this->predictionRangeLabel($c['fans']),
                    'status' => $c['status'],
                    'status_label' => $c['status_label'],
                    'reason' => $reason,
                ];
            }
            if (count($recommendations) >= 4) break;
        }

        // Dormant alerts: top 1-2 sleeping cities cu fani > 200
        $dormantAlerts = [];
        foreach ($cities as $c) {
            if ($c['status'] === 'sleeping' && $c['fans'] >= 100) {
                $dormantAlerts[] = [
                    'city' => $c['name'],
                    'fans' => $c['fans'],
                    'months_ago' => $c['months_ago'],
                    'message' => "{$c['fans']} fani din {$c['name']} n-au mai venit de {$c['months_ago']} luni",
                ];
            }
            if (count($dormantAlerts) >= 2) break;
        }

        return [
            'cities' => $cities,
            'recommendations' => $recommendations,
            'dormant_alerts' => $dormantAlerts,
            'kpis' => [
                'opportunity_cities' => count($recommendations),
                'dormant_fans' => collect($cities)->where('status', 'sleeping')->sum('fans'),
                'predicted_tickets' => $this->roughTourPrediction($cities),
                'saved_scenarios' => ArtistTourScenario::where('artist_id', $artist->id)->count(),
            ],
        ];
    }

    protected function computePredictions(Artist $artist): array
    {
        $opp = $this->computeOpportunityMap($artist);
        $weekday = $this->computeWeekdayPerformance($artist);
        $seasonalityData = $this->computeSeasonality($artist);
        $monthlyMult = $this->seasonalityMultipliers($artist);
        $avgDow = array_sum(self::DOW_FACTORS) / count(self::DOW_FACTORS); // ~0.93

        $predictions = [];
        foreach ($opp['cities'] as $c) {
            $fans = (int) $c['fans'];
            $events = (int) $c['events_count'];
            // Use medium-month factor and average DOW for the prediction column estimates
            $monthAvg = array_sum($monthlyMult) / max(1, count($monthlyMult));

            $small = $this->predictTickets($fans, $events, 'small', $avgDow, $monthAvg);
            $medium = $this->predictTickets($fans, $events, 'medium', $avgDow, $monthAvg);
            $large = $this->predictTickets($fans, $events, 'large', $avgDow, $monthAvg);

            $predictions[] = [
                'city' => $c['name'],
                'fans' => $fans,
                'note' => $this->predictionNote($c),
                'small' => ['estimate' => $this->humanRange($small['estimate'], 0.85), 'confidence' => $small['confidence']],
                'medium' => ['estimate' => $this->humanRange($medium['estimate'], 0.85), 'confidence' => $medium['confidence']],
                'large' => ['estimate' => $this->humanRange($large['estimate'], 0.85), 'confidence' => $large['confidence']],
                'status' => $c['status'],
                'status_label' => $c['status_label'],
            ];
        }

        return [
            'cities' => $predictions,
            'weekday' => $weekday,
            'seasonality' => $seasonalityData,
        ];
    }

    protected function computeWeekdayPerformance(Artist $artist): array
    {
        // Average tickets sold per event, grouped by weekday of event_date.
        // Foloseste lanțul tickets → ticket_types → events → event_artist.
        $rows = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->leftJoin('tickets as t', function ($j) {
                $j->on('t.ticket_type_id', '=', 'tt.id');
            })
            ->leftJoin('orders as o', function ($j) {
                $j->on('o.id', '=', 't.order_id')
                  ->whereIn('o.status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES);
            })
            ->where('ea.artist_id', $artist->id)
            ->whereNotNull('e.event_date')
            ->select('e.id as event_id', 'e.event_date', DB::raw('COUNT(t.id) as tickets'))
            ->groupBy('e.id', 'e.event_date')
            ->get();

        $byDow = array_fill(1, 7, ['sum' => 0, 'events' => 0]);
        foreach ($rows as $r) {
            $dow = (int) Carbon::parse($r->event_date)->isoWeekday();
            $byDow[$dow]['sum'] += (int) $r->tickets;
            $byDow[$dow]['events']++;
        }

        $labels = ['Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm', 'Dum'];
        $values = [];
        for ($i = 1; $i <= 7; $i++) {
            $b = $byDow[$i];
            $values[] = $b['events'] > 0 ? (int) round($b['sum'] / $b['events']) : 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function computeSeasonality(Artist $artist): array
    {
        $rows = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->leftJoin('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->leftJoin('orders as o', function ($j) {
                $j->on('o.id', '=', 't.order_id')
                  ->whereIn('o.status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES);
            })
            ->where('ea.artist_id', $artist->id)
            ->whereNotNull('e.event_date')
            ->select('e.id as event_id', 'e.event_date', DB::raw('COUNT(t.id) as tickets'))
            ->groupBy('e.id', 'e.event_date')
            ->get();

        $byMonth = array_fill(1, 12, ['sum' => 0, 'events' => 0]);
        foreach ($rows as $r) {
            $m = (int) Carbon::parse($r->event_date)->month;
            $byMonth[$m]['sum'] += (int) $r->tickets;
            $byMonth[$m]['events']++;
        }

        $labels = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $values = [];
        for ($i = 1; $i <= 12; $i++) {
            $b = $byMonth[$i];
            $values[] = $b['events'] > 0 ? (int) round($b['sum'] / $b['events']) : 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Returnează multiplicatori per lună (1..12) calculați față de media lunară.
     * 1.0 = lună medie. > 1 = lună aglomerată. < 1 = lună slabă.
     */
    protected function seasonalityMultipliers(Artist $artist): array
    {
        $data = $this->computeSeasonality($artist);
        $values = $data['values'] ?? [];
        $avg = array_sum($values) / max(1, count($values));
        if ($avg <= 0) {
            // Fallback hardcoded RO: vară puternică, ianuarie slabă
            return [0.7, 0.75, 0.9, 1.05, 1.2, 1.3, 1.35, 1.3, 1.1, 0.95, 0.85, 0.7];
        }
        return array_map(fn ($v) => round($v / $avg, 2), $values);
    }

    // Helpers -------------------------------------------------------------------

    /**
     * Returns [normalized_key => {name, fans, events, last_event}] aggregated for the artist.
     * Folosit de opportunity map, predictions și optimize.
     */
    protected function cityStatsLookup(Artist $artist): array
    {
        // Reuse fan-crm chain: customers care au cumpărat tickete la evenimente de-ale artistului,
        // grupate pe orașul venue-ului (folosim venues.city, NU mc.city — orașul concertului contează).
        $rows = DB::table('events as e')
            ->join('event_artist as ea', 'ea.event_id', '=', 'e.id')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->leftJoin('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->leftJoin('orders as o', function ($j) {
                $j->on('o.id', '=', 't.order_id')
                  ->whereIn('o.status', MarketplaceCustomer::SUCCESS_ORDER_STATUSES);
            })
            ->where('ea.artist_id', $artist->id)
            ->whereNotNull('v.city')
            ->where('v.city', '!=', '')
            ->select(
                'v.city as city',
                DB::raw('COUNT(DISTINCT e.id) as events_count'),
                DB::raw('COUNT(DISTINCT o.marketplace_customer_id) as fans'),
                DB::raw('MAX(e.event_date) as last_event_date')
            )
            ->groupBy('v.city')
            ->get();

        $lookup = [];
        foreach ($rows as $r) {
            $key = $this->normalizeKey($r->city);
            $lookup[$key] = [
                'name' => $r->city,
                'fans' => (int) $r->fans,
                'events' => (int) $r->events_count,
                'last_event' => $r->last_event_date,
            ];
        }

        return $lookup;
    }

    protected function classifyCityStatus(?string $lastEventDate, int $eventsCount): string
    {
        if ($eventsCount === 0) return 'new';
        if (!$lastEventDate) return 'new';

        $monthsAgo = Carbon::parse($lastEventDate)->diffInMonths(now());
        if ($monthsAgo <= 6) return 'active';
        if ($monthsAgo <= 12) return 'warm';
        return 'sleeping';
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activ',
            'warm' => 'Recomandat',
            'sleeping' => 'Dormit',
            'new' => 'Neexplorat',
            default => '—',
        };
    }

    protected function predictionRangeLabel(int $fans): string
    {
        $low = (int) round($fans * 0.4);
        $high = (int) round($fans * 0.7);
        if ($low < 50) $low = max(50, $low);
        return number_format($low, 0, ',', '.') . '-' . number_format($high, 0, ',', '.');
    }

    protected function predictionNote(array $city): string
    {
        if ($city['status'] === 'new') return 'Niciodată cântat acolo · fani din festivaluri vecine';
        if ($city['status'] === 'sleeping') return ($city['months_ago'] ?? '?') . ' luni dormit · risc churn';
        if ($city['status'] === 'warm') return 'Ultim concert acum ' . ($city['months_ago'] ?? '?') . ' luni';
        $events = (int) $city['events_count'];
        return $events . ' ' . ($events === 1 ? 'eveniment' : 'evenimente') . ' · activ';
    }

    /**
     * Predicție bilete pentru o combinație (fans, events, venue size, day-of-week, month).
     * Returnează ['estimate' => int, 'confidence' => int 0-100].
     */
    protected function predictTickets(int $fans, int $events, string $venueSize, float $dowFactor, float $monthFactor): array
    {
        $venueFactor = self::VENUE_FACTORS[$venueSize] ?? 0.7;

        // Base estimate: fani × venue factor × dow × month
        $estimate = $fans * $venueFactor * $dowFactor * $monthFactor;

        // Cap at sensible venue capacity
        $caps = ['small' => 500, 'medium' => 1500, 'large' => 3500];
        $estimate = min($estimate, $caps[$venueSize] ?? 1500);
        $estimate = max(0, $estimate);

        // Confidence: based on number of past events in city
        $confidence = match (true) {
            $events >= 5 => 88,
            $events >= 3 => 78,
            $events >= 1 => 65,
            default => 45,
        };
        // Penalize confidence for small venue (sample bias smaller)
        if ($venueSize === 'large' && $events < 3) $confidence = max(30, $confidence - 15);

        return [
            'estimate' => (int) round($estimate),
            'confidence' => $confidence,
        ];
    }

    protected function transportCostEstimate(float $distanceKm, int $stopsCount): float
    {
        // 5 RON/km transport + 800 RON/oraș (cazare/locale per stop)
        return ($distanceKm * 5) + ($stopsCount * 800);
    }

    protected function roughTourPrediction(array $cities): int
    {
        // Top 5 cities medium venue prediction summed
        $top5 = array_slice($cities, 0, 5);
        $total = 0;
        foreach ($top5 as $c) {
            $p = $this->predictTickets((int) $c['fans'], (int) $c['events_count'], 'medium', 1.0, 1.0);
            $total += $p['estimate'];
        }
        return $total;
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthR = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthR * $c;
    }

    protected function normalizeKey(string $city): string
    {
        return mb_strtolower(trim(Str::ascii($city)));
    }

    protected function resolveCityGeo(string $name, array $citiesGeo): ?array
    {
        $key = $this->normalizeKey($name);
        foreach ($citiesGeo as $cityName => $coords) {
            if ($this->normalizeKey($cityName) === $key) {
                return ['name' => $cityName] + $coords;
            }
        }
        return null;
    }

    public function cacheKey(int $artistId, string $method, array $params = []): string
    {
        $version = 'v1';
        $hash = empty($params) ? '' : ':' . substr(md5(json_encode($params)), 0, 8);
        return "artist:{$artistId}:tour:{$version}:{$method}{$hash}";
    }

    protected function formatDateRange(ArtistTourScenario $s): string
    {
        if (!$s->start_date || !$s->end_date) return '—';
        return $s->start_date->translatedFormat('j M') . ' - ' . $s->end_date->translatedFormat('j M Y');
    }

    /**
     * Returnează un range "low-high" în jurul lui $estimate (±$band).
     * Ex: 320 ± 15% → "270-370".
     */
    protected function humanRange(int $estimate, float $accuracy = 0.85): string
    {
        $low = (int) round($estimate * $accuracy);
        $high = (int) round($estimate * (2 - $accuracy));
        if ($low === 0 && $high === 0) return '0';
        return number_format($low, 0, ',', '.') . '-' . number_format($high, 0, ',', '.');
    }
}
