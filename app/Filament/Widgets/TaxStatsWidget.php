<?php

namespace App\Filament\Widgets;

use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use App\Models\Tax\TaxExemption;
use App\Services\Tax\TaxService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class TaxStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->tenant !== null;
    }

    protected function getStats(): array
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return [];
        }

        $tenantId = $tenant->id;
        $today = Carbon::today();

        // General taxes stats
        $generalActive = GeneralTax::forTenant($tenantId)->active()->validOn()->count();
        $generalTotal = GeneralTax::forTenant($tenantId)->count();
        $generalCompound = GeneralTax::forTenant($tenantId)->active()->compound()->count();

        // Local taxes stats
        $localActive = LocalTax::forTenant($tenantId)->active()->validOn()->count();
        $localTotal = LocalTax::forTenant($tenantId)->count();
        $localCompound = LocalTax::forTenant($tenantId)->active()->compound()->count();

        // Exemptions stats
        $exemptionsActive = TaxExemption::forTenant($tenantId)->active()->validOn()->count();
        $exemptionsTotal = TaxExemption::forTenant($tenantId)->count();

        // Expiring taxes
        $expiringGeneral = GeneralTax::forTenant($tenantId)
            ->active()
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', $today)
            ->where('valid_until', '<=', $today->copy()->addDays(30))
            ->count();
        $expiringLocal = LocalTax::forTenant($tenantId)
            ->active()
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', $today)
            ->where('valid_until', '<=', $today->copy()->addDays(30))
            ->count();
        $totalExpiring = $expiringGeneral + $expiringLocal;

        // Countries with local taxes
        $countriesCount = LocalTax::forTenant($tenantId)
            ->active()
            ->distinct('country')
            ->count('country');

        return [
            Stat::make('General Taxes', $generalActive)
                ->description("{$generalTotal} total, {$generalCompound} compound")
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('primary'),

            Stat::make('Local Taxes', $localActive)
                ->description("{$localTotal} total, {$localCompound} compound")
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('success'),

            Stat::make('Tax Exemptions', $exemptionsActive)
                ->description("{$exemptionsTotal} total")
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('warning'),

            Stat::make('Countries Covered', $countriesCount)
                ->description('With local taxes')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Expiring Soon', $totalExpiring)
                ->description("Next 30 days")
                ->descriptionIcon($totalExpiring > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($totalExpiring > 0 ? 'danger' : 'success'),

            Stat::make('Total Active', $generalActive + $localActive)
                ->description("Combined taxes")
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }
}
