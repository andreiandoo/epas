<?php

namespace App\Console\Commands;

use App\Services\ExtendedArtist\BookingService;
use Illuminate\Console\Command;

/**
 * Marchează cererile de booking nehotărâte (new/viewed/negotiating) cu expires_at < now() ca expired.
 * Programat zilnic prin app/Console/Kernel.php sau routes/console.php.
 */
class ExpireStaleBookingsCommand extends Command
{
    protected $signature = 'bookings:expire-stale';
    protected $description = 'Mark stale booking requests (older than 14 days, undecided) as expired.';

    public function handle(BookingService $booking): int
    {
        $count = $booking->expireStaleRequests();
        $this->info("Expired $count stale booking request(s).");
        return self::SUCCESS;
    }
}
