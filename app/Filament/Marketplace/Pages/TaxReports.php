<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Services\Tax\TaxReportService;

class TaxReports extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Tax Reports';
    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.marketplace.pages.tax-reports';

    public string $filterOrganizer = '';
    public string $filterEvent = '';
    public string $filterStatus = 'all';
    public string $filterPeriod = 'all';
    public string $search = '';

    public function getTitle(): string
    {
        return 'Tax Reports';
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return [
                'organizerOptions' => [],
                'eventOptions' => [],
                'filteredEvents' => [],
                'filteredTotals' => ['total_revenue' => 0, 'total_tax' => 0, 'event_count' => 0],
                'hasFilters' => false,
            ];
        }

        // Organizer options
        $organizerOptions = MarketplaceOrganizer::where('marketplace_client_id', $marketplace->id)
            ->whereNotNull('verified_at')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        // Event options - only load when organizer is selected
        $eventOptions = [];
        if ($this->filterOrganizer) {
            $eventOptions = Event::where('marketplace_client_id', $marketplace->id)
                ->where('marketplace_organizer_id', $this->filterOrganizer)
                ->orderByDesc('event_date')
                ->get()
                ->mapWithKeys(function ($event) {
                    $title = $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') ?: 'Event #' . $event->id;
                    $date = $event->event_date?->format('d.m.Y') ?? '';
                    return [$event->id => $title . ($date ? " ({$date})" : '')];
                })
                ->toArray();
        }

        // Check if user has applied any filter
        $hasFilters = $this->filterOrganizer !== ''
            || $this->filterEvent !== ''
            || $this->search !== ''
            || $this->filterStatus !== 'all'
            || $this->filterPeriod !== 'all';

        if (!$hasFilters) {
            return [
                'organizerOptions' => $organizerOptions,
                'eventOptions' => $eventOptions,
                'filteredEvents' => [],
                'filteredTotals' => ['total_revenue' => 0, 'total_tax' => 0, 'event_count' => 0],
                'hasFilters' => false,
            ];
        }

        // Build filtered query
        $query = Event::where('marketplace_client_id', $marketplace->id)
            ->with(['venue', 'eventTypes', 'ticketTypes', 'marketplaceOrganizer'])
            ->orderByDesc('event_date');

        if ($this->filterOrganizer) {
            $query->where('marketplace_organizer_id', $this->filterOrganizer);
        }

        if ($this->filterEvent) {
            $query->where('id', $this->filterEvent);
        }

        if ($this->search) {
            $searchTerm = $this->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhereHas('marketplaceOrganizer', function ($oq) use ($searchTerm) {
                      $oq->where('name', 'like', "%{$searchTerm}%");
                  });
            });
        }

        // Period filter
        if ($this->filterPeriod !== 'all') {
            $now = now();
            match ($this->filterPeriod) {
                'upcoming' => $query->where('event_date', '>=', $now->toDateString()),
                'this_month' => $query->whereMonth('event_date', $now->month)->whereYear('event_date', $now->year),
                'this_quarter' => $query->whereBetween('event_date', [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()]),
                'this_year' => $query->whereYear('event_date', $now->year),
                'past' => $query->where('event_date', '<', $now->toDateString()),
                default => null,
            };
        }

        // Status filter
        if ($this->filterStatus !== 'all') {
            match ($this->filterStatus) {
                'upcoming' => $query->where('event_date', '>=', now()->toDateString()),
                'past' => $query->where('event_date', '<', now()->toDateString()),
                'cancelled' => $query->whereNotNull('cancelled_at'),
                default => null,
            };
        }

        // Limit results
        $events = $query->limit(50)->get();

        $service = app(TaxReportService::class);
        $reports = [];
        $totals = ['total_revenue' => 0, 'total_tax' => 0, 'event_count' => 0];

        foreach ($events as $event) {
            $report = $service->calculateEventTaxes($event, $marketplace);
            $reports[] = $report;
            $totals['total_revenue'] += $report['estimated_revenue'];
            $totals['total_tax'] += $report['total_tax'];
            $totals['event_count']++;
        }

        return [
            'organizerOptions' => $organizerOptions,
            'eventOptions' => $eventOptions,
            'filteredEvents' => $reports,
            'filteredTotals' => $totals,
            'hasFilters' => true,
        ];
    }

    public function updatedFilterOrganizer(): void
    {
        $this->filterEvent = '';
    }

    public function updatedFilterEvent(): void {}
    public function updatedFilterStatus(): void {}
    public function updatedFilterPeriod(): void {}
    public function updatedSearch(): void {}
}
