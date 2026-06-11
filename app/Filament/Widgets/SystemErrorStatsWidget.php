<?php

namespace App\Filament\Widgets;

use App\Models\SystemError;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-page stat cards for /admin/system-errors. Polls at the cadence
 * configured in config('system_errors.polling.stats') so the dashboard
 * stays live without a heavy realtime infrastructure.
 */
class SystemErrorStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public function __construct()
    {
        $this->pollingInterval = config('system_errors.polling.stats', 15) . 's';
    }

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $now = now();

        $criticalLastHour = SystemError::query()
            ->where('level', '>=', 500)
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();

        $criticalPrevHour = SystemError::query()
            ->where('level', '>=', 500)
            ->whereBetween('created_at', [$now->copy()->subHours(2), $now->copy()->subHour()])
            ->count();

        $criticalDelta = $criticalPrevHour > 0
            ? round((($criticalLastHour - $criticalPrevHour) / $criticalPrevHour) * 100)
            : null;

        $errorsLast24h = SystemError::query()
            ->whereIn('level', [400, 500, 550, 600])
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        $warningsLast24h = SystemError::query()
            ->where('level', 300)
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        $unacknowledged = SystemError::query()
            ->whereNull('acknowledged_at')
            ->count();

        $topCategoryRow = SystemError::query()
            ->selectRaw('category, COUNT(*) as c')
            ->where('created_at', '>=', $now->copy()->subHour())
            ->groupBy('category')
            ->orderByDesc('c')
            ->first();

        $topCategoryLabel = $topCategoryRow
            ? sprintf('%s — %d', $topCategoryRow->category, (int) $topCategoryRow->c)
            : '—';

        return [
            Stat::make('Critical (last 1h)', (string) $criticalLastHour)
                ->description($criticalDelta === null
                    ? 'no activity prev hour'
                    : ($criticalDelta >= 0 ? "+{$criticalDelta}% vs prev hour" : "{$criticalDelta}% vs prev hour"))
                ->descriptionIcon($criticalDelta !== null && $criticalDelta < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color($criticalLastHour > 0 ? 'danger' : 'success'),

            Stat::make('Errors (last 24h)', (string) $errorsLast24h)
                ->description('error+ severity')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($errorsLast24h > 100 ? 'danger' : ($errorsLast24h > 0 ? 'warning' : 'success')),

            Stat::make('Warnings (last 24h)', (string) $warningsLast24h)
                ->description('warning level')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('gray'),

            Stat::make('Top category (last 1h)', $topCategoryLabel)
                ->description('most frequent')
                ->descriptionIcon('heroicon-m-tag')
                ->color('primary'),

            Stat::make('Unacknowledged', (string) $unacknowledged)
                ->description('all-time')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($unacknowledged > 0 ? 'warning' : 'success'),
        ];
    }
}
