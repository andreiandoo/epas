<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purge ALL orders + emitted tickets for an event (test-data cleanup).
 *
 * IRREVERSIBLE. Scope = every ticket whose ticket_type belongs to the event,
 * plus every order those tickets belong to. Refuses to run if any of those
 * orders also carries a ticket from a DIFFERENT event (would delete unrelated
 * data). All FK-child rows are discovered dynamically from the DB and removed
 * first, so nothing is left dangling in any report.
 *
 * Optionally drops specific ticket types entirely (--delete-ticket-types).
 * Remaining ticket types keep their catalog row but get quota_sold reset to 0.
 *
 *   php artisan event:purge-sales 4234 --delete-ticket-types=11364,11365 --dry-run
 *   php artisan event:purge-sales 4234 --delete-ticket-types=11364,11365
 */
class PurgeEventSalesCommand extends Command
{
    protected $signature = 'event:purge-sales
        {event : Event id whose orders + emitted tickets to delete}
        {--delete-ticket-types= : Comma-separated ticket_type ids to also DROP entirely}
        {--dry-run : Show what would be deleted without writing}';

    protected $description = 'Delete ALL orders + emitted tickets for an event (test-data cleanup). Irreversible.';

    public function handle(): int
    {
        $eventId = (int) $this->argument('event');
        $event = Event::find($eventId);
        if (!$event) {
            $this->error("Event {$eventId} not found.");
            return self::FAILURE;
        }

        $ticketTypeIds = TicketType::where('event_id', $eventId)->pluck('id')->all();
        $ticketIds = Ticket::whereIn('ticket_type_id', $ticketTypeIds)->pluck('id')->all();
        $orderIds = Ticket::whereIn('id', $ticketIds)->pluck('order_id')->filter()->unique()->values()->all();

        // Safety: never touch an order that also holds tickets from another event.
        if (!empty($orderIds)) {
            $foreign = Ticket::whereIn('order_id', $orderIds)
                ->whereNotIn('ticket_type_id', $ticketTypeIds)
                ->exists();
            if ($foreign) {
                $this->error('ABORT: some of these orders contain tickets from another event. Manual review required.');
                return self::FAILURE;
            }
        }

        $dropTypeIds = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('delete-ticket-types')))));

        $this->info("Event {$eventId}: " . count($ticketTypeIds) . ' ticket types, ' . count($ticketIds) . ' tickets, ' . count($orderIds) . ' orders.');
        if ($dropTypeIds) {
            $this->info('Ticket types to DROP entirely: ' . implode(',', $dropTypeIds));
        }

        $driver = DB::getDriverName();
        $ticketChildren = $this->referencingTables('tickets', $driver);
        $orderChildren = $this->referencingTables('orders', $driver);

        $this->line('--- child rows that will be deleted (non-empty only) ---');
        foreach ($ticketChildren as [$t, $c]) {
            $n = empty($ticketIds) ? 0 : DB::table($t)->whereIn($c, $ticketIds)->count();
            if ($n) {
                $this->line("  {$t}.{$c}: {$n}");
            }
        }
        foreach ($orderChildren as [$t, $c]) {
            if ($t === 'tickets') {
                continue; // deleted directly below
            }
            $n = empty($orderIds) ? 0 : DB::table($t)->whereIn($c, $orderIds)->count();
            if ($n) {
                $this->line("  {$t}.{$c}: {$n}");
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing deleted.');
            return self::SUCCESS;
        }

        if (!$this->confirm('This is IRREVERSIBLE and deletes production rows. Proceed?')) {
            $this->warn('Cancelled.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($ticketIds, $orderIds, $ticketChildren, $orderChildren, $ticketTypeIds, $dropTypeIds, $eventId) {
            // 1) rows referencing the tickets
            foreach ($ticketChildren as [$t, $c]) {
                if (!empty($ticketIds)) {
                    DB::table($t)->whereIn($c, $ticketIds)->delete();
                }
            }
            // 2) the tickets themselves
            if (!empty($ticketIds)) {
                Ticket::whereIn('id', $ticketIds)->delete();
            }
            // 3) rows referencing the orders (order_items, etc.) — tickets already gone
            foreach ($orderChildren as [$t, $c]) {
                if ($t === 'tickets') {
                    continue;
                }
                if (!empty($orderIds)) {
                    DB::table($t)->whereIn($c, $orderIds)->delete();
                }
            }
            // 4) the orders
            if (!empty($orderIds)) {
                Order::whereIn('id', $orderIds)->delete();
            }
            // 5) drop requested ticket types (their tickets are already deleted)
            if ($dropTypeIds) {
                TicketType::whereIn('id', $dropTypeIds)->delete();
            }
            // 6) reset counters so no report shows stale numbers
            TicketType::where('event_id', $eventId)->update(['quota_sold' => 0]);
            $ev = Event::find($eventId);
            if ($ev) {
                foreach (['total_tickets_sold', 'total_revenue', 'tickets_sold', 'revenue'] as $col) {
                    if (Schema::hasColumn('events', $col)) {
                        $ev->{$col} = 0;
                    }
                }
                $ev->save();
            }
        });

        $this->info('Purge complete. Run `php artisan cache:clear` so dashboard/report caches refresh.');
        return self::SUCCESS;
    }

    /**
     * All (table, column) pairs whose FK references {parent}.id, discovered
     * from the live schema so we never miss a child table.
     *
     * @return array<int, array{0:string,1:string}>
     */
    protected function referencingTables(string $parent, string $driver): array
    {
        if ($driver === 'pgsql') {
            $rows = DB::select(
                "SELECT tc.table_name AS t, kcu.column_name AS c
                 FROM information_schema.table_constraints tc
                 JOIN information_schema.key_column_usage kcu
                   ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
                 JOIN information_schema.constraint_column_usage ccu
                   ON tc.constraint_name = ccu.constraint_name AND tc.table_schema = ccu.table_schema
                 WHERE tc.constraint_type = 'FOREIGN KEY'
                   AND ccu.table_name = ? AND ccu.column_name = 'id'",
                [$parent]
            );
        } else {
            $rows = DB::select(
                "SELECT TABLE_NAME AS t, COLUMN_NAME AS c
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = 'id'
                   AND TABLE_SCHEMA = DATABASE()",
                [$parent]
            );
        }

        return array_map(fn ($r) => [$r->t, $r->c], $rows);
    }
}
