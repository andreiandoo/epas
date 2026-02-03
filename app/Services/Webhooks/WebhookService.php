<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Webhook Management Service
 *
 * Handles webhook registration, triggering, and delivery management
 * for tenant integrations with external systems.
 */
class WebhookService
{
    /**
     * Create a new webhook endpoint
     *
     * @param string $tenantId Tenant ID
     * @param array $data Webhook configuration
     * @return array {success: bool, webhook_id: int|null, message: string}
     */
    public function createWebhook(string $tenantId, array $data): array
    {
        try {
            // Validate URL
            if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
                return [
                    'success' => false,
                    'webhook_id' => null,
                    'message' => 'Invalid webhook URL',
                ];
            }

            // SECURITY FIX: Block internal/private IPs to prevent SSRF
            $parsedUrl = parse_url($data['url']);
            $host = $parsedUrl['host'] ?? '';
            $blockedPatterns = ['localhost', '127.0.0.1', '0.0.0.0', '::1', '10.', '172.16.', '172.17.', '172.18.', '172.19.', '172.20.', '172.21.', '172.22.', '172.23.', '172.24.', '172.25.', '172.26.', '172.27.', '172.28.', '172.29.', '172.30.', '172.31.', '192.168.', '.local', '.internal'];
            foreach ($blockedPatterns as $pattern) {
                if (str_starts_with($host, $pattern) || str_ends_with($host, $pattern) || $host === $pattern) {
                    return [
                        'success' => false,
                        'webhook_id' => null,
                        'message' => 'Webhook URLs pointing to internal/private addresses are not allowed',
                    ];
                }
            }
            // Also validate scheme is https for production
            $scheme = $parsedUrl['scheme'] ?? '';
            if (!in_array($scheme, ['http', 'https'])) {
                return [
                    'success' => false,
                    'webhook_id' => null,
                    'message' => 'Webhook URL must use http or https',
                ];
            }

            // Validate events
            if (empty($data['events']) || !is_array($data['events'])) {
                return [
                    'success' => false,
                    'webhook_id' => null,
                    'message' => 'At least one event is required',
                ];
            }

            // Generate webhook secret
            $secret = $data['secret'] ?? $this->generateSecret();

            $webhookId = DB::table('tenant_webhooks')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => $data['name'] ?? 'Webhook',
                'url' => $data['url'],
                'events' => json_encode($data['events']),
                'secret' => $secret,
                'status' => $data['status'] ?? 'active',
                'headers' => !empty($data['headers']) ? json_encode($data['headers']) : null,
                'timeout' => $data['timeout'] ?? 30,
                'retry_limit' => $data['retry_limit'] ?? 3,
                'verify_ssl' => $data['verify_ssl'] ?? true,
                'metadata' => !empty($data['metadata']) ? json_encode($data['metadata']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => true,
                'webhook_id' => $webhookId,
                'message' => 'Webhook created successfully',
                'secret' => $secret,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create webhook', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'webhook_id' => null,
                'message' => 'Failed to create webhook: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update webhook configuration
     *
     * @param int $webhookId Webhook ID
     * @param string $tenantId Tenant ID (for authorization)
     * @param array $data Updated webhook data
     * @return array {success: bool, message: string}
     */
    public function updateWebhook(int $webhookId, string $tenantId, array $data): array
    {
        try {
            $webhook = DB::table('tenant_webhooks')
                ->where('id', $webhookId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$webhook) {
                return [
                    'success' => false,
                    'message' => 'Webhook not found',
                ];
            }

            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['url'])) {
                if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid webhook URL',
                    ];
                }
                $updateData['url'] = $data['url'];
            }

            if (isset($data['events'])) {
                $updateData['events'] = json_encode($data['events']);
            }

            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            if (isset($data['headers'])) {
                $updateData['headers'] = json_encode($data['headers']);
            }

            if (isset($data['timeout'])) {
                $updateData['timeout'] = $data['timeout'];
            }

            if (isset($data['retry_limit'])) {
                $updateData['retry_limit'] = $data['retry_limit'];
            }

            if (isset($data['verify_ssl'])) {
                $updateData['verify_ssl'] = $data['verify_ssl'];
            }

            $updateData['updated_at'] = now();

            DB::table('tenant_webhooks')
                ->where('id', $webhookId)
                ->update($updateData);

