<?php

namespace App\Console\Commands;

use App\Jobs\SyncMetaCustomAudienceJob;
use App\Models\MarketplaceOrganizerAudienceSubscription;
use Illuminate\Console\Command;

class SyncMetaAudiencesCommand extends Command
{
    protected $signature = 'audiences:sync-meta {--organizer= : Limit to a single marketplace_organizer_id} {--force : Sync even subscriptions synced in the last 12h}';
    protected $description = 'Dispatch SyncMetaCustomAudienceJob for all active audience subscriptions';

    public function handle(): int
    {
        $query = MarketplaceOrganizerAudienceSubscription::where('is_active', true);

        if ($organizerId = $this->option('organizer')) {
            $query->where('marketplace_organizer_id', (int) $organizerId);
        }

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('last_synced_at')
                  ->orWhere('last_synced_at', '<', now()->subHours(12));
            });
        }

        $subscriptions = $query->get();
        $this->info("Dispatching {$subscriptions->count()} audience sync jobs.");

        foreach ($subscriptions as $sub) {
            SyncMetaCustomAudienceJob::dispatch($sub->id);
        }

        return self::SUCCESS;
    }
}
