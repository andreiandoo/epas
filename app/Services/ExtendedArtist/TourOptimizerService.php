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
     *
     * citiesInput: array de objecte cu { name, fixed?, date?, venue_id? }
     *   - date: dacă e setată, fixează data evenimentului în acel oraș (ISO)
     *   - venue_id: dacă e setată, prediction-ul folosește capacitatea venue-ului
     * constraints poate include `tour_config` cu vehicles, fuel, people, rooms, meal_price
     */
    public function optimizeRoute(Artist $artist, array $citiesInput, array $constraints, ?Carbon $startDate = null): array
    {
        $citiesGeo = config('cities_geo', []);
        $cityStats = $this->cityStatsLookup($artist);
        $config = $this->normalizeTourConfig($constraints['tour_config'] ?? []);
        $venueLookup = $this->venuesLookupForInput($citiesInput);

        // Resolve coordinates and metadata for input cities (preserve user-supplied order
        // — userul deja poate să dea drag-reorder, deci respectăm ordinea când nu există date fixe)
        $resolved = [];
        foreach ($citiesInput as $c) {
            $name = is_array($c) ? ($c['name'] ?? '') : (string) $c;
            $fixed = is_array($c) ? (bool) ($c['fixed'] ?? false) : false;
            $providedDate = is_array($c) ? ($c['date'] ?? null) : null;
            $venueId = is_array($c) ? ($c['venue_id'] ?? null) : null;
            if ($name === '') continue;
            $geo = $this->resolveCityGeo($name, $citiesGeo);
            if (!$geo) continue;
            $key = $this->normalizeKey($geo['name']);
            $stats = $cityStats[$key] ?? ['fans' => 0, 'events' => 0, 'last_event' => null];
            $venue = $venueId && isset($venueLookup[$venueId]) ? $venueLookup[$venueId] : null;
            $resolved[] = [
                'name' => $geo['name'],
                'lat' => $venue['lat'] ?? $geo['lat'],
                'lng' => $venue['lng'] ?? $geo['lng'],
                'fixed' => $fixed,
                'fans' => (int) $stats['fans'],
                'events' => (int) $stats['events'],
                'last_event' => $stats['last_event'],
                'provided_date' => $providedDate ? Carbon::parse($providedDate)->toDateString() : null,
                'venue_id' => $venueId,
                'venue' => $venue,
            ];
        }

        if (count($resolved) < 2) {
            return [
                'route' => $resolved,
                'summary' => $this->emptySummary(),
                'message' => 'Adaugă cel puțin 2 orașe pentru a optimiza ruta.',
            ];
        }

        // Dacă cel puțin un oraș are dată fixă → respectăm ordinea cronologică implicată,
        // sortăm cele cu dată după dată, și împrăștiem celelalte între ele cu nearest-neighbor
        $hasAnyFixedDate = !empty(array_filter($resolved, fn ($r) => $r['provided_date']));

        if ($hasAnyFixedDate) {
            // Sort all stops with a date by date, păstrează cele fără în ordinea introdusă (dar le inserăm
            // după nearest-neighbor între cele cu date)
            $orderedRoute = $this->orderRouteWithFixedDates($resolved);
        } else {
            // Pure TSP nearest-neighbor — start from city with highest fan count (or first fixed city)
            $orderedRoute = $this->nearestNeighborOrder($resolved);
        }

        // Schedule dates
        $minDaysBetween = max(1, (int) ($constraints['min_days_between'] ?? 2));
        $defaultStart = ($startDate ?? now()->addWeek())->startOfDay();
        $current = $defaultStart->copy();
        $monthlyMultipliers = $this->seasonalityMultipliers($artist);
        $finalRoute = [];
        $totalDistance = 0;

        foreach ($orderedRoute as $i => $stop) {
            $next = $orderedRoute[$i + 1] ?? null;
            $distanceToNext = $next ? $this->haversineKm($stop['lat'], $stop['lng'], $next['lat'], $next['lng']) : null;
            if ($distanceToNext !== null) $totalDistance += $distanceToNext;

            // Date: dacă userul a dat dată specifică, folosim aceea. Altfel, $current.
            if (!empty($stop['provided_date'])) {
                $eventDate = Carbon::parse($stop['provided_date'])->startOfDay();
                $current = $eventDate->copy(); // sincronizează "current" cu data fixă
            } else {
                $eventDate = $current->copy();
            }

            $dow = (int) $eventDate->isoWeekday();
            $dowFactor = self::DOW_FACTORS[$dow] ?? 1.0;
            $monthFactor = $monthlyMultipliers[(int) $eventDate->month - 1] ?? 1.0;

            // Venue size bucket: bazat pe capacity_total. Override pentru predicție.
            $venueSize = $stop['venue'] ? $this->venueSizeBucket((int) ($stop['venue']['capacity_total'] ?? 0)) : 'medium';
            $prediction = $this->predictTickets($stop['fans'], $stop['events'], $venueSize, $dowFactor, $monthFactor);

            // Cost combustibil pentru leg-ul ANTERIOR (de la stop precedent la acesta).
            // Pentru primul stop nu există leg precedent → 0.
            $prevDistance = $finalRoute[count($finalRoute) - 1]['distance_to_next_km'] ?? 0;
            $fuelCost = $this->fuelCost((float) $prevDistance, $config);

            // Cazare: nopți între acest stop și următorul (sau ziua de încheiere)
            $nightsHere = $next ? $this->daysBetween($eventDate, null) : 1; // placeholder, set după loop
            // Placeholder, computed after loop completes for accuracy

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
                'venue_id' => $stop['venue_id'],
                'venue_name' => $stop['venue']['name'] ?? null,
                'venue_address' => $stop['venue']['address'] ?? null,
                'venue_capacity' => $stop['venue']['capacity_total'] ?? null,
                'venue_size' => $venueSize,
                'fuel_cost' => (int) round($fuelCost),
            ];

            // Advance current date if next stop has no fixed date
            if ($next) {
                $nextProvided = $next['provided_date'] ?? null;
                $current = $nextProvided
                    ? Carbon::parse($nextProvided)->startOfDay()
                    : $current->copy()->addDays($minDaysBetween);
            }
        }

        // After loop: compute accommodation_cost și meal_cost per stop, plus duration totală
        $firstDate = isset($finalRoute[0]) ? Carbon::parse($finalRoute[0]['date_iso']) : $defaultStart;
        $lastDate = isset($finalRoute[count($finalRoute) - 1])
            ? Carbon::parse(end($finalRoute)['date_iso'])
            : $defaultStart;
        $duration = max(1, $firstDate->diffInDays($lastDate) + 1);

        // Pentru fiecare stop, calculează cazarea = nopți × room_total
        // Nopți = zile până la următorul concert (cel puțin 1, pentru noaptea concertului în sine)
        for ($i = 0; $i < count($finalRoute); $i++) {
            $thisDate = Carbon::parse($finalRoute[$i]['date_iso']);
            $nextDate = isset($finalRoute[$i + 1])
                ? Carbon::parse($finalRoute[$i + 1]['date_iso'])
                : null;
            $nights = $nextDate ? max(1, $thisDate->diffInDays($nextDate)) : 1;
            $finalRoute[$i]['nights'] = $nights;
            $finalRoute[$i]['accommodation_cost'] = (int) round($this->accommodationCostPerNight($config) * $nights);
            // Mâncare: porție per stop = total_meal_cost / nr_stops (pentru afișare echitabilă)
        }

        $totalMealCost = $this->mealCost($duration, $config);
        $mealPerStop = count($finalRoute) > 0 ? (int) round($totalMealCost / count($finalRoute)) : 0;
        for ($i = 0; $i < count($finalRoute); $i++) {
            $finalRoute[$i]['meal_cost'] = $mealPerStop;
        }

        $totalFuelCost = (int) collect($finalRoute)->sum('fuel_cost');
        $totalAccommodation = (int) collect($finalRoute)->sum('accommodation_cost');
        $totalCost = $totalFuelCost + $totalAccommodation + $totalMealCost;

        $totalTickets = (int) collect($finalRoute)->sum('prediction');
        $avgTicketPrice = 80;
        $revenue = $totalTickets * $avgTicketPrice;
        $roi = $totalCost > 0 ? round($revenue / $totalCost, 1) : 0;

        return [
            'route' => $finalRoute,
            'summary' => [
                'total_distance_km' => (int) round($totalDistance),
                'duration_days' => (int) $duration,
                'total_cost_ron' => (int) round($totalCost),
                'fuel_cost_ron' => $totalFuelCost,
                'accommodation_cost_ron' => $totalAccommodation,
                'meal_cost_ron' => (int) round($totalMealCost),
                'predicted_tickets' => $totalTickets,
                'predicted_revenue_ron' => (int) round($revenue),
                'roi' => $roi,
            ],
            'cities' => array_map(fn ($r) => [
                'name' => $r['name'],
                'fixed' => $r['fixed'],
                'lat' => $r['lat'],
                'lng' => $r['lng'],
                'date' => $r['provided_date'],
                'venue_id' => $r['venue_id'],
            ], $resolved),
            'tour_config' => $config,
        ];
    }

    /**
     * Returnează venues pentru un oraș, filtrând cele cu coordonate (ca să poată fi puse pe hartă).
     * Apelat de TourOptimizerController::venuesInCity.
     */
    public function searchVenuesInCity(string $city, int $limit = 50): array
    {
        $rows = DB::table('venues')
            ->whereRaw('LOWER(city) = ?', [mb_strtolower(trim($city))])
            ->select('id', 'name', 'address', 'city', 'capacity_total', 'lat', 'lng', 'venue_type_id')
            ->orderByDesc('capacity_total')
            ->limit($limit)
            ->get();

        return $rows->map(function ($v) {
            // Name e translatable JSON — extract localized value
            $name = $v->name;
            if ($name && str_starts_with($name, '{')) {
                $decoded = json_decode($name, true);
                if (is_array($decoded)) {
                    $name = $decoded['ro'] ?? $decoded['en'] ?? reset($decoded) ?: '—';
                }
            }
            return [
                'id' => (int) $v->id,
                'name' => $name,
                'address' => $v->address,
                'city' => $v->city,
                'capacity_total' => $v->capacity_total ? (int) $v->capacity_total : null,
                'lat' => $v->lat ? (float) $v->lat : null,
                'lng' => $v->lng ? (float) $v->lng : null,
                'size' => $v->capacity_total ? $this->venueSizeBucket((int) $v->capacity_total) : null,
            ];
        })->toArray();
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

    // ===========================================================================
    // Tour config helpers (vehicles, fuel, accommodation, meals)
    // ===========================================================================

    /**
     * Normalize tour config — fills missing fields with sensible defaults.
     */
    protected function normalizeTourConfig(array $cfg): array
    {
        $defaultVehicle = [
            'type' => 'van',
            'count' => 1,
            'capacity_seats' => 8,
            'consumption_l_100km' => 9.5,
        ];

        $vehicles = $cfg['vehicles'] ?? [];
        if (empty($vehicles)) {
            $vehicles = [$defaultVehicle];
        } else {
            $vehicles = array_map(function ($v) use ($defaultVehicle) {
                return [
                    'type' => (string) ($v['type'] ?? $defaultVehicle['type']),
                    'count' => max(1, (int) ($v['count'] ?? 1)),
                    'capacity_seats' => max(1, (int) ($v['capacity_seats'] ?? 4)),
                    'consumption_l_100km' => max(0.0, (float) ($v['consumption_l_100km'] ?? 9.5)),
                ];
            }, $vehicles);
        }

        $rooms = $cfg['rooms'] ?? ['single' => 0, 'double' => 0, 'apartment' => 0];
        $roomPrices = $cfg['room_prices'] ?? ['single' => 250, 'double' => 380, 'apartment' => 600];

        return [
            'vehicles' => $vehicles,
            'fuel_type' => (string) ($cfg['fuel_type'] ?? 'diesel'),
            'fuel_price_ron_l' => max(0.0, (float) ($cfg['fuel_price_ron_l'] ?? 7.5)),
            'people_count' => max(1, (int) ($cfg['people_count'] ?? 4)),
            'rooms' => [
                'single' => max(0, (int) ($rooms['single'] ?? 0)),
                'double' => max(0, (int) ($rooms['double'] ?? 0)),
                'apartment' => max(0, (int) ($rooms['apartment'] ?? 0)),
            ],
            'room_prices' => [
                'single' => max(0.0, (float) ($roomPrices['single'] ?? 250)),
                'double' => max(0.0, (float) ($roomPrices['double'] ?? 380)),
                'apartment' => max(0.0, (float) ($roomPrices['apartment'] ?? 600)),
            ],
            'meal_price_per_day' => max(0.0, (float) ($cfg['meal_price_per_day'] ?? 120)),
        ];
    }

    /**
     * TSP nearest-neighbor — start from highest fan count or first fixed.
     */
    protected function nearestNeighborOrder(array $resolved): array
    {
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
        return $orderedRoute;
    }

    /**
     * Când există date fixe per oraș, sortăm cronologic — orașele fără dată
     * sunt inserate cu nearest-neighbor între cele cu dată.
     */
    protected function orderRouteWithFixedDates(array $resolved): array
    {
        $withDates = array_values(array_filter($resolved, fn ($r) => !empty($r['provided_date'])));
        $withoutDates = array_values(array_filter($resolved, fn ($r) => empty($r['provided_date'])));

        usort($withDates, fn ($a, $b) => strcmp($a['provided_date'], $b['provided_date']));

        // Insert each "without-date" city greedy near where it's closest geometrically
        foreach ($withoutDates as $stop) {
            $bestIdx = count($withDates);
            $bestDist = INF;
            foreach ($withDates as $i => $existing) {
                $d = $this->haversineKm($stop['lat'], $stop['lng'], $existing['lat'], $existing['lng']);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $bestIdx = $i + 1;
                }
            }
            array_splice($withDates, $bestIdx, 0, [$stop]);
        }
        return $withDates;
    }

    protected function venuesLookupForInput(array $citiesInput): array
    {
        $ids = [];
        foreach ($citiesInput as $c) {
            $vid = is_array($c) ? ($c['venue_id'] ?? null) : null;
            if ($vid) $ids[] = (int) $vid;
        }
        if (empty($ids)) return [];

        $rows = DB::table('venues')
            ->whereIn('id', array_unique($ids))
            ->select('id', 'name', 'address', 'capacity_total', 'lat', 'lng')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $name = $r->name;
            if ($name && str_starts_with($name, '{')) {
                $decoded = json_decode($name, true);
                if (is_array($decoded)) {
                    $name = $decoded['ro'] ?? $decoded['en'] ?? reset($decoded) ?: '—';
                }
            }
            $out[(int) $r->id] = [
                'id' => (int) $r->id,
                'name' => $name,
                'address' => $r->address,
                'capacity_total' => $r->capacity_total ? (int) $r->capacity_total : null,
                'lat' => $r->lat ? (float) $r->lat : null,
                'lng' => $r->lng ? (float) $r->lng : null,
            ];
        }
        return $out;
    }

    protected function venueSizeBucket(int $capacity): string
    {
        if ($capacity <= 0) return 'medium';
        if ($capacity < 1000) return 'small';
        if ($capacity <= 3500) return 'medium';
        return 'large';
    }

    /**
     * Cost combustibil pentru o distanță dată cu config-ul actual de vehicule.
     */
    protected function fuelCost(float $distanceKm, array $config): float
    {
        if ($distanceKm <= 0) return 0;
        $totalConsumptionPerKm = 0;
        foreach ($config['vehicles'] as $v) {
            $totalConsumptionPerKm += ($v['count'] * $v['consumption_l_100km']) / 100;
        }
        return $distanceKm * $totalConsumptionPerKm * $config['fuel_price_ron_l'];
    }

    /**
     * Cost cazare pentru o noapte: suma camere × prețuri.
     */
    protected function accommodationCostPerNight(array $config): float
    {
        $rooms = $config['rooms'];
        $prices = $config['room_prices'];
        return ($rooms['single'] * $prices['single'])
            + ($rooms['double'] * $prices['double'])
            + ($rooms['apartment'] * $prices['apartment']);
    }

    /**
     * Cost mâncare pentru durata totală: people_count × meal_price × duration_days.
     */
    protected function mealCost(int $durationDays, array $config): float
    {
        return $config['people_count'] * $config['meal_price_per_day'] * $durationDays;
    }

    protected function daysBetween(Carbon $a, ?Carbon $b): int
    {
        if (!$b) return 1;
        return max(1, (int) abs($a->diffInDays($b)));
    }

    protected function emptySummary(): array
    {
        return [
            'total_distance_km' => 0,
            'duration_days' => 0,
            'total_cost_ron' => 0,
            'fuel_cost_ron' => 0,
            'accommodation_cost_ron' => 0,
            'meal_cost_ron' => 0,
            'predicted_tickets' => 0,
            'predicted_revenue_ron' => 0,
            'roi' => 0,
        ];
    }
}
