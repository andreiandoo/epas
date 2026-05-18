<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Marketplace\SeriesAllocator;
use Illuminate\Console\Command;

/**
 * Backfill / re-sync event_ticket_type_promo_series. Run once after
 * deploying Phase B to populate existing events; thereafter the
 * MarketplaceOrganizerPromoCodeObserver keeps it in sync automatically.
 *
 * Re-runnable — SeriesAllocator::syncForEvent is idempotent.
 *
 * Examples:
 *   php artisan series:sync                           # all marketplace events
 *   php artisan series:sync --event-id=4399           # single event
 *   php artisan series:sync --marketplace-client-id=1 # filter by mp client
 */
class SyncPromoSeriesAllocationsCommand extends Command
{
    protected $signature = 'series:sync
        {--event-id= : Single event id}
        {--marketplace-client-id= : Filter events by marketplace client id}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Sync event_ticket_type_promo_series with the current promo codes + ticket types.';

    public function handle(SeriesAllocator $allocator): int
    {
        $query = Event::query()
            ->whereNotNull('marketplace_client_id')
            ->with('ticketTypes');

        if ($id = $this->option('event-id')) {
            $query->where('id', (int) $id);
        }
        if ($mp = $this->option('marketplace-client-id')) {
            $query->where('marketplace_client_id', (int) $mp);
        }

        $events = $query->get();
        if ($events->isEmpty()) {
            $this->warn('No matching events found.');
            return self::SUCCESS;
        }

        $this->info("Syncing series for {$events->count()} event(s)...");
        $dryRun = (bool) $this->option('dry-run');

        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        $totalRows = 0;
        foreach ($events as $event) {
            if ($dryRun) {
                // For dry run, just count what the sync would produce.
                $rowsBefore = \App\Models\EventTicketTypePromoSeries::query()
                    ->whereIn('ticket_type_id', $event->ticketTypes->pluck('id'))
                    ->count();
                $this->newLine();
                $this->line("  [dry-run] event #{$event->id} '{$event->name}' — current rows: {$rowsBefore}");
            } else {
                $rows = $allocator->syncForEvent($event);
                $totalRows += $rows->count();
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        if (!$dryRun) {
            $this->info("Synced {$totalRows} total series rows across {$events->count()} events.");
        }
        return self::SUCCESS;
    }
}
