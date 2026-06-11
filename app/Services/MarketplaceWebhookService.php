<?php

namespace App\Services;

use App\Models\MarketplaceClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketplaceWebhookService
{
    /**
     * Send a webhook notification to a marketplace client
     */
    public function send(MarketplaceClient $client, string $event, array $payload): bool
    {
        $webhookUrl = $client->getWebhookUrl();

        if (!$webhookUrl) {
            return false;
        }

        $webhookPayload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'client_id' => $client->id,
            'data' => $payload,
        ];

        $signature = $this->generateSignature($webhookPayload, $client->getWebhookSecret());

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Timestamp' => $webhookPayload['timestamp'],
                ])
                ->post($webhookUrl, $webhookPayload);

            $success = $response->successful();

            Log::channel('marketplace')->info('Webhook sent', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'event' => $event,
                'url' => $webhookUrl,
                'status' => $response->status(),
                'success' => $success,
            ]);

            return $success;
        } catch (\Exception $e) {
            Log::channel('marketplace')->error('Webhook failed', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'event' => $event,
                'url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate HMAC signature for webhook payload
     */
    protected function generateSignature(array $payload, ?string $secret): string
    {
        if (!$secret) {
            return '';
        }

        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Send order created webhook
     */
    public function orderCreated(MarketplaceClient $client, array $orderData): bool
    {
        return $this->send($client, 'order.created', $orderData);
    }

    /**
     * Send order confirmed webhook
     */
    public function orderConfirmed(MarketplaceClient $client, array $orderData): bool
    {
        return $this->send($client, 'order.confirmed', $orderData);
    }

    /**
     * Send order cancelled webhook
     */
    public function orderCancelled(MarketplaceClient $client, array $orderData): bool
    {
        return $this->send($client, 'order.cancelled', $orderData);
    }

    /**
     * Send order completed webhook
     */
    public function orderCompleted(MarketplaceClient $client, array $orderData): bool
    {
        return $this->send($client, 'order.completed', $orderData);
    }

    /**
     * Send order refunded webhook
     */
    public function orderRefunded(MarketplaceClient $client, array $orderData): bool
    {
        return $this->send($client, 'order.refunded', $orderData);
    }
}
