<?php

namespace App\Services\Push;

use App\Models\MarketplaceCustomer;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder transport: records what *would* have been pushed.
 *
 * Deliberately not a silent no-op — the log line is how the trigger logic gets
 * verified before the real FCM/APNs layer exists. See PushSender for the
 * TODO(owner) that removes this.
 */
class LogPushSender implements PushSender
{
    public function send(MarketplaceCustomer $customer, string $title, string $body, array $data = []): bool
    {
        Log::info('Push (no transport configured — logged only)', [
            'customer_id' => $customer->id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return true;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
