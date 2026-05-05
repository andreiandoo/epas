<?php

namespace App\Jobs;

use App\Models\MarketplaceOrganizerAudienceSubscription;
use App\Services\Integrations\FacebookCapi\MetaAudienceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMetaCustomAudienceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $backoff = 300;
    public int $timeout = 300;

    public function __construct(public int $subscriptionId)
    {
    }

    public function handle(MetaAudienceSyncService $svc): void
    {
        $subscription = MarketplaceOrganizerAudienceSubscription::with(['organizer', 'segment'])
            ->find($this->subscriptionId);

        if (!$subscription || !$subscription->is_active) {
            return;
        }

        $svc->syncSubscription($subscription);
    }
}
