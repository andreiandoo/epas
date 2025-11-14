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

        return compact('kpis', 'months', 'events', 'tickets', 'revenue', 'from', 'to');
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
