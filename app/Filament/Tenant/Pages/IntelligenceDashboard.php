<?php

namespace App\Filament\Tenant\Pages;

use App\Services\Tracking\AlertTriggerService;
use App\Services\Tracking\WinBackCampaignService;
use App\Services\Tracking\CustomerJourneyService;
use App\Services\Tracking\DemandForecastingService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

class IntelligenceDashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-light-bulb';
    protected static ?string $navigationLabel = 'Intelligence';
    protected static \UnitEnum|string|null $navigationGroup = 'Analytics & Tracking';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'intelligence';
    protected string $view = 'filament.tenant.pages.intelligence-dashboard';

    public array $alerts = [];
    public array $winBackStats = [];
    public array $journeyAnalytics = [];
    public array $demandForecasts = [];

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return;
        }

        $this->loadData($tenant->id);
    }

    protected function loadData(int $tenantId): void
    {
        // Load pending alerts
        try {
            $alertService = AlertTriggerService::forTenant($tenantId);
            $this->alerts = $alertService->getPendingAlerts(10)->toArray();
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
            $forecasts = $forecastService->forecastAllUpcoming(30);
            $this->demandForecasts = $forecasts['by_risk'] ?? [];
        } catch (\Exception $e) {
            $this->demandForecasts = [];
        }
    }

    public function refreshData(): void
    {
        $tenant = auth()->user()->tenant;
        if ($tenant) {
            $this->loadData($tenant->id);
        }
    }

    public function handleAlert(string $alertId, string $action): void
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return;
        }

        AlertTriggerService::forTenant($tenant->id)->markAsHandled($alertId, $action);
        $this->refreshData();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Alert marked as handled',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshData'),
        ];
    }

    public function getHeading(): string
    {
        return 'Intelligence Dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'AI-powered insights for data-driven marketing';
    }
}
