<?php

namespace App\Jobs;

use App\Services\Integrations\FacebookCapi\FacebookCapiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generic CAPI event dispatcher used by browser-bridge events
 * (AddToCart, InitiateCheckout, ViewContent, PageView, Lead, CompleteRegistration).
 *
 * Purchase events use the dedicated SendFacebookCapiPurchaseJob because they
 * derive their payload from the persisted Order, not from a transient
 * frontend payload.
 */
class SendFacebookCapiEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(
        public int $marketplaceOrganizerId,
        public string $eventName,
        public array $userData,
        public array $customData,
        public string $eventId,
        public ?string $eventSourceUrl = null,
        public ?string $correlationType = null,
        public ?string $correlationId = null,
    ) {
    }

    public function handle(FacebookCapiService $capi): void
    {
        $connection = $capi->getConnectionForOrganizer($this->marketplaceOrganizerId);
        if (!$connection) {
            return;
        }

        $allowed = $connection->enabled_events ?? [];
        if (!empty($allowed) && !in_array($this->eventName, $allowed, true)) {
            return;
        }

        try {
            $capi->sendEvent($connection, $this->eventName, $this->userData, $this->customData, [
                'event_id' => $this->eventId,
                'event_source_url' => $this->eventSourceUrl,
                'action_source' => 'website',
                'correlation_type' => $this->correlationType,
                'correlation_id' => $this->correlationId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FB CAPI event send failed', [
                'organizer_id' => $this->marketplaceOrganizerId,
                'event_name' => $this->eventName,
                'event_id' => $this->eventId,
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
