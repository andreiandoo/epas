<?php

namespace App\Filament\Pages;

use App\Services\Tax\TaxAnalyticsService;
use BackedEnum;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Carbon\Carbon;
use Livewire\Attributes\Computed;

class TaxReports extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.pages.tax-reports';

    protected static ?string $navigationLabel = 'Tax Reports';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationParentItem = 'Taxes';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Tax Reports & Analytics';

    public ?string $startDate = null;
    public ?string $endDate = null;
    public string $period = 'month';

    public function mount(): void
    {
        $this->setPeriod('month');
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;

        switch ($period) {
            case 'week':
                $this->startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'quarter':
                $this->startDate = Carbon::now()->startOfQuarter()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfQuarter()->format('Y-m-d');
                break;
            case 'year':
                $this->startDate = Carbon::now()->startOfYear()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfYear()->format('Y-m-d');
                break;
        }
    }

    public function setCustomDates(string $start, string $end): void
    {
        $this->period = 'custom';
        $this->startDate = $start;
        $this->endDate = $end;
    }

    #[Computed]
    public function analytics(): TaxAnalyticsService
    {
        return app(TaxAnalyticsService::class);
    }

    #[Computed]
    public function tenantId(): int
    {
        return auth()->user()->tenant?->id ?? 0;
    }

    #[Computed]
    public function startCarbon(): Carbon
    {
        return Carbon::parse($this->startDate);
    }

    #[Computed]
    public function endCarbon(): Carbon
    {
        return Carbon::parse($this->endDate);
    }

    #[Computed]
    public function summary(): array
    {
        return $this->analytics->getDashboardSummary(
            $this->tenantId,
            $this->startCarbon,
            $this->endCarbon
        );
    }

    #[Computed]
    public function chartData(): array
    {
        return $this->analytics->getDailyCollectionChart(
            $this->tenantId,
            $this->startCarbon,
            $this->endCarbon
        );
    }

    #[Computed]
    public function topTaxes(): array
    {
        return $this->analytics->getTopTaxes(
            $this->tenantId,
            $this->startCarbon,
            $this->endCarbon
        );
    }

    #[Computed]
    public function byCountry(): array
    {
        return $this->analytics->getCollectionByCountry(
            $this->tenantId,
            $this->startCarbon,
            $this->endCarbon
        );
    }

    #[Computed]
    public function exemptions(): array
    {
        return $this->analytics->getExemptionReport(
            $this->tenantId,
            $this->startCarbon,
            $this->endCarbon
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $data = $this->analytics->generateTaxFilingExport(
                        $this->tenantId,
                        $this->startCarbon,
                        $this->endCarbon
                    );

                    $filename = 'tax-report-' . $this->startDate . '-to-' . $this->endDate . '.json';

                    return response()->streamDownload(function () use ($data) {
                        echo json_encode($data, JSON_PRETTY_PRINT);
                    }, $filename, [
                        'Content-Type' => 'application/json',
                    ]);
                }),
        ];
    }
}
