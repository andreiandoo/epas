<?php

namespace App\Console\Commands;

use App\Services\Seating\SeatHoldService;
use Illuminate\Console\Command;

class ReleaseExpiredHolds extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seating:release-expired-holds
                            {--dry-run : Preview what would be released without actually releasing}';

    /**
     * The console command description.
     */
    protected $description = 'Release expired seat holds (fallback when Redis not used)';

    /**
     * Execute the console command.
     */
    public function handle(SeatHoldService $holdService): int
    {
        if (config('seating.use_redis_holds')) {
            $this->info('Redis holds are enabled - seats are automatically released by Redis TTL.');
            $this->info('This command only runs in DB fallback mode.');
            return 0;
        }

        $this->info('Starting expired holds cleanup...');

        $startTime = microtime(true);

        try {
            if ($this->option('dry-run')) {
                $this->warn('DRY RUN MODE - No changes will be made');

                $expiredCount = \App\Models\Seating\SeatHold::where('expires_at', '<', now())->count();

                $this->info("Found {$expiredCount} expired holds that would be released.");

                return 0;
            }

            $released = $holdService->releaseExpiredHolds();

            $elapsed = round(microtime(true) - $startTime, 2);

            if ($released > 0) {
                $this->info("Released {$released} expired holds in {$elapsed}s");
            } else {
                $this->comment("No expired holds found ({$elapsed}s)");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error releasing expired holds: {$e->getMessage()}");

            return 1;
        }
    }
}
