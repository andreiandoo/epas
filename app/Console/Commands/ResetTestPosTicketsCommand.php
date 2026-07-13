<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset the Test POS stock so the organizer can run another smoke pass.
 *
 * Each event's test ticket type is capped at 10; once they're used up
 * the organizer either creates more via the /marketplace/events/{id}
 * admin card or calls this command. Deletes the emitted tickets AND
 * the parent pos_test orders (they never mattered for reporting, but
 * leaving them around would clutter the Orders list on the marketplace
 * admin panel), then zeroes quota_sold on the ticket type itself.
 *
 * Filters by --event / --organizer / --all. At least one must be
 * specified so a stray run never wipes everyone's test data.
 */
class ResetTestPosTicketsCommand extends Command
{
    protected $signature = 'test-tickets:reset
        {--event= : Reset test tickets for a single event ID}
        {--organizer= : Reset for every event of a marketplace_organizer_id}
        {--all : Reset globally across every event (use with care)}
        {--dry-run : Print counts without touching the DB}';

    protected $description = 'Delete Test POS emitted tickets + orders and reset quota_sold to 0.';

    public function handle(): int
    {
        $eventId = $this->option('event');
        $organizerId = $this->option('organizer');
        $all = (bool) $this->option('all');

        if (!$eventId && !$organizerId && !$all) {
            $this->error('Specify --event=, --organizer= or --all so we know what to reset.');
            return self::INVALID;
        }

        $ticketTypesQ = TicketType::query()
            ->whereRaw("(meta->>'is_test')::boolean = true");

        if ($eventId) {
            $ticketTypesQ->where('event_id', (int) $eventId);
        } elseif ($organizerId) {
            $ticketTypesQ->whereHas('event', fn ($q) => $q->where('marketplace_organizer_id', (int) $organizerId));
        }

        $ticketTypes = $ticketTypesQ->get(['id', 'event_id']);

        if ($ticketTypes->isEmpty()) {
            $this->warn('No matching Test POS ticket types found for this scope.');
            return self::SUCCESS;
        }

        $ttIds = $ticketTypes->pluck('id')->all();
        $tickets = Ticket::whereIn('ticket_type_id', $ttIds)->get(['id', 'order_id']);
        $orderIds = $tickets->pluck('order_id')->filter()->unique()->values()->all();

        // Only wipe orders that are strictly test — we never want to
        // touch an order that also contains a real ticket (would be a
        // data model violation anyway, but defensive is cheap).
        $testOrderIds = Order::whereIn('id', $orderIds)
            ->where('source', 'pos_test')
            ->pluck('id')
            ->all();

        $this->line("Scope: {$ticketTypes->count()} ticket type(s), {$tickets->count()} ticket(s), " . count($testOrderIds) . ' pos_test order(s)');

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing deleted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($ttIds, $testOrderIds) {
            // Delete tickets FIRST — order_items usually cascade off order
            // delete, but tickets don't so we always own them explicitly.
            Ticket::whereIn('ticket_type_id', $ttIds)->delete();

            if (!empty($testOrderIds)) {
                // order_items are cascade-deleted by FK; if not, this
                // still leaves them as harmless orphans against a gone
                // order.
                Order::whereIn('id', $testOrderIds)->delete();
            }

            // Zero out the counter so the next batch can go 0 → 10 again.
            TicketType::whereIn('id', $ttIds)->update([
                'quota_sold' => 0,
                'updated_at' => now(),
            ]);
        });

        $this->info('Reset complete.');
        return self::SUCCESS;
    }
}
