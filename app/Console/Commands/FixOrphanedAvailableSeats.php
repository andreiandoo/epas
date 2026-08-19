<?php

namespace App\Console\Commands;

use App\Models\Seating\EventSeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair seats wrongly marked 'available' while a still-valid ticket holds them.
 *
 * Root cause (fixed in Order::releaseSeatsAndRestoreStock 2026-08-18): releasing
 * a failed/cancelled/expired order freed seats purely by the seat_uids in its
 * ticket meta, so when a customer re-bought the SAME seats in a second,
 * successful order, releasing the first order stole the seats from the paid
 * order's valid tickets. This backfills the resulting orphaned seats.
 *
 * Usage:
 *   php artisan seating:fix-orphaned-available-seats --dry-run
 *   php artisan seating:fix-orphaned-available-seats
 *   php artisan seating:fix-orphaned-available-seats --event=739
 */
class FixOrphanedAvailableSeats extends Command
{
    protected $signature = 'seating:fix-orphaned-available-seats
        {--dry-run : Report only, do not write}
        {--event= : Limit to a single event_seating_id}';

    protected $description = 'Re-mark seats that are available but held by a valid/used ticket back to sold';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Match event_seats to the tickets that reference them via meta
        // (seat_uid + event_seating_id). meta->> returns text, so compare the
        // seating id as text. Only currently-'available' seats that a
        // valid/used ticket owns are mismatches.
        $query = DB::table('event_seats as es')
            ->join('tickets as t', function ($join) {
                $join->on(DB::raw("(t.meta->>'seat_uid')"), '=', 'es.seat_uid')
                    ->on(DB::raw("(t.meta->>'event_seating_id')"), '=', DB::raw('es.event_seating_id::text'));
            })
            ->whereIn('t.status', ['valid', 'used'])
            ->where('es.status', 'available');

        if ($eventSeatingId = $this->option('event')) {
            $query->where('es.event_seating_id', (int) $eventSeatingId);
        }

        $rows = $query
            ->select('es.id as seat_id', 'es.event_seating_id', 'es.seat_uid', 't.id as ticket_id', 't.order_id')
            ->distinct()
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No orphaned seats found.');
            return self::SUCCESS;
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Found {$rows->count()} seat(s) available-but-held-by-valid-ticket:");
        foreach ($rows as $r) {
            $this->line("  event_seating_id={$r->event_seating_id} seat={$r->seat_uid} ticket={$r->ticket_id} order={$r->order_id}");
        }

        if ($dry) {
            $this->comment('Dry run — nothing written. Re-run without --dry-run to fix.');
            return self::SUCCESS;
        }

        $seatIds = $rows->pluck('seat_id')->unique()->all();
        $fixed = EventSeat::whereIn('id', $seatIds)
            ->where('status', 'available')
            ->update([
                'status' => 'sold',
                'version' => DB::raw('version + 1'),
                'last_change_at' => now(),
            ]);

        $this->info("Fixed {$fixed} seat(s): available → sold.");
        return self::SUCCESS;
    }
}
