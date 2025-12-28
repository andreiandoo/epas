<?php

namespace App\Filament\Resources\Artists\Pages;

use App\Filament\Resources\Artists\ArtistResource;
use App\Models\Artist;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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
        $topVenues = \Illuminate\Support\Facades\DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $this->record->id)
            ->select('venues.id', 'venues.name', \Illuminate\Support\Facades\DB::raw('COUNT(tickets.id) as tickets_count'))
            ->groupBy('venues.id', 'venues.name')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get();

        // Top cities by ticket sales
        $topCities = \Illuminate\Support\Facades\DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $this->record->id)
            ->whereNotNull('venues.city')
            ->select('venues.city as name', \Illuminate\Support\Facades\DB::raw('COUNT(tickets.id) as tickets_count'))
            ->groupBy('venues.city')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get();

        // Top counties/states by ticket sales
        $topCounties = \Illuminate\Support\Facades\DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('event_artist.artist_id', $this->record->id)
            ->whereNotNull('venues.state')
            ->select('venues.state as name', \Illuminate\Support\Facades\DB::raw('COUNT(tickets.id) as tickets_count'))
            ->groupBy('venues.state')
            ->orderByDesc('tickets_count')
            ->limit(10)
            ->get();

        return compact(
            'kpis', 'months', 'events', 'tickets', 'revenue', 'from', 'to',
            'artistEvents', 'artistVenues', 'artistTenants',
            'topVenues', 'topCities', 'topCounties'
        );
    }

    /** Serii pentru chart-uri (ultimele 12 luni) */
    public array $seriesMonths = [];
    public array $seriesEvents = [];
    public array $seriesTickets = [];
    public array $seriesRevenue = [];

    public function mount(\App\Models\Artist $record): void
    {
        // Route-model binding îți dă direct modelul corect
        $this->record = $record->load([
            'artistTypes:id,name,slug',
            'artistGenres:id,name,slug',
        ]);

        // IMPORTANT: Eliminăm authorize() aici – Filament verifică oricum capabilitățile pe Resource/Page
        // $this->authorize('view', $this->record);

        // Seriile pentru grafice (dacă ai helperul pe model)
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
