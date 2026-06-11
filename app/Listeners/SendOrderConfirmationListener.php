<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Send Order Confirmation via WhatsApp
 *
 * Listens to OrderConfirmed event and sends WhatsApp confirmation + schedules reminders
 */
class SendOrderConfirmationListener implements ShouldQueue
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderConfirmed $event): void
    {
        // Check if tenant has WhatsApp microservice enabled
        if (!$this->tenantHasMicroservice($event->tenantId, 'whatsapp-notifications')) {
            Log::info('WhatsApp not enabled for tenant', ['tenant_id' => $event->tenantId]);
            return;
        }

        try {
            // Send order confirmation
            $result = $this->whatsAppService->sendOrderConfirmation(
                $event->tenantId,
                $event->orderRef,
                $event->orderData
            );

            if ($result['success']) {
                Log::info('WhatsApp order confirmation sent', [
                    'tenant_id' => $event->tenantId,
                    'order_ref' => $event->orderRef,
                    'message_id' => $result['message_id'] ?? null,
                ]);
            }

            // Schedule reminders if event-based order
            if (isset($event->orderData['event_start_at'])) {
                $reminderResult = $this->whatsAppService->scheduleReminders(
                    $event->tenantId,
                    $event->orderRef,
                    $event->orderData
                );

                if ($reminderResult['success']) {
                    Log::info('WhatsApp reminders scheduled', [
                        'tenant_id' => $event->tenantId,
                        'order_ref' => $event->orderRef,
                        'count' => $reminderResult['scheduled_count'],
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp confirmation', [
                'tenant_id' => $event->tenantId,
                'order_ref' => $event->orderRef,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if tenant has microservice enabled
     */
    protected function tenantHasMicroservice(string $tenantId, string $microserviceSlug): bool
    {
        $microservice = DB::table('microservices')
            ->where('slug', $microserviceSlug)
            ->first();

        if (!$microservice) {
            return false;
        }

        $subscription = DB::table('tenant_microservices')
            ->where('tenant_id', $tenantId)
            ->where('microservice_id', $microservice->id)
            ->where('status', 'active')
            ->first();

        return $subscription !== null;
    }
}
