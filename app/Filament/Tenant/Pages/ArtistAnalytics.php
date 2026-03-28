<?php

namespace App\Filament\Tenant\Pages;

use App\Enums\TenantType;
use App\Models\Artist;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ArtistAnalytics extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Analytics';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.tenant.pages.artist-analytics';

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

    public function getTitle(): string
    {
        return 'Analytics — ' . $this->record->name;
    }

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

    public function getViewData(): array
    {
        $cacheKey = "artist_analytics_tenant_{$this->record->id}";
        if (request()->has('refresh_analytics')) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 300, function () {
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
                    'geoIntelligence' => [],
                    'performanceDeepDive' => ['events' => [], 'customer_loyalty' => [], 'role_comparison' => []],
                    'salesIntelligence' => ['channels' => [], 'purchase_timing' => [], 'price_sensitivity' => [], 'velocity_curves' => []],
                    'expansionPlanner' => [],
                    'upcomingAnalysis' => [],
                    'opportunities' => [],
                ]
            );
        });
    }

    // ─── EVENT ANALYZER ────────────────────────────────────────────────

    public function analyzeEvent(int $eventId): void
    {
        $this->selectedEventId = $eventId;
        $event = DB::table('events as e')
            ->leftJoin('venues as v', 'v.id', '=', 'e.venue_id')
            ->leftJoin(DB::raw('(SELECT event_id, SUM(quota_sold) as sold, SUM(CASE WHEN quota_total>0 THEN quota_total ELSE 0 END) as capacity, SUM(quota_sold*price_cents)/100 as revenue FROM ticket_types GROUP BY event_id) as ts'), 'ts.event_id', '=', 'e.id')
            ->where('e.id', $eventId)
            ->select('e.*', 'v.name as venue_name', 'v.city as venue_city', 'ts.sold', 'ts.capacity as ticket_capacity', 'ts.revenue')
            ->first();

        if (!$event) { $this->eventAnalysis = null; return; }
        $title = $event->title;
        if ($title && str_starts_with($title, '{')) { $d = json_decode($title, true); $title = $d['en'] ?? $d['ro'] ?? reset($d) ?: $title; }
        $sold = (int) ($event->sold ?? 0);
        $cap = (int) ($event->ticket_capacity ?? 0);

        $this->eventAnalysis = [
            'title' => $title, 'date' => $event->event_date,
            'venue' => $event->venue_name, 'city' => $event->venue_city,
            'sold' => $sold, 'capacity' => $cap,
            'sell_through' => $cap > 0 ? round($sold / $cap * 100, 1) : null,
            'revenue' => round((float) ($event->revenue ?? 0), 0),
            'days_until' => $event->event_date ? max(0, (int) now()->diffInDays(Carbon::parse($event->event_date), false)) : null,
            'comparables' => [], 'prediction' => [],
        ];
    }

    public function updatedVenueSearch(): void
    {
        if (mb_strlen($this->venueSearch) < 2) { $this->venueResults = []; return; }
        $search = $this->venueSearch;
        $this->venueResults = \App\Models\Venue::where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")->orWhere('city', 'LIKE', "%{$search}%");
            })
            ->select('id', 'name', 'city', 'capacity')->orderBy('name')->limit(15)->get()
            ->map(function ($v) {
                $name = is_array($v->name) ? ($v->name['en'] ?? $v->name['ro'] ?? reset($v->name) ?: '') : $v->name;
                return ['id' => $v->id, 'label' => "{$name} ({$v->city})", 'name' => $name, 'city' => $v->city, 'capacity' => (int) ($v->capacity ?? 0)];
            })->toArray();
    }

    public function analyzeVenue(int $venueId): void
    {
        $this->selectedVenueId = $venueId;
        $this->venueAnalysis = null;
    }

    // ─── AUDIENCE PERSONAS ────────────────────────────────────────────

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
}
