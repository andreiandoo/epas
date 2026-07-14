<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot cleanup for the opt-in migration of the "Test POS" ticket type.
 *
 * Test POS tickets used to be auto-provisioned on EVERY non-leisure event.
 * They are now opt-in per marketplace organizer (marketplace_organizers.
 * test_pos_enabled, default false). This command DELETES the leftover Test
 * POS ticket types — plus their emitted tickets and pos_test orders — for
 * every event whose organizer did NOT opt in (flag false, or no organizer at
 * all). Events belonging to an opted-in organizer are left untouched.
 *
 * Unlike `test-tickets:reset` (which only zeroes quota_sold + wipes emitted
 * tickets so a fresh smoke pass can run), this removes the ticket TYPE row
 * itself, so the 10-lei entry disappears from the public "Preț de la" and the
 * event's ticket list entirely.
 */
class PruneDisabledTestPosTicketsCommand extends Command
{
    protected $signature = 'test-tickets:prune-disabled
        {--dry-run : Print counts without touching the DB}';

    protected $description = 'Delete Test POS ticket types (+ tickets + pos_test orders) for organizers that did not opt in.';

    public function handle(): int
    {
        // Test POS ticket types whose event's organizer is NOT opted in.
        // whereDoesntHave covers both "flag is false" and "no organizer".
        $ticketTypes = TicketType::query()
            ->whereRaw("(meta->>'is_test')::boolean = true")
            ->whereDoesntHave('event.marketplaceOrganizer', fn ($q) => $q->where('test_pos_enabled', true))
            ->get(['id', 'event_id']);

        if ($ticketTypes->isEmpty()) {
            $this->info('Nothing to prune — no Test POS ticket types on disabled organizers.');
            return self::SUCCESS;
        }

        $ttIds = $ticketTypes->pluck('id')->all();
        $tickets = Ticket::whereIn('ticket_type_id', $ttIds)->get(['id', 'order_id']);
        $orderIds = $tickets->pluck('order_id')->filter()->unique()->values()->all();

        // Only delete orders that are strictly pos_test — never touch an order
        // that carries a real ticket.
        $testOrderIds = empty($orderIds)
            ? []
            : Order::whereIn('id', $orderIds)->where('source', 'pos_test')->pluck('id')->all();

        $this->line("Scope: {$ticketTypes->count()} ticket type(s), {$tickets->count()} ticket(s), " . count($testOrderIds) . ' pos_test order(s)');

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing deleted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($ttIds, $testOrderIds) {
            Ticket::whereIn('ticket_type_id', $ttIds)->delete();

            if (!empty($testOrderIds)) {
                Order::whereIn('id', $testOrderIds)->delete();
            }

            TicketType::whereIn('id', $ttIds)->delete();
        });

        $this->info('Prune complete — Test POS ticket types removed for disabled organizers.');
        return self::SUCCESS;
    }
}
