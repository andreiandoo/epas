<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * One-shot backfill for the Tixello POS test ticket type.
 *
 * The observer covers every event created AFTER deploy. Existing events
 * that were scheduled before the observer shipped need this pass so
 * organizers can start smoke-testing on the mobile POS today, without
 * waiting for the next event they create.
 *
 * Scope tightened per operator request: only future events (event_date
 * >= today at Bucharest midnight, or a live range that hasn't ended)
 * get one. Historical events don't need a test ticket at all — nobody's
 * running POS on a past event — and skipping them keeps the backfill
 * cheap on Ambilet's 5k+ historical rows.
 *
 * Leisure events skip via ensureTestTicketType() itself.
 */
class CreateTestPosTicketsCommand extends Command
{
    protected $signature = 'events:create-test-tickets
        {--dry-run : Print what would be created without touching the DB}';

    protected $description = 'Backfill the Test POS ticket type for every future non-leisure event.';

    public function handle(): int
    {
        $today = \Carbon\Carbon::now('Europe/Bucharest')->startOfDay();

        $events = Event::query()
            ->where(function ($q) use ($today) {
                $q->where('event_date', '>=', $today->toDateString())
                    ->orWhere('range_end_date', '>=', $today->toDateString());
            })
            ->where(function ($q) {
                $q->where('display_template', '!=', 'leisure_venue')
                    ->orWhereNull('display_template');
            })
            // Opt-in per organizer — only backfill events whose marketplace
            // organizer explicitly enabled Test POS tickets. Matches the same
            // gate ensureTestTicketType() enforces, so the counts below are
            // accurate and the scan stays cheap.
            ->whereHas('marketplaceOrganizer', fn ($q) => $q->where('test_pos_enabled', true))
            ->orderBy('id')
            ->get();

        $this->info("Scanning {$events->count()} candidate events (future, non-leisure, Test POS enabled)...");

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($events as $event) {
            // Skip if already has a test ticket (idempotent — matches
            // ensureTestTicketType's own lookup).
            $exists = $event->ticketTypes()
                ->where(function ($q) {
                    $q->whereRaw("(meta->>'is_test')::boolean = true")
                        ->orWhere('name', 'Test POS');
                })
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  would create for event {$event->id}");
                $created++;
                continue;
            }

            try {
                $event->ensureTestTicketType();
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  failed for event {$event->id}: {$e->getMessage()}");
            }
        }

        $verb = $dryRun ? 'WOULD create' : 'created';
        $this->info("Done — $verb: $created | already had it: $skipped | failed: $failed");

        return self::SUCCESS;
    }
}
