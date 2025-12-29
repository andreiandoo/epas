<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Services\Tax\TaxReportService;

class TaxReports extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Tax Reports';
    protected static \UnitEnum|string|null $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.marketplace.pages.tax-reports';

    public string $filterStatus = 'all';
    public string $filterPeriod = 'all';
    public string $filterEvent = 'all';

    public function getTitle(): string
    {
        return 'Tax Reports';
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return [
                'report' => null,
                'upcomingDeadlines' => [],
                'overduePayments' => [],
                'taxSummary' => [],
                'eventOptions' => [],
            ];
        }

        $service = app(TaxReportService::class);
        $report = $service->getEventsTaxReport($marketplace);

        // Get event options for selector
        $eventOptions = Event::where('marketplace_client_id', $marketplace->id)
            ->orderByDesc('event_date')
            ->orderByDesc('range_start_date')
            ->get()
            ->mapWithKeys(function ($event) {
                $title = $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') ?: 'Event #' . $event->id;
                $date = $event->start_date?->format('d M Y') ?? '';
                return [$event->id => $title . ($date ? " ({$date})" : '')];
            })
            ->toArray();

        // Apply filters
        $events = collect($report['events']);

        // Filter by event
        if ($this->filterEvent !== 'all') {
            $events = $events->filter(fn($e) => (string) $e['event']['id'] === $this->filterEvent);
        }

        // Filter by status
        if ($this->filterStatus !== 'all') {
            $events = $events->filter(fn($e) => $e['event']['status'] === $this->filterStatus);
        }

        // Filter by period
        if ($this->filterPeriod !== 'all') {
            $events = $events->filter(function ($e) {
                $date = $e['event']['date_raw'];
                if (!$date) return false;

                return match ($this->filterPeriod) {
                    'upcoming' => $date->isFuture() || $date->isToday(),
                    'this_month' => $date->isCurrentMonth(),
                    'this_quarter' => $date->isCurrentQuarter(),
                    'this_year' => $date->isCurrentYear(),
                    'past' => $date->isPast(),
                    default => true,
                };
            });
        }

        // Recalculate totals based on filtered events
        $filteredTotals = [
            'total_revenue' => $events->sum('estimated_revenue'),
            'total_tax' => $events->sum('total_tax'),
            'event_count' => $events->count(),
        ];

        return [
            'tenant' => $marketplace,
            'report' => $report,
            'filteredEvents' => $events->values()->toArray(),
            'filteredTotals' => $filteredTotals,
            'upcomingDeadlines' => $service->getUpcomingDeadlines($marketplace, 30),
            'overduePayments' => $service->getOverduePayments($marketplace),
            'taxSummary' => $service->getTaxSummaryByType($marketplace),
            'eventOptions' => $eventOptions,
        ];
    }

    public function updatedFilterStatus(): void
    {
        // Livewire will automatically re-render
    }

    public function updatedFilterPeriod(): void
    {
        // Livewire will automatically re-render
    }

    public function updatedFilterEvent(): void
    {
        // Livewire will automatically re-render
    }
}
