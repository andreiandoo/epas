<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Services\Tracking\AlertTriggerService;
use App\Services\Tracking\WinBackCampaignService;
use App\Services\Tracking\CustomerJourneyService;
use App\Services\Tracking\DemandForecastingService;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Livewire\Attributes\Url;

class IntelligenceHub extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-light-bulb';
    protected static ?string $navigationLabel = 'Intelligence Hub';
    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Intelligence Hub';
    protected string $view = 'filament.pages.intelligence-hub';

    #[Url]
    public ?string $tenantId = null;

    public array $tenants = [];
    public array $alerts = [];
    public array $winBackStats = [];
    public array $journeyAnalytics = [];
    public array $demandForecasts = [];
    public array $platformStats = [];

    public function mount(): void
    {
        $this->tenants = Tenant::orderBy('name')->pluck('name', 'id')->toArray();
        $this->loadData();
    }

    public function updatedTenantId(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        if ($this->tenantId) {
            $this->loadTenantData((int) $this->tenantId);
        } else {
            $this->loadPlatformData();
        }
    }

    protected function loadTenantData(int $tenantId): void
    {
        // Load alerts
        try {
            $alertService = AlertTriggerService::forTenant($tenantId);
            $this->alerts = $alertService->getPendingAlerts(15)->toArray();
        } catch (\Exception $e) {
            $this->alerts = [];
        }

        // Load win-back stats
        try {
            $winBackService = WinBackCampaignService::forTenant($tenantId);
            $this->winBackStats = $winBackService->getSummaryStats();
        } catch (\Exception $e) {
            $this->winBackStats = [];
        }

        // Load journey analytics
        try {
            $journeyService = CustomerJourneyService::forTenant($tenantId);
            $this->journeyAnalytics = $journeyService->getJourneyAnalytics();
        } catch (\Exception $e) {
            $this->journeyAnalytics = [];
        }

        // Load demand forecasts
        try {
            $forecastService = DemandForecastingService::forTenant($tenantId);
            $forecasts = $forecastService->forecastAllUpcoming(60);
            $this->demandForecasts = $forecasts;
        } catch (\Exception $e) {
            $this->demandForecasts = [];
        }
    }

    protected function loadPlatformData(): void
    {
        // Aggregate stats across all tenants
        $this->platformStats = [
            'total_at_risk' => 0,
            'total_alerts' => 0,
            'tenants_with_issues' => 0,
            'high_risk_events' => 0,
        ];

        foreach (array_keys($this->tenants) as $tenantId) {
            try {
                $winBackService = WinBackCampaignService::forTenant($tenantId);
                $stats = $winBackService->getSummaryStats();
                $this->platformStats['total_at_risk'] += $stats['at_risk_customers'] ?? 0;
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function handleAlert(string $alertId, string $action): void
    {
        if (!$this->tenantId) {
            return;
        }

        AlertTriggerService::forTenant((int) $this->tenantId)
            ->markAsHandled($alertId, $action);

        $this->loadData();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Alert marked as handled',
        ]);
    }

    public function launchWinBack(string $tier): void
    {
        if (!$this->tenantId) {
            return;
        }

        dispatch(new \App\Jobs\Tracking\SendWinBackCampaignJob(
            (int) $this->tenantId,
            $tier,
            100
        ))->onQueue('emails');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Win-back campaign for '{$tier}' tier queued for sending",
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->action(fn() => $this->loadData()),
        ];
    }

    public function getHeading(): string
    {
        return 'Intelligence Hub';
    }

    public function getSubheading(): ?string
    {
        return 'AI-powered insights across all tenants - Recommendations, Win-Back, Forecasting, Journey Analytics';
    }
}
