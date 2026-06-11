<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use App\Services\Accounting\AccountingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Issue Invoice via Accounting Connectors
 *
 * Listens to PaymentCaptured event and creates invoice in external accounting system
 */
class IssueInvoiceListener implements ShouldQueue
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PaymentCaptured $event): void
    {
        // Check if tenant has accounting connectors microservice enabled
        if (!$this->tenantHasMicroservice($event->tenantId, 'accounting-connectors')) {
            Log::info('Accounting connectors not enabled for tenant', ['tenant_id' => $event->tenantId]);
            return;
        }

        // Check if issue_extern is enabled for this tenant
        if (!$this->isExternalIssuanceEnabled($event->tenantId)) {
            Log::info('External invoice issuance not enabled', ['tenant_id' => $event->tenantId]);
            return;
        }

        try {
            // Issue invoice in external accounting system
            $result = $this->accountingService->issueInvoice(
                $event->tenantId,
                $event->orderRef,
                $this->buildInvoiceData($event->paymentData)
            );

            if ($result['success']) {
                Log::info('Invoice issued in external system', [
                    'tenant_id' => $event->tenantId,
                    'order_ref' => $event->orderRef,
                    'external_ref' => $result['external_ref'] ?? null,
                    'invoice_number' => $result['invoice_number'] ?? null,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to issue external invoice', [
                'tenant_id' => $event->tenantId,
                'order_ref' => $event->orderRef,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build invoice data for accounting system
     */
    protected function buildInvoiceData(array $paymentData): array
    {
        return [
            'customer' => $paymentData['customer'] ?? [],
            'lines' => $paymentData['lines'] ?? [],
            'total' => $paymentData['total'] ?? 0,
            'vat_total' => $paymentData['vat_total'] ?? 0,
            'grand_total' => $paymentData['grand_total'] ?? 0,
            'currency' => $paymentData['currency'] ?? 'RON',
            'issue_date' => $paymentData['issue_date'] ?? now()->format('Y-m-d'),
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

    /**
     * Check if external invoice issuance is enabled
     */
    protected function isExternalIssuanceEnabled(string $tenantId): bool
    {
        $connector = DB::table('acc_connectors')
            ->where('tenant_id', $tenantId)
            ->where('status', 'connected')
            ->first();

        if (!$connector) {
            return false;
        }

        $settings = json_decode($connector->settings ?? '{}', true);
        return $settings['issue_extern'] ?? false;
    }
}
