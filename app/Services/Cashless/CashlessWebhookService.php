<?php

namespace App\Services\Cashless;

use App\Models\Cashless\CashlessWebhookDelivery;
use App\Models\Cashless\CashlessWebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CashlessWebhookService
{
    private const RETRY_DELAYS = [30, 120, 600, 3600, 21600]; // 30s, 2m, 10m, 1h, 6h

    /**
     * Dispatch a webhook event to all subscribed endpoints.
     */
    public function dispatch(string $eventType, array $payload, int $tenantId, ?int $editionId = null): void
    {
        $endpoints = CashlessWebhookEndpoint::active()
            ->where('tenant_id', $tenantId)
            ->when($editionId, fn ($q) => $q->where(function ($q2) use ($editionId) {
                $q2->where('festival_edition_id', $editionId)->orWhereNull('festival_edition_id');
            }))
            ->forEvent($eventType)
            ->get();

        foreach ($endpoints as $endpoint) {
            $this->deliver($endpoint, $eventType, $payload);
        }
    }

    /**
     * Deliver a webhook to a specific endpoint.
     */
    public function deliver(CashlessWebhookEndpoint $endpoint, string $eventType, array $payload): CashlessWebhookDelivery
    {
        $deliveryId = \Illuminate\Support\Str::uuid()->toString();
        $signature = hash_hmac('sha256', json_encode($payload), $endpoint->secret);

        $delivery = CashlessWebhookDelivery::create([
            'cashless_webhook_endpoint_id' => $endpoint->id,
            'event_type'                   => $eventType,
            'payload'                      => $payload,
            'attempted_at'                 => now(),
            'attempt_number'               => 1,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Cashless-Signature'   => $signature,
                    'X-Cashless-Event'       => $eventType,
                    'X-Cashless-Delivery-Id' => $deliveryId,
                    'Content-Type'           => 'application/json',
                ])
                ->post($endpoint->url, $payload);

            $delivery->update([
                'response_status' => $response->status(),
                'response_body'   => substr($response->body(), 0, 2000),
                'succeeded'       => $response->successful(),
            ]);

            if ($response->successful()) {
                $endpoint->update([
                    'last_success_at'      => now(),
                    'consecutive_failures' => 0,
                ]);
            } else {
                $this->handleFailure($endpoint, $delivery);
            }
        } catch (\Throwable $e) {
            $delivery->update([
                'response_body' => substr($e->getMessage(), 0, 2000),
                'succeeded'     => false,
            ]);
            $this->handleFailure($endpoint, $delivery);

            Log::warning("Cashless webhook delivery failed", [
                'endpoint_id' => $endpoint->id,
                'event'       => $eventType,
                'error'       => $e->getMessage(),
            ]);
        }

        return $delivery;
    }

    /**
     * Retry failed deliveries that are due.
     */
    public function processRetries(): array
    {
        $pending = CashlessWebhookDelivery::pendingRetry()->limit(50)->get();
        $retried = 0;
        $succeeded = 0;

        foreach ($pending as $delivery) {
            $endpoint = $delivery->endpoint;
            if (! $endpoint || ! $endpoint->is_active) {
                continue;
            }

            $result = $this->deliver($endpoint, $delivery->event_type, $delivery->payload);
            $retried++;
            if ($result->succeeded) {
                $succeeded++;
            }
        }

        return ['retried' => $retried, 'succeeded' => $succeeded];
    }

    private function handleFailure(CashlessWebhookEndpoint $endpoint, CashlessWebhookDelivery $delivery): void
    {
        $endpoint->increment('consecutive_failures');
        $endpoint->update(['last_failure_at' => now()]);

        // Schedule retry
        $attempt = $delivery->attempt_number;
        if ($attempt <= count(self::RETRY_DELAYS)) {
            $delay = self::RETRY_DELAYS[$attempt - 1];
            $delivery->update([
                'next_retry_at'  => now()->addSeconds($delay),
                'attempt_number' => $attempt + 1,
            ]);
        }

        // Auto-disable after 10 consecutive failures
        if ($endpoint->shouldDisable()) {
            $endpoint->update(['is_active' => false]);
            Log::warning("Cashless webhook endpoint auto-disabled after 10 failures", [
                'endpoint_id' => $endpoint->id,
                'url'         => $endpoint->url,
            ]);
        }
    }
}
