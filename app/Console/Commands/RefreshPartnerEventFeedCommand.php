<?php

namespace App\Console\Commands;

use App\Models\MarketplaceClient;
use App\Models\MarketplacePartner;
use App\Services\Partners\PartnerEventFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RefreshPartnerEventFeedCommand extends Command
{
    protected $signature = 'partners:refresh-event-feed {--client= : Only this marketplace client id}';

    protected $description = 'Recompute the event feed media partners read and record what changed';

    public function handle(PartnerEventFeed $feed): int
    {
        // The schedule may ship before the migration runs.
        if (!Schema::hasTable('marketplace_partners') || !Schema::hasTable('partner_event_feed')) {
            return self::SUCCESS;
        }

        $clientIds = MarketplacePartner::where('status', 'active')
            ->distinct()
            ->pluck('marketplace_client_id')
            ->map(fn ($id) => (int) $id);

        if ($this->option('client')) {
            $clientIds = $clientIds->intersect([(int) $this->option('client')]);
        }

        foreach (MarketplaceClient::whereIn('id', $clientIds)->get() as $client) {
            if (!$client->hasMicroservice(MarketplacePartner::MICROSERVICE)) {
                continue;
            }

            $stats = $feed->refresh($client);

            $this->info(sprintf(
                '%s: %d checked, %d new, %d changed, %d removed',
                $client->name,
                $stats['checked'],
                $stats['created'],
                $stats['updated'],
                $stats['removed']
            ));

            if ($stats['created'] + $stats['updated'] + $stats['removed'] > 0) {
                Log::info('partners:refresh-event-feed', ['marketplace_client_id' => $client->id] + $stats);
            }
        }

        return self::SUCCESS;
    }
}
