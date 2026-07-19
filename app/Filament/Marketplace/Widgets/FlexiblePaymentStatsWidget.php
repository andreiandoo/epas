<?php

namespace App\Filament\Marketplace\Widgets;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\InstallmentAgreement;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard KPIs for flexible payments (§18bis.A): revenue by method,
 * outstanding receivables, collection and default rates.
 */
class FlexiblePaymentStatsWidget extends StatsOverviewWidget
{
    use HasMarketplaceContext;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return static::marketplaceHasMicroservice('flexible-payments');
    }

    protected function getStats(): array
    {
        $clientId = static::getMarketplaceClient()?->id;
        $base = InstallmentAgreement::query()->where('marketplace_client_id', $clientId);

        $active = (clone $base)->where('status', 'active');
        $completed = (clone $base)->where('status', 'completed')->count();
        $defaulted = (clone $base)->where('status', 'defaulted')->count();
        $total = (clone $base)->count();

        $installmentsGmv = (clone $base)->where('plan_type', 'installments')->sum('customer_total_cents');
        $bnplGmv = (clone $base)->where('plan_type', 'bnpl_single')->sum('customer_total_cents');

        // Outstanding = customer_total - collected, across active agreements.
        $outstanding = 0;
        (clone $active)->select(['id', 'customer_total_cents'])->chunkById(500, function ($rows) use (&$outstanding) {
            foreach ($rows as $a) {
                $outstanding += $a->outstandingCents();
            }
        });

        $defaultRate = $total > 0 ? round($defaulted / $total * 100, 1) : 0;
        $completionRate = $total > 0 ? round($completed / $total * 100, 1) : 0;

        return [
            Stat::make('GMV Rate', number_format($installmentsGmv / 100, 2) . ' RON')
                ->icon('heroicon-o-calendar-days')->color('primary'),
            Stat::make('GMV BNPL', number_format($bnplGmv / 100, 2) . ' RON')
                ->icon('heroicon-o-clock')->color('info'),
            Stat::make('Sold de încasat', number_format($outstanding / 100, 2) . ' RON')
                ->description($active->count() . ' planuri active')
                ->icon('heroicon-o-banknotes')->color('warning'),
            Stat::make('Rată finalizare', $completionRate . '%')
                ->description($completed . ' finalizate')
                ->icon('heroicon-o-check-circle')->color('success'),
            Stat::make('Rată default', $defaultRate . '%')
                ->description($defaulted . ' anulate')
                ->icon('heroicon-o-x-circle')->color($defaultRate > 10 ? 'danger' : 'gray'),
        ];
    }
}
