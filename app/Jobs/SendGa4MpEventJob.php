<?php

namespace App\Jobs;

use App\Models\TrackingIntegration;
use App\Services\Integrations\Ga4\Ga4MeasurementProtocolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Server-side GA4 Measurement Protocol dispatcher.
 *
 * Takes the TrackingIntegration row id (already resolved by
 * MarketplaceTrackingController via the organizer→marketplace fallback)
 * plus the payload, and posts to GA4 MP with the appropriate mapped
 * event name and params. The credential (api_secret) is re-fetched
 * from the row inside handle() so it never leaks into the serialized
 * queue payload.
 */
class SendGa4MpEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(
        public int $trackingIntegrationId,
        public string $clientId,
        public string $eventName,
        public array $params = [],
        public array $userProperties = [],
    ) {
    }

    public function handle(Ga4MeasurementProtocolService $ga4): void
    {
        $integration = TrackingIntegration::find($this->trackingIntegrationId);
        if (!$integration || !$integration->hasServerSideCredentials()) {
            return;
        }

        $measurementId = $integration->getProviderId();
        $apiSecret = $integration->getServerSideCredential();

        try {
            $ga4->sendEvent(
                measurementId: $measurementId,
                apiSecret: $apiSecret,
                clientId: $this->clientId,
                eventName: $ga4->mapEventName($this->eventName),
                params: $this->params,
                userProperties: $this->userProperties,
            );
        } catch (\Throwable $e) {
            Log::warning('GA4 MP job send failed', [
                'integration_id' => $this->trackingIntegrationId,
                'event_name' => $this->eventName,
                'client_id' => $this->clientId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
