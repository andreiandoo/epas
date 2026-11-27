<?php

namespace App\Console\Commands;

use App\Models\MarketplaceNotification;
use Illuminate\Console\Command;

/**
 * Jurnalul operatorilor păstrează doar ultimele zile.
 * Notificările organizatorilor (marketplace_organizer_id setat) se șterg doar dacă
 * se dă explicit --organizer-days, pentru că sunt istoricul lor din contul de organizator.
 */
class PruneMarketplaceNotifications extends Command
{
    protected $signature = 'marketplace-notifications:prune
        {--days=3 : Păstrează notificările operatorilor din ultimele N zile}
        {--organizer-days= : Dacă e dat, șterge și notificările organizatorilor mai vechi de N zile}
        {--dry-run : Doar numără, nu șterge}';

    protected $description = 'Șterge notificările vechi din jurnalul operatorilor marketplace';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry-run');

        $operatorQuery = MarketplaceNotification::query()
            ->whereNull('marketplace_organizer_id')
            ->where('created_at', '<', now()->subDays($days));

        $total = $this->prune($operatorQuery, "operatori (> {$days} zile)", $dry);

        if ($this->option('organizer-days') !== null) {
            $orgDays = max(1, (int) $this->option('organizer-days'));
            $orgQuery = MarketplaceNotification::query()
                ->whereNotNull('marketplace_organizer_id')
                ->where('created_at', '<', now()->subDays($orgDays));
            $total += $this->prune($orgQuery, "organizatori (> {$orgDays} zile)", $dry);
        }

        $this->info(($dry ? 'De șters: ' : 'Șterse: ') . $total);

        return self::SUCCESS;
    }

    private function prune($query, string $label, bool $dry): int
    {
        $count = (clone $query)->count();
        $this->line("{$label}: {$count}");

        if ($dry || $count === 0) {
            return $count;
        }

        $deleted = 0;
        do {
            $ids = (clone $query)->orderBy('id')->limit(2000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += MarketplaceNotification::whereIn('id', $ids)->delete();
        } while ($ids->count() === 2000);

        return $deleted;
    }
}
