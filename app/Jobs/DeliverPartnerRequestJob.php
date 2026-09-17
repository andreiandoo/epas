<?php

namespace App\Jobs;

use App\Models\MarketplacePartnerDelivery;
use App\Services\Partners\PartnerRequestSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a media-partner delivery, retrying after 1, 5 and 30 minutes when the
 * partner is unreachable or answers 5xx / 408 / 425 / 429.
 *
 * Retries use release() rather than exceptions, so a partner outage does not
 * flood the error dashboard; the outcome is on the delivery row.
 */
class DeliverPartnerRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const RETRY_DELAYS = [60, 300, 1800];

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $deliveryId)
    {
    }

    public function handle(PartnerRequestSender $sender): void
    {
        $delivery = MarketplacePartnerDelivery::with('partner')->find($this->deliveryId);

        if (!$delivery || !$delivery->partner || $delivery->status !== MarketplacePartnerDelivery::STATUS_PENDING) {
            return;
        }

        if ($sender->send($delivery)) {
            return;
        }

        $attempt = $this->attempts();
        if (PartnerRequestSender::isRetryable($delivery->http_status) && isset(self::RETRY_DELAYS[$attempt - 1])) {
            $this->release(self::RETRY_DELAYS[$attempt - 1]);

            return;
        }

        $delivery->forceFill(['status' => MarketplacePartnerDelivery::STATUS_FAILED])->save();
    }
}
