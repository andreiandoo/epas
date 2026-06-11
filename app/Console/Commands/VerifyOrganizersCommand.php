<?php

namespace App\Console\Commands;

use App\Models\MarketplaceOrganizer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class VerifyOrganizersCommand extends Command
{
    protected $signature = 'marketplace:verify-organizers
                            {--marketplace_id=1 : The marketplace client ID}
                            {--date=2026-01-03 12:00:00 : The verified_at date}';

    protected $description = 'Mark all organizers as verified for a given marketplace';

    public function handle(): int
    {
        $marketplaceId = (int) $this->option('marketplace_id');
        $date = Carbon::parse($this->option('date'));

        $count = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->whereNull('verified_at')
            ->update(['verified_at' => $date]);

        $this->info("Updated {$count} organizers as verified (verified_at = {$date}) for marketplace #{$marketplaceId}.");

        return self::SUCCESS;
    }
}
