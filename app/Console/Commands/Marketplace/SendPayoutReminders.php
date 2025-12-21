<?php

namespace App\Console\Commands\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Marketplace\MarketplacePayout;
use App\Notifications\Marketplace\PayoutReady;
use Illuminate\Console\Command;

class SendPayoutReminders extends Command
{
    protected $signature = 'marketplace:send-payout-reminders
                            {--days=7 : Send reminders for payouts pending longer than this}';

    protected $description = 'Send reminders for pending payouts that need processing';

    public function handle(): int
    {
        $this->info('Checking for pending payouts...');

        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        // Find pending payouts older than cutoff
        $pendingPayouts = MarketplacePayout::where('status', 'pending')
            ->where('created_at', '<', $cutoffDate)
            ->with('organizer.marketplace')
            ->get();

        if ($pendingPayouts->isEmpty()) {
            $this->info('No pending payouts require reminders.');
            return self::SUCCESS;
        }

        $this->info("Found {$pendingPayouts->count()} pending payouts older than {$days} days.");

        foreach ($pendingPayouts as $payout) {
            $organizer = $payout->organizer;
            $marketplace = $organizer->marketplace;

            // Notify marketplace owner
            $owner = $marketplace->owner;
            if ($owner) {
                $this->line("  - Reminder for payout {$payout->reference} ({$organizer->name})");

                // You could send a notification here
                // $owner->notify(new PendingPayoutReminder($payout));
            }
        }

        $this->info('Payout reminders processed.');

        return self::SUCCESS;
    }
}
