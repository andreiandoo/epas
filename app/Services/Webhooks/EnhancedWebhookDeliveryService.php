<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * Enhanced Webhook Delivery Service
 *
 * Advanced webhook delivery with:
 * - Exponential backoff retry
 * - Circuit breaker pattern
 * - Batch delivery
 * - Delivery analytics
 * - Webhook health monitoring
 */
class EnhancedWebhookDeliveryService
{
    /**
     * Deliver webhook with advanced retry logic
     *
     * @param int $webhookId Webhook ID
     * @param string $event Event name
     * @param array $payload Event payload
     * @param int $attempt Current attempt number
     * @return array Delivery result
     */
    public function deliver(
        int $webhookId,
        string $event,
        array $payload,
        int $attempt = 1
    ): array {
        $webhook = $this->getWebhook($webhookId);

        if (!$webhook) {
            return [
                'success' => false,
                'error' => 'Webhook not found',
            ];
        }

        // Check circuit breaker
        if ($this->isCircuitOpen($webhookId)) {
            Log::warning('Webhook circuit breaker open', ['webhook_id' => $webhookId]);

            return [
                'success' => false,
                'error' => 'Circuit breaker open - too many recent failures',
                'circuit_broken' => true,
            ];
        }

        // Prepare webhook payload
        $webhookPayload = $this->preparePayload($event, $payload, $attempt);

        // Generate signature
        $signature = $this->generateSignature($webhookPayload, $webhook->secret);

        // Prepare headers
        $headers = $this->prepareHeaders($webhook, $signature, $attempt);

        try {
            $startTime = microtime(true);

            // Send webhook request
            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout ?? 30)
                ->retry(1, 100) // Internal retry with 100ms delay
                ->when(!($webhook->verify_ssl ?? true), function ($http) {
                    return $http->withoutVerifying();
                })
                ->post($webhook->url, $webhookPayload);

            $duration = round((microtime(true) - $startTime) * 1000, 2); // ms

            // Log delivery
            $this->logDelivery($webhookId, $event, true, $response->status(), $duration, $attempt);

            // Record success for circuit breaker
            $this->recordSuccess($webhookId);

            return [
                'success' => true,
                'status_code' => $response->status(),
                'response' => $response->body(),
                'duration_ms' => $duration,
                'attempt' => $attempt,
            ];

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Webhook delivery failed', [
                'webhook_id' => $webhookId,
                'event' => $event,
                'attempt' => $attempt,
                'error' => $e->getMessage(),
            ]);

            // Log failure
            $this->logDelivery($webhookId, $event, false, null, $duration, $attempt, $e->getMessage());

            // Record failure for circuit breaker
            $this->recordFailure($webhookId);

            // Schedule retry with exponential backoff
            if ($attempt < ($webhook->retry_limit ?? 3)) {
                $this->scheduleRetry($webhookId, $event, $payload, $attempt + 1);
            } else {
                // Final failure - notify admin
                $this->notifyWebhookFailure($webhook, $event, $e->getMessage());
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
                'attempt' => $attempt,
                'will_retry' => $attempt < ($webhook->retry_limit ?? 3),
            ];
        }
    }

    /**
     * Schedule webhook retry with exponential backoff
     *
     * @param int $webhookId
     * @param string $event
     * @param array $payload
     * @param int $attempt
     * @return void
     */
    protected function scheduleRetry(
        int $webhookId,
        string $event,
        array $payload,
        int $attempt
    ): void {
        // Exponential backoff: 1min, 5min, 30min
        $delays = [1, 5, 30];
        $delayMinutes = $delays[$attempt - 1] ?? 60;

        Queue::later(
            now()->addMinutes($delayMinutes),
            new \App\Jobs\DeliverWebhookJob($webhookId, $event, $payload, $attempt)
        );

        Log::info('Webhook retry scheduled', [
            'webhook_id' => $webhookId,
            'event' => $event,
            'attempt' => $attempt,
            'delay_minutes' => $delayMinutes,
        ]);
    }

    /**
     * Prepare webhook payload
     *
     * @param string $event
     * @param array $payload
     * @param int $attempt
     * @return array
     */
    protected function preparePayload(string $event, array $payload, int $attempt): array
    {
        return [
            'event' => $event,
            'data' => $payload,
            'metadata' => [
                'webhook_id' => request()->attributes->get('webhook_id'),
                'attempt' => $attempt,
                'timestamp' => now()->toIso8601String(),
                'request_id' => request()->id(),
            ],
        ];
    }

    /**
     * Prepare request headers
     *
     * @param object $webhook
     * @param string $signature
     * @param int $attempt
     * @return array
     */
    protected function prepareHeaders(object $webhook, string $signature, int $attempt): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Microservices-Webhook/1.0',
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Attempt' => $attempt,
            'X-Webhook-Timestamp' => now()->timestamp,
        ];

        // Add custom headers from webhook configuration
        if ($webhook->headers) {
            $customHeaders = json_decode($webhook->headers, true) ?? [];
            $headers = array_merge($headers, $customHeaders);
        }

        return $headers;
    }

    /**
     * Generate webhook signature
     *
     * @param array $payload
     * @param string $secret
     * @return string
     */
    protected function generateSignature(array $payload, string $secret): string
    {
        $jsonPayload = json_encode($payload);
        return hash_hmac('sha256', $jsonPayload, $secret);
    }

    /**
     * Log webhook delivery attempt
     *
     * @param int $webhookId
     * @param string $event
     * @param bool $success
     * @param int|null $statusCode
     * @param float $duration
     * @param int $attempt
     * @param string|null $error
     * @return void
     */
    protected function logDelivery(
        int $webhookId,
        string $event,
        bool $success,
        ?int $statusCode,
        float $duration,
        int $attempt,
        ?string $error = null
    ): void {
        DB::table('webhook_delivery_logs')->insert([
            'webhook_id' => $webhookId,
            'event' => $event,
            'success' => $success,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'attempt' => $attempt,
            'error_message' => $error,
            'created_at' => now(),
        ]);

        // Update webhook statistics
        DB::table('tenant_webhooks')
            ->where('id', $webhookId)
            ->increment($success ? 'successful_deliveries' : 'failed_deliveries');

        if ($success) {
            DB::table('tenant_webhooks')
                ->where('id', $webhookId)
                ->update(['last_successful_delivery_at' => now()]);
        }
    }

    /**
     * Get webhook configuration
     *
     * @param int $webhookId
     * @return object|null
     */
    protected function getWebhook(int $webhookId): ?object
    {
        return DB::table('tenant_webhooks')
            ->where('id', $webhookId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Circuit Breaker: Check if circuit is open
     *
     * @param int $webhookId
     * @return bool
     */
    protected function isCircuitOpen(int $webhookId): bool
    {
        $key = "webhook_circuit:{$webhookId}";

        // Check if circuit is currently open
        if (cache()->has($key)) {
            return true;
        }

        // Check recent failure rate
        $recentFailures = DB::table('webhook_delivery_logs')
            ->where('webhook_id', $webhookId)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->where('success', false)
            ->count();

        // Open circuit if > 10 failures in 5 minutes
        if ($recentFailures > 10) {
            cache()->put($key, true, now()->addMinutes(15)); // Open for 15 minutes
            return true;
        }

        return false;
    }

    /**
     * Record successful delivery
     *
     * @param int $webhookId
     * @return void
     */
    protected function recordSuccess(int $webhookId): void
    {
        $key = "webhook_failures:{$webhookId}";
        cache()->forget($key); // Reset failure counter
    }

    /**
     * Record failed delivery
     *
     * @param int $webhookId
     * @return void
     */
    protected function recordFailure(int $webhookId): void
    {
        $key = "webhook_failures:{$webhookId}";
        $failures = cache()->get($key, 0) + 1;
        cache()->put($key, $failures, now()->addHour());
    }

    /**
     * Notify admin of final webhook failure
     *
     * @param object $webhook
     * @param string $event
     * @param string $error
     * @return void
     */
    protected function notifyWebhookFailure(object $webhook, string $event, string $error): void
    {
        if (config('microservices.alerts.enabled')) {
            // Send alert email
            \Illuminate\Support\Facades\Notification::route('mail', config('microservices.alerts.recipients.webhook_failure'))
                ->notify(new \App\Notifications\WebhookFailureNotification($webhook, $event, $error));
        }
    }

    /**
     * Get webhook health metrics
     *
     * @param int $webhookId
     * @return array
     */
    public function getHealthMetrics(int $webhookId): array
    {
        $webhook = DB::table('tenant_webhooks')->where('id', $webhookId)->first();

        if (!$webhook) {
            return [];
        }

        // Get recent delivery stats (last 24 hours)
        $recentDeliveries = DB::table('webhook_delivery_logs')
            ->where('webhook_id', $webhookId)
            ->where('created_at', '>=', now()->subDay())
            ->get();

        $total = $recentDeliveries->count();
        $successful = $recentDeliveries->where('success', true)->count();
        $failed = $recentDeliveries->where('success', false)->count();

        $avgDuration = $recentDeliveries->where('success', true)->avg('duration_ms');

        return [
            'webhook_id' => $webhookId,
            'url' => $webhook->url,
            'status' => $webhook->status,
            'circuit_open' => $this->isCircuitOpen($webhookId),
            'last_24h' => [
                'total_deliveries' => $total,
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
                'avg_duration_ms' => round($avgDuration, 2),
            ],
            'lifetime' => [
                'successful_deliveries' => $webhook->successful_deliveries ?? 0,
                'failed_deliveries' => $webhook->failed_deliveries ?? 0,
            ],
            'last_successful_delivery' => $webhook->last_successful_delivery_at,
        ];
    }
}
