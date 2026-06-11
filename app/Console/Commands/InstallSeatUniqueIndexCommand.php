<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Final step of the seat double-booking fix — installs the partial unique
 * index on tickets.(event_id, seat_uid) for status IN ('valid','used').
 *
 * The original migration (2026_05_22_110000_add_seat_uid_unique_to_tickets)
 * skips this step when existing duplicates are detected. After the operator
 * resolves each conflict (cancel one of the duplicate tickets / refund),
 * this command installs the constraint.
 *
 * Idempotent. Safe to re-run.
 */
class InstallSeatUniqueIndexCommand extends Command
{
    protected $signature = 'tickets:install-seat-unique-index {--dry-run : List duplicates without installing}';

    protected $description = 'Install partial unique index on tickets(event_id, seat_uid) after resolving duplicates.';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('Skipping — only pgsql supports partial unique indexes. Application-side checks are the guard on other drivers.');
            return self::SUCCESS;
        }

        // Check if the index already exists (from a successful migration or
        // a previous run of this command). pg_indexes is the canonical source.
        $existing = DB::select(<<<SQL
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = 'tickets'
              AND indexname = 'tickets_seat_uid_event_unique'
        SQL);

        if (!empty($existing)) {
            $this->info('Index tickets_seat_uid_event_unique already installed. Nothing to do.');
            return self::SUCCESS;
        }

        // Scan for duplicates that would block index creation.
        $duplicates = DB::select(<<<SQL
            SELECT event_id, seat_uid, COUNT(*) AS dup_count,
                   array_agg(id ORDER BY id) AS ticket_ids
            FROM tickets
            WHERE seat_uid IS NOT NULL
              AND status IN ('valid', 'used')
            GROUP BY event_id, seat_uid
            HAVING COUNT(*) > 1
            ORDER BY event_id, seat_uid
        SQL);

        if (!empty($duplicates)) {
            $this->error('Cannot install index — ' . count($duplicates) . ' duplicate group(s) still present:');
            $this->newLine();
            foreach ($duplicates as $d) {
                $ids = is_string($d->ticket_ids) ? $d->ticket_ids : json_encode($d->ticket_ids);
                $this->line(sprintf(
                    '  event_id=%d  seat_uid=%s  count=%d  ticket_ids=%s',
                    $d->event_id,
                    $d->seat_uid,
                    $d->dup_count,
                    $ids
                ));
            }
            $this->newLine();
            $this->warn('Resolve each conflict (cancel/refund one of the tickets in each group), then re-run.');
            $this->line('Example to cancel ticket #X:');
            $this->line('  php artisan tinker --execute=\'\\App\\Models\\Ticket::where("id", X)->update(["status"=>"cancelled","cancellation_reason"=>"duplicate-seat-resolved-manually"]);\'');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('No duplicates found. Index would be created. (dry-run)');
            return self::SUCCESS;
        }

        $this->info('No duplicates found. Creating partial unique index...');
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS tickets_seat_uid_event_unique
            ON tickets (event_id, seat_uid)
            WHERE seat_uid IS NOT NULL
              AND status IN ('valid', 'used')
        SQL);
        $this->info('✓ Index installed. Future double-bookings will be rejected at the database layer.');

        return self::SUCCESS;
    }
}
