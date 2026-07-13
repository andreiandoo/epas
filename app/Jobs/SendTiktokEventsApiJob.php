<?php

namespace App\Jobs;

use App\Models\TrackingIntegration;
use App\Services\Integrations\TikTokEventsApi\TikTokEventsApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Server-side TikTok Events API dispatcher.
 *
 * Fires from MarketplaceTrackingController::track() after the browser
 * event is persisted. Deduplicates against the browser ttq.track() call
 * via a shared event_id so TikTok's Events Manager collapses the pair
 * into a single event (unlike GA4 MP, TikTok supports this natively).
 *
 * The credential (access_token) is re-fetched from the TrackingIntegration
 * inside handle() so it never lives in the queue serialization.
 */
class SendTiktokEventsApiJob implements ShouldQueue
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
        public string $eventName,
        public string $eventId,
        public array $userData = [],
        public array $properties = [],
        public array $page = [],
        public ?string $testEventCode = null,
    ) {
    }

    public function handle(TikTokEventsApiClient $client): void
    {
        $integration = TrackingIntegration::find($this->trackingIntegrationId);
        if (!$integration || !$integration->hasServerSideCredentials()) {
            return;
        }

        $pixelCode = $integration->getProviderId();
        $accessToken = $integration->getServerSideCredential();

        try {
            $client->sendEvent(
                pixelCode: $pixelCode,
                accessToken: $accessToken,
                eventName: $client->mapEventName($this->eventName),
                eventId: $this->eventId,
                userData: $this->userData,
                properties: $this->properties,
                page: $this->page,
                testEventCode: $this->testEventCode,
            );
        } catch (\Throwable $e) {
            Log::warning('TikTok EAPI job send failed', [
                'integration_id' => $this->trackingIntegrationId,
                'event_name' => $this->eventName,
                'event_id' => $this->eventId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
