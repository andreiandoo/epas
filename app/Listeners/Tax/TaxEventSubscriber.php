<?php

namespace App\Listeners\Tax;

use App\Events\Tax\TaxesCalculated;
use App\Events\Tax\TaxConfigurationChanged;
use App\Events\Tax\TaxExemptionApplied;
use App\Models\Tax\TaxCollectionRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TaxEventSubscriber implements ShouldQueue
{
    public $queue = 'webhooks';

    /**
     * Handle taxes calculated event
     */
    public function handleTaxesCalculated(TaxesCalculated $event): void
    {
        // Record tax collection for analytics
        $this->recordTaxCollection($event);

        // Send webhook if configured
        $this->sendWebhook($event->tenantId, $event->toWebhookPayload());
    }

    /**
     * Handle tax configuration changed event
     */
    public function handleTaxConfigurationChanged(TaxConfigurationChanged $event): void
    {
        // Invalidate analytics cache for the tenant
        \App\Models\Tax\TaxAnalyticsCache::invalidateForTenant($event->tenantId);

        // Clear TaxService cache
        app(\App\Services\Tax\TaxService::class)->clearCache($event->tenantId);

        // Send webhook if configured
        $this->sendWebhook($event->tenantId, $event->toWebhookPayload());

        Log::info("Tax configuration {$event->action}", [
            'tenant_id' => $event->tenantId,
            'tax_type' => $event->taxType,
            'tax_id' => $event->taxId,
            'user' => $event->userName,
        ]);
    }

    /**
     * Handle tax exemption applied event
     */
    public function handleTaxExemptionApplied(TaxExemptionApplied $event): void
    {
        // Send webhook if configured
        $this->sendWebhook($event->tenantId, $event->toWebhookPayload());

        Log::info("Tax exemption applied", [
            'tenant_id' => $event->tenantId,
            'exemption' => $event->exemptionName,
            'savings' => $event->savings,
            'order_type' => $event->orderType,
            'order_id' => $event->orderId,
        ]);
    }

    /**
     * Record tax collection for analytics
     */
    protected function recordTaxCollection(TaxesCalculated $event): void
    {
        foreach ($event->breakdown as $item) {
            TaxCollectionRecord::recordFromBreakdown(
                tenantId: $event->tenantId,
                taxableType: $event->orderType,
                taxableId: $event->orderId,
                breakdownItem: $item,
                taxableAmount: $event->subtotal,
                date: Carbon::today()
            );
        }
    }

    /**
     * Send webhook to tenant's configured endpoint
     */
    protected function sendWebhook(int $tenantId, array $payload): void
    {
        try {
            // Get tenant's webhook configuration
            $tenant = \App\Models\Tenant::find($tenantId);
            if (!$tenant) {
                return;
            }

            $webhookUrl = $tenant->settings['tax_webhook_url'] ?? null;
            $webhookSecret = $tenant->settings['tax_webhook_secret'] ?? null;

            if (!$webhookUrl) {
                return;
            }

            // Build webhook payload with signature
            $payloadJson = json_encode($payload);
            $signature = $webhookSecret
                ? hash_hmac('sha256', $payloadJson, $webhookSecret)
                : null;

            $headers = [
                'Content-Type' => 'application/json',
                'X-Tax-Event' => $payload['event'],
                'X-Tenant-ID' => (string) $tenantId,
            ];

            if ($signature) {
                $headers['X-Webhook-Signature'] = $signature;
            }

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($webhookUrl, $payload);

            if (!$response->successful()) {
                Log::warning("Tax webhook failed", [
                    'tenant_id' => $tenantId,
                    'event' => $payload['event'],
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Tax webhook error", [
                'tenant_id' => $tenantId,
                'event' => $payload['event'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the listeners for the subscriber
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TaxesCalculated::class => 'handleTaxesCalculated',
            TaxConfigurationChanged::class => 'handleTaxConfigurationChanged',
            TaxExemptionApplied::class => 'handleTaxExemptionApplied',
        ];
    }
}
