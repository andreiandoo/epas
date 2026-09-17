<?php

namespace App\Services\Partners;

use App\Jobs\DeliverPartnerRequestJob;
use App\Models\MarketplaceClient;
use App\Models\MarketplacePartner;
use App\Models\MarketplacePartnerDelivery;
use Illuminate\Support\Facades\Log;

/**
 * Event notifications for media partners: a signed POST per changed event,
 * { "event": "event.updated", "id": 48213, "occurred_at": "…" }, after which the
 * partner reads GET /api/partner/v1/events/{id}.
 */
class PartnerWebhooks
{
    /**
     * Above this many changes in one pass (e.g. a bulk edit), no notifications
     * are sent: partners pick the changes up through their regular sync.
     */
    public const MAX_PER_PASS = 500;

    /**
     * @param array<int, string> $changes event id => event.created|event.updated|event.cancelled|event.deleted
     * @return int deliveries queued
     */
    public function eventsChanged(MarketplaceClient $client, array $changes): int
    {
        if (!$changes) {
            return 0;
        }

        $partners = MarketplacePartner::where('marketplace_client_id', $client->id)
            ->where('status', 'active')
            ->whereNotNull('webhook_url')
            ->get()
            ->filter(fn (MarketplacePartner $partner) => $partner->wantsEventWebhooks());

        if ($partners->isEmpty()) {
            return 0;
        }

        if (count($changes) > self::MAX_PER_PASS) {
            Log::info('Partner webhooks skipped: too many changes in one pass', [
                'marketplace_client_id' => $client->id,
                'changes' => count($changes),
            ]);

            return 0;
        }

        $occurredAt = now()->setTimezone(PartnerEventPresenter::timezoneFor($client))->toIso8601String();
        $queued = 0;

        foreach ($partners as $partner) {
            foreach ($changes as $eventId => $type) {
                $delivery = $partner->deliveries()->create([
                    'type' => $type,
                    'subject_id' => $eventId,
                    'method' => 'POST',
                    'url' => $partner->webhook_url,
                    'body' => ['event' => $type, 'id' => $eventId, 'occurred_at' => $occurredAt],
                    'status' => MarketplacePartnerDelivery::STATUS_PENDING,
                ]);

                DeliverPartnerRequestJob::dispatch($delivery->id);
                $queued++;
            }
        }

        return $queued;
    }

    /**
     * Send a test notification right away (not queued), for the admin panel.
     */
    public function ping(MarketplacePartner $partner): MarketplacePartnerDelivery
    {
        $delivery = $partner->deliveries()->create([
            'type' => 'ping',
            'method' => 'POST',
            'url' => $partner->webhook_url,
            'body' => [
                'event' => 'ping',
                'occurred_at' => now()->setTimezone(PartnerEventPresenter::timezoneFor($partner->marketplaceClient))->toIso8601String(),
            ],
            'status' => MarketplacePartnerDelivery::STATUS_PENDING,
        ]);

        if (!app(PartnerRequestSender::class)->send($delivery->setRelation('partner', $partner))) {
            $delivery->forceFill(['status' => MarketplacePartnerDelivery::STATUS_FAILED])->save();
        }

        return $delivery;
    }
}
