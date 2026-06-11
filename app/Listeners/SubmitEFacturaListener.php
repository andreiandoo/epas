<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use App\Services\EFactura\EFacturaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Submit eFactura to ANAF
 *
 * Listens to PaymentCaptured event and submits invoice to ANAF SPV
 */
class SubmitEFacturaListener implements ShouldQueue
{
    public function __construct(
        protected EFacturaService $eFacturaService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PaymentCaptured $event): void
    {
        // Check if tenant has eFactura microservice enabled
        if (!$this->tenantHasMicroservice($event->tenantId, 'efactura-ro')) {
            Log::info('eFactura not enabled for tenant', ['tenant_id' => $event->tenantId]);
            return;
        }

        try {
            // Queue invoice for eFactura submission
            $result = $this->eFacturaService->queueInvoice(
                $event->tenantId,
                $event->paymentData['invoice_id'] ?? 0,
                $this->buildInvoiceData($event->paymentData)
            );

            if ($result['success']) {
                Log::info('eFactura queued for submission', [
                    'tenant_id' => $event->tenantId,
                    'order_ref' => $event->orderRef,
                    'queue_id' => $result['queue_id'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to queue eFactura', [
                'tenant_id' => $event->tenantId,
                'order_ref' => $event->orderRef,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build invoice data for eFactura submission
     */
    protected function buildInvoiceData(array $paymentData): array
    {
        return [
            'invoice_number' => $paymentData['invoice_number'] ?? '',
            'issue_date' => $paymentData['issue_date'] ?? now()->format('Y-m-d'),
            'seller' => $paymentData['seller'] ?? [],
            'buyer' => $paymentData['buyer'] ?? [],
            'lines' => $paymentData['lines'] ?? [],
            'total' => $paymentData['total'] ?? 0,
            'vat_total' => $paymentData['vat_total'] ?? 0,
            'grand_total' => $paymentData['grand_total'] ?? 0,
        ];
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
