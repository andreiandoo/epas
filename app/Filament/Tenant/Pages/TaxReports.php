<?php

namespace App\Filament\Tenant\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Services\Tax\TaxReportService;

class TaxReports extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Tax Reports';
    protected static \UnitEnum|string|null $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.tenant.pages.tax-reports';

    public string $filterStatus = 'all';
    public string $filterPeriod = 'all';

    public function getTitle(): string
    {
        return 'Tax Reports';
    }

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return [
                'report' => null,
                'upcomingDeadlines' => [],
                'overduePayments' => [],
                'taxSummary' => [],
            ];
        }

        $service = app(TaxReportService::class);
        $report = $service->getEventsTaxReport($tenant);

        // Apply filters
        $events = collect($report['events']);

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
            'tenant' => $tenant,
            'report' => $report,
            'filteredEvents' => $events->values()->toArray(),
            'filteredTotals' => $filteredTotals,
            'upcomingDeadlines' => $service->getUpcomingDeadlines($tenant, 30),
            'overduePayments' => $service->getOverduePayments($tenant),
            'taxSummary' => $service->getTaxSummaryByType($tenant),
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
}
