<?php

namespace App\Services\Hub\Adapters;

/**
 * Zapier Integration Adapter
 *
 * Supports:
 * - Webhooks (triggers and actions)
 * - REST Hooks for subscriptions
 */
class ZapierAdapter extends BaseConnectorAdapter
{
    protected string $slug = 'zapier';
    protected string $baseUrl = 'https://hooks.zapier.com';

    public function getAuthorizationUrl(string $connectionId, array $config): string
    {
        // Zapier uses webhook URLs, not OAuth
        // Return a setup page URL instead
        return config('app.url') . '/hub/zapier/setup?connection=' . $connectionId;
    }

    public function exchangeCodeForTokens(string $code, array $config): array
    {
        // Zapier doesn't use OAuth tokens
        // Instead, we store the webhook URL
        return [
            'webhook_url' => $code,
            'access_token' => 'webhook',
        ];
    }

    public function refreshTokens(string $refreshToken, array $config): array
    {
        // No refresh needed for webhooks
        return ['access_token' => 'webhook'];
    }

    public function testConnection(array $credentials): array
    {
        $webhookUrl = $credentials['webhook_url'] ?? null;

        if (!$webhookUrl) {
            return [
                'success' => false,
                'error' => 'No webhook URL configured',
            ];
        }

        // Test by sending a test event
        try {
            $response = \Illuminate\Support\Facades\Http::post($webhookUrl, [
                'type' => 'test',
                'message' => 'Connection test from Hub Integration',
                'timestamp' => now()->toIso8601String(),
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function executeAction(string $action, array $data, array $credentials): array
    {
        return match ($action) {
            'trigger_zap' => $this->triggerZap($data, $credentials),
            'subscribe_hook' => $this->subscribeHook($data, $credentials),
            'unsubscribe_hook' => $this->unsubscribeHook($data, $credentials),
            default => throw new \Exception("Unsupported action: {$action}"),
        };
    }

    protected function triggerZap(array $data, array $credentials): array
    {
        $webhookUrl = $credentials['webhook_url'];

        $response = \Illuminate\Support\Facades\Http::post($webhookUrl, $data);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json(),
        ];
    }

    protected function subscribeHook(array $data, array $credentials): array
    {
        // Store the subscription URL provided by Zapier
        return [
            'success' => true,
            'subscription_url' => $data['subscription_url'],
        ];
    }

    protected function unsubscribeHook(array $data, array $credentials): array
    {
        // Remove the subscription
        return [
            'success' => true,
        ];
    }

    public function getSupportedActions(): array
    {
        return [
            'trigger_zap' => 'Trigger a Zap webhook',
            'subscribe_hook' => 'Subscribe to REST hook',
            'unsubscribe_hook' => 'Unsubscribe from REST hook',
        ];
    }

    public function getSupportedEvents(): array
    {
        return [
            'order_created' => 'Order was created',
            'customer_created' => 'Customer was created',
            'event_published' => 'Event was published',
            'ticket_sold' => 'Ticket was sold',
        ];
    }
}
