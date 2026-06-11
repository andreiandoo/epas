<?php

namespace App\Services\Alerts;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Alert Service
 *
 * Handles critical system alerts and notifications with:
 * - Health check alerts
 * - Email notifications
 * - Slack webhook integration
 * - Alert throttling to prevent spam
 * - Multi-channel delivery
 */
class AlertService
{
    /**
     * Send health check alert
     *
     * @param array $health Health check results from HealthCheckService
     * @return array {sent: bool, channels: array, throttled: bool}
     */
    public function sendHealthAlert(array $health): array
    {
        $status = $health['status'] ?? 'unknown';
        $timestamp = $health['timestamp'] ?? now()->toIso8601String();

        // Check if we should throttle this alert
        if ($this->shouldThrottle($status)) {
            Log::info('Alert throttled to prevent spam', [
                'status' => $status,
                'timestamp' => $timestamp,
            ]);

            return [
                'sent' => false,
                'channels' => [],
                'throttled' => true,
            ];
        }

        $channels = [];
        $sent = false;

        // Send email alert
        if (config('microservices.alerts.email.enabled', true)) {
            try {
                $this->sendEmailAlert($health);
                $channels[] = 'email';
                $sent = true;
            } catch (\Exception $e) {
                Log::error('Failed to send email alert', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send Slack alert
        if (config('microservices.alerts.slack.enabled', false)) {
            try {
                $this->sendSlackAlert($health);
                $channels[] = 'slack';
                $sent = true;
            } catch (\Exception $e) {
                Log::error('Failed to send Slack alert', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update throttle cache
        if ($sent) {
            $this->updateThrottle($status);
        }

        return [
            'sent' => $sent,
            'channels' => $channels,
            'throttled' => false,
        ];
    }

    /**
     * Send microservice expiring alert
     *
     * @param string $tenantId Tenant ID
     * @param array $subscription Subscription data
     * @return bool
     */
    public function sendMicroserviceExpiringAlert(string $tenantId, array $subscription): bool
    {
        try {
            $recipients = $this->getAlertRecipients('microservice_expiring');

            if (empty($recipients)) {
                return false;
            }

            Mail::send('emails.alerts.microservice-expiring', [
                'tenantId' => $tenantId,
                'subscription' => $subscription,
            ], function ($message) use ($recipients, $subscription) {
                $message->to($recipients)
                    ->subject("Microservice Expiring Soon: {$subscription['microservice_id']}")
                    ->from(
                        config('mail.from.address'),
                        config('mail.from.name')
                    );
            });

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send microservice expiring alert', [
                'tenant_id' => $tenantId,
                'subscription' => $subscription,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send microservice suspended alert
     *
     * @param string $tenantId Tenant ID
     * @param array $subscription Subscription data
     * @return bool
     */
    public function sendMicroserviceSuspendedAlert(string $tenantId, array $subscription): bool
    {
        try {
            $recipients = $this->getAlertRecipients('microservice_suspended');

            if (empty($recipients)) {
                return false;
            }

            Mail::send('emails.alerts.microservice-suspended', [
                'tenantId' => $tenantId,
                'subscription' => $subscription,
            ], function ($message) use ($recipients, $subscription) {
                $message->to($recipients)
                    ->subject("Microservice Auto-Suspended: {$subscription['microservice_id']}")
                    ->from(
                        config('mail.from.address'),
                        config('mail.from.name')
                    );
            });

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send microservice suspended alert', [
                'tenant_id' => $tenantId,
                'subscription' => $subscription,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send webhook failure alert (after max retries exhausted)
     *
     * @param string $tenantId Tenant ID
     * @param array $webhookDelivery Webhook delivery data
     * @return bool
     */
    public function sendWebhookFailureAlert(string $tenantId, array $webhookDelivery): bool
    {
        try {
            $recipients = $this->getAlertRecipients('webhook_failure');

            if (empty($recipients)) {
                return false;
            }

            Mail::send('emails.alerts.webhook-failure', [
                'tenantId' => $tenantId,
                'webhookDelivery' => $webhookDelivery,
            ], function ($message) use ($recipients, $webhookDelivery) {
                $message->to($recipients)
                    ->subject("Webhook Delivery Failed: {$webhookDelivery['event_type']}")
                    ->from(
                        config('mail.from.address'),
                        config('mail.from.name')
                    );
            });

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send webhook failure alert', [
                'tenant_id' => $tenantId,
                'webhook_delivery' => $webhookDelivery,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email alert for health issues
     *
     * @param array $health Health check results
     * @return void
     */
    protected function sendEmailAlert(array $health): void
    {
        $recipients = $this->getAlertRecipients('health');

        if (empty($recipients)) {
            throw new \Exception('No alert recipients configured');
        }

        $status = $health['status'];
        $subject = $status === 'unhealthy'
            ? '🔴 CRITICAL: System Health Check Failed'
            : '⚠️ WARNING: System Health Degraded';

        Mail::send('emails.alerts.health', [
            'health' => $health,
            'status' => $status,
        ], function ($message) use ($recipients, $subject) {
            $message->to($recipients)
                ->subject($subject)
                ->from(
                    config('mail.from.address'),
                    config('mail.from.name')
                );
        });

        Log::info('Health alert email sent', [
            'recipients' => $recipients,
            'status' => $status,
        ]);
    }

    /**
     * Send Slack alert for health issues
     *
     * @param array $health Health check results
     * @return void
     */
    protected function sendSlackAlert(array $health): void
    {
        $webhookUrl = config('microservices.alerts.slack.webhook_url');

        if (!$webhookUrl) {
            throw new \Exception('Slack webhook URL not configured');
        }

        $status = $health['status'];
        $color = $status === 'unhealthy' ? 'danger' : 'warning';
        $emoji = $status === 'unhealthy' ? '🔴' : '⚠️';

        // Build check details
        $fields = [];
        foreach ($health['checks'] ?? [] as $checkName => $checkResult) {
            $checkStatus = $checkResult['status'] ?? 'unknown';
            $checkEmoji = $checkStatus === 'healthy' ? '✅' : ($checkStatus === 'degraded' ? '⚠️' : '❌');

            $fields[] = [
                'title' => ucfirst($checkName),
                'value' => "{$checkEmoji} {$checkStatus}",
                'short' => true,
            ];
        }

        $payload = [
            'attachments' => [
                [
                    'fallback' => "System health is {$status}",
                    'color' => $color,
                    'title' => "{$emoji} System Health: " . strtoupper($status),
                    'text' => "Health check detected system issues at " . ($health['timestamp'] ?? 'unknown time'),
                    'fields' => $fields,
                    'footer' => config('app.name'),
                    'ts' => now()->timestamp,
                ],
            ],
        ];

        $response = Http::post($webhookUrl, $payload);

        if (!$response->successful()) {
            throw new \Exception("Slack API returned {$response->status()}");
        }

        Log::info('Health alert Slack message sent', [
            'webhook_url' => $webhookUrl,
            'status' => $status,
        ]);
    }

    /**
     * Check if alert should be throttled
     *
     * Prevents alert spam by throttling repeated alerts:
     * - Unhealthy: 15 minutes
     * - Degraded: 30 minutes
     *
     * @param string $status Health status
     * @return bool
     */
    protected function shouldThrottle(string $status): bool
    {
        $cacheKey = "alert:throttle:{$status}";

        return Cache::has($cacheKey);
    }

    /**
     * Update throttle cache after sending alert
     *
     * @param string $status Health status
     * @return void
     */
    protected function updateThrottle(string $status): void
    {
        $cacheKey = "alert:throttle:{$status}";

        // Throttle duration based on severity
        $ttl = match($status) {
            'unhealthy' => 15 * 60, // 15 minutes
            'degraded' => 30 * 60,  // 30 minutes
            default => 60 * 60,     // 1 hour
        };

        Cache::put($cacheKey, true, $ttl);
    }

    /**
     * Get alert recipients for a specific alert type
     *
     * @param string $alertType Type of alert (health, microservice_expiring, etc.)
     * @return array
     */
    protected function getAlertRecipients(string $alertType): array
    {
        // Get from config
        $recipients = config("microservices.alerts.recipients.{$alertType}", []);

        // Fall back to default recipients if none specified
        if (empty($recipients)) {
            $recipients = config('microservices.alerts.recipients.default', []);
        }

        // Convert single email to array
        if (is_string($recipients)) {
            $recipients = [$recipients];
        }

        // Filter out empty values
        return array_filter($recipients);
    }
}
