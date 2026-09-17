<?php

namespace App\Jobs;

use App\Models\MarketplacePartnerDelivery;
use App\Services\Partners\PartnerRequestSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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

        if (!$delivery || $delivery->status !== MarketplacePartnerDelivery::STATUS_PENDING) {
            return;
        }

        if (!$this->stillWanted($delivery)) {
            $delivery->forceFill([
                'status' => MarketplacePartnerDelivery::STATUS_FAILED,
                'error' => 'Not sent: the partner was deactivated or its address changed.',
            ])->save();

            return;
        }

        if ($sender->send($delivery)) {
            return;
        }

        if ($delivery->status !== MarketplacePartnerDelivery::STATUS_PENDING) {
            return;
        }

        $attempt = $this->attempts();
        if (PartnerRequestSender::isRetryable($delivery->http_status) && isset(self::RETRY_DELAYS[$attempt - 1])) {
            $this->release(self::RETRY_DELAYS[$attempt - 1]);

            return;
        }

        $delivery->forceFill(['status' => MarketplacePartnerDelivery::STATUS_FAILED])->save();
    }

    /**
     * An exception (e.g. an unreadable secret after an APP_KEY change) must not
     * leave the delivery pending, where it could not be resent.
     */
    public function failed(?\Throwable $exception): void
    {
        MarketplacePartnerDelivery::whereKey($this->deliveryId)
            ->where('status', MarketplacePartnerDelivery::STATUS_PENDING)
            ->update([
                'status' => MarketplacePartnerDelivery::STATUS_FAILED,
                'error' => Str::limit('Job failed: ' . ($exception?->getMessage() ?? 'unknown error'), 500),
                'updated_at' => now(),
            ]);
    }

    /**
     * Retries can run half an hour later; by then the partner may be off or moved.
     */
    private function stillWanted(MarketplacePartnerDelivery $delivery): bool
    {
        $partner = $delivery->partner;

        if (!$partner || !$partner->isActive()) {
            return false;
        }

        if (str_starts_with($delivery->type, 'event.')) {
            return $partner->wantsEventWebhooks() && $delivery->url === $partner->webhook_url;
        }

        return true;
    }
}
