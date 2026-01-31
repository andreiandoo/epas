<?php

namespace App\Jobs;

use App\Services\Webhooks\EnhancedWebhookDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deliver Webhook Job
 *
 * Queued job for async webhook delivery with retry logic
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $webhookId,
        public string $event,
        public array $payload,
        public int $attempt = 1
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(EnhancedWebhookDeliveryService $deliveryService): void
    {
        $deliveryService->deliver(
            $this->webhookId,
            $this->event,
            $this->payload,
            $this->attempt
        );
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 1min, 5min, 30min
        return [60, 300, 1800];
    }
}
