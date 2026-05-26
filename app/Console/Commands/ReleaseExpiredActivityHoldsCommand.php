<?php

namespace App\Console\Commands;

use App\Models\ActivityBooking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Release activity slot capacity held by abandoned checkouts.
 *
 * Background: when a customer reaches the activity checkout page, an
 * ActivityBooking row is created with status=pending_payment and a
 * short-lived held_until (default 5 minutes). That row consumes slot
 * capacity in SlotResolver until either:
 *   - the user finishes paying → status moves to paid (see Order observer)
 *   - the user abandons the flow → held_until expires → THIS command
 *     marks the row as cancelled so the next shopper can reclaim the slot
 *
 * Idempotent: running it twice in a row is a no-op the second time.
 * Designed for everyMinute() cadence — see routes/console.php.
 */
class ReleaseExpiredActivityHoldsCommand extends Command
{
    protected $signature = 'activities:release-expired-holds
                            {--dry-run : Preview what would be released without making changes}';

    protected $description = 'Cancel pending_payment activity bookings whose hold has expired (frees slot capacity)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $query = ActivityBooking::query()
            ->heldExpired()
            // Defensive: only touch bookings that are NOT already wired to a
            // paid/confirmed order. If something downstream already moved the
            // status without clearing held_until, we leave it alone.
            ->where('status', ActivityBooking::STATUS_PENDING_PAYMENT);

        $expired = $query->get();

        if ($expired->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("Found {$expired->count()} expired activity hold(s).");

        if ($dryRun) {
            $this->warn('DRY RUN — listing only.');
            foreach ($expired as $b) {
                $this->line(sprintf(
                    '  booking #%d (activity %d, %s %s, %d participants, held_until %s)',
                    $b->id,
                    $b->activity_id,
                    $b->booking_date?->toDateString() ?? '?',
                    $b->slot_start_time ? (is_string($b->slot_start_time) ? $b->slot_start_time : $b->slot_start_time->format('H:i:s')) : '?',
                    $b->participants_count,
                    $b->held_until?->toIso8601String() ?? '?',
                ));
            }
            return self::SUCCESS;
        }

        $released = 0;
        foreach ($expired as $booking) {
            try {
                DB::transaction(function () use ($booking, &$released) {
                    // Re-read with lock to avoid racing the checkout transaction that
                    // might still be finalising the same booking right this second.
                    $fresh = ActivityBooking::lockForUpdate()->find($booking->id);
                    if (! $fresh) {
                        return;
                    }
                    if ($fresh->status !== ActivityBooking::STATUS_PENDING_PAYMENT) {
                        return; // someone else moved it (paid in the last millisecond)
                    }
                    if ($fresh->held_until && $fresh->held_until->isFuture()) {
                        return; // hold was extended after we read the list
                    }

                    $fresh->update([
                        'status' => ActivityBooking::STATUS_CANCELLED,
                        'notes' => trim(($fresh->notes ?? '') . "\nAuto-cancelled: hold expired."),
                    ]);

                    $released++;
                });
            } catch (\Throwable $e) {
                Log::warning('[activities:release-expired-holds] failed to release booking', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
                $this->warn("  skipped booking #{$booking->id}: {$e->getMessage()}");
            }
        }

        if ($released > 0) {
            $this->info("Released {$released} expired activity hold(s).");
            Log::info('[activities:release-expired-holds] released holds', [
                'released_count' => $released,
            ]);
        }

        return self::SUCCESS;
    }
}
