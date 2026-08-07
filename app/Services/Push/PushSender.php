<?php

namespace App\Services\Push;

use App\Models\MarketplaceCustomer;

/**
 * Customer-facing push notifications.
 *
 * TODO(owner): there is no push layer in EPAS yet — no FCM/APNs credentials, no
 * device-token table, no delivery pipeline (docs/plans/friends-social.md §5 and
 * docs/plans/shorts.md §B11 both flag it). Shorts needs it for the drop
 * reminders (D2) and the behavioural nudges (D12).
 *
 * This contract exists so those features can be built and tested against
 * something real. LogPushSender is bound by default: every send is written to
 * the log with its full payload, so the trigger logic is verifiable end-to-end
 * today and only the transport is missing.
 *
 * When the push layer lands: implement FcmPushSender/ApnsPushSender and bind it
 * in a service provider. Nothing else has to change.
 */
interface PushSender
{
    /**
     * @param  array<string, mixed>  $data  Deep-link payload (e.g. ['short_id' => 12]).
     * @return bool True when the message was handed to the transport.
     */
    public function send(MarketplaceCustomer $customer, string $title, string $body, array $data = []): bool;

    /**
     * False while no real transport is configured — callers can then choose to
     * fall back to email instead of silently dropping the message.
     */
    public function isConfigured(): bool;
}
