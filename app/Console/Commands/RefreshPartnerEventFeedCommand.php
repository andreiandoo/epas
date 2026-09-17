<?php

namespace App\Console\Commands;

use App\Models\MarketplaceClient;
use App\Models\MarketplacePartner;
use App\Models\MarketplacePartnerDelivery;
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
        if (!Schema::hasTable('marketplace_partners')
            || !Schema::hasTable('partner_event_feed')
            || !Schema::hasTable('marketplace_partner_deliveries')) {
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
                '%s: %d checked, %d new, %d changed, %d cancelled, %d removed, %d notifications queued',
                $client->name,
                $stats['checked'],
                $stats['created'],
                $stats['updated'],
                $stats['cancelled'],
                $stats['removed'],
                $stats['notified']
            ));

            if ($stats['created'] + $stats['updated'] + $stats['cancelled'] + $stats['removed'] > 0) {
                Log::info('partners:refresh-event-feed', ['marketplace_client_id' => $client->id] + $stats);
            }
        }

        // The delivery log is for troubleshooting; a month is enough.
        MarketplacePartnerDelivery::where('created_at', '<', now()->subDays(30))->delete();

        return self::SUCCESS;
    }
}