            return [
                'success' => true,
                'message' => 'Webhook updated successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update webhook', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update webhook: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete webhook
     *
     * @param int $webhookId Webhook ID
     * @param string $tenantId Tenant ID (for authorization)
     * @return array {success: bool, message: string}
     */
    public function deleteWebhook(int $webhookId, string $tenantId): array
    {
        try {
            $deleted = DB::table('tenant_webhooks')
                ->where('id', $webhookId)
                ->where('tenant_id', $tenantId)
                ->delete();

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Webhook not found',
                ];
            }

            return [
                'success' => true,
                'message' => 'Webhook deleted successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to delete webhook', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete webhook: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Trigger webhooks for an event
     *
     * @param string $tenantId Tenant ID
     * @param string $eventType Event type (e.g., 'order.created')
     * @param array $payload Event data
     * @return array {triggered: int, failed: int}
     */
    public function triggerEvent(string $tenantId, string $eventType, array $payload): array
    {
        try {
            // Find all active webhooks listening for this event
            $webhooks = DB::table('tenant_webhooks')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->get();

            $triggered = 0;
            $failed = 0;

            foreach ($webhooks as $webhook) {
                $events = json_decode($webhook->events, true);

                // Check if this webhook is listening for this event
                if (!in_array($eventType, $events) && !in_array('*', $events)) {
                    continue;
                }

                // Create delivery record
                $deliveryId = DB::table('tenant_webhook_deliveries')->insertGetId([
                    'webhook_id' => $webhook->id,
                    'tenant_id' => $tenantId,
                    'event_type' => $eventType,
                    'payload' => json_encode($payload),
                    'attempt' => 1,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Attempt to deliver immediately
                $result = $this->deliverWebhook($deliveryId);

                if ($result['success']) {
                    $triggered++;
                } else {
                    $failed++;
                }
            }

            return [
                'triggered' => $triggered,
                'failed' => $failed,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to trigger webhooks', [
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return [
                'triggered' => 0,
                'failed' => 0,
            ];
        }
    }

    /**
     * Deliver a webhook (send HTTP request)
     *
     * @param int $deliveryId Delivery ID
     * @return array {success: bool, message: string}
     */
    public function deliverWebhook(int $deliveryId): array
    {
        try {
            $delivery = DB::table('tenant_webhook_deliveries')
                ->where('id', $deliveryId)
                ->first();

            if (!$delivery) {
                return [
                    'success' => false,
                    'message' => 'Delivery not found',
                ];
            }

            $webhook = DB::table('tenant_webhooks')
                ->where('id', $delivery->webhook_id)
                ->first();

            if (!$webhook) {
                return [
                    'success' => false,
                    'message' => 'Webhook not found',
                ];
            }

            // Build payload with signature
            $payload = json_decode($delivery->payload, true);
            $timestamp = now()->timestamp;
            $signature = $this->generateSignature($payload, $timestamp, $webhook->secret);

            // Build headers
            $headers = [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Event' => $delivery->event_type,
                'Content-Type' => 'application/json',
            ];

            // Add custom headers
            if ($webhook->headers) {
                $customHeaders = json_decode($webhook->headers, true);
                $headers = array_merge($headers, $customHeaders);
            }

            // Send HTTP request
            $request = Http::withHeaders($headers)
                ->timeout($webhook->timeout);

            if (!$webhook->verify_ssl) {
                $request = $request->withoutVerifying();
            }

            $response = $request->post($webhook->url, $payload);

            // Update delivery record
            if ($response->successful()) {
                DB::table('tenant_webhook_deliveries')
                    ->where('id', $deliveryId)
                    ->update([
                        'status' => 'sent',
                        'http_status' => $response->status(),
                        'response_body' => $response->body(),
                        'sent_at' => now(),
                        'updated_at' => now(),
                    ]);

                // Update webhook last success
                DB::table('tenant_webhooks')
                    ->where('id', $webhook->id)
                    ->update([
                        'last_triggered_at' => now(),
                        'last_success_at' => now(),
                    ]);

                return [
                    'success' => true,
                    'message' => 'Webhook delivered successfully',
                ];
            } else {
                // Failed delivery
                $this->handleFailedDelivery($deliveryId, $webhook, $response);

                return [
                    'success' => false,
                    'message' => 'Webhook delivery failed: HTTP ' . $response->status(),
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to deliver webhook', [
                'delivery_id' => $deliveryId,
                'error' => $e->getMessage(),
            ]);

            // Update delivery record
            DB::table('tenant_webhook_deliveries')
                ->where('id', $deliveryId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle failed webhook delivery (schedule retry)
     */
    protected function handleFailedDelivery(int $deliveryId, $webhook, $response): void
    {
        $delivery = DB::table('tenant_webhook_deliveries')
            ->where('id', $deliveryId)
            ->first();

        $nextAttempt = $delivery->attempt + 1;

        // Update delivery record
        DB::table('tenant_webhook_deliveries')
            ->where('id', $deliveryId)
            ->update([
                'status' => 'failed',
                'http_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000), // Limit to 1000 chars
                'error_message' => 'HTTP ' . $response->status(),
                'attempt' => $nextAttempt,
                'next_retry_at' => $nextAttempt <= $webhook->retry_limit
                    ? now()->addMinutes($nextAttempt * 5) // Exponential backoff: 5, 10, 15 minutes
                    : null,
                'updated_at' => now(),
            ]);

        // Update webhook last failure
        DB::table('tenant_webhooks')
            ->where('id', $webhook->id)
            ->update([
                'last_triggered_at' => now(),
                'last_failure_at' => now(),
            ]);
    }

    /**
     * Process pending retries
     *
     * @return array {processed: int, succeeded: int, failed: int}
     */
    public function processRetries(): array
    {
        $pendingRetries = DB::table('tenant_webhook_deliveries')
            ->where('status', 'failed')
            ->where('next_retry_at', '<=', now())
            ->whereNotNull('next_retry_at')
            ->limit(100)
            ->get();

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($pendingRetries as $delivery) {
            $result = $this->deliverWebhook($delivery->id);

            $processed++;

            if ($result['success']) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    /**
     * Generate webhook secret
     */
    protected function generateSecret(): string
    {
        return 'whsec_' . Str::random(32);
    }

    /**
     * Generate webhook signature (HMAC-SHA256)
     */
    protected function generateSignature(array $payload, int $timestamp, string $secret): string
    {
        $signedPayload = $timestamp . '.' . json_encode($payload);
        return hash_hmac('sha256', $signedPayload, $secret);
    }

    /**
     * Verify webhook signature
     *
     * @param array $payload Payload data
     * @param string $signature Provided signature
     * @param int $timestamp Timestamp
     * @param string $secret Webhook secret
     * @return bool
     */
    public function verifySignature(array $payload, string $signature, int $timestamp, string $secret): bool
    {
        // Check timestamp is within 5 minutes
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $expectedSignature = $this->generateSignature($payload, $timestamp, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
