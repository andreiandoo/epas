<?php

namespace App\Services\Accounting;

use App\Services\Accounting\Adapters\AccountingAdapterInterface;
use App\Services\Accounting\Adapters\MockAccountingAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class AccountingService
{
    protected array $adapters = [];

    public function __construct()
    {
        $this->registerAdapter('mock', new MockAccountingAdapter());
    }

    public function registerAdapter(string $key, AccountingAdapterInterface $adapter): void
    {
        $this->adapters[$key] = $adapter;
    }

    protected function getAdapter(string $provider, ?array $auth = null): AccountingAdapterInterface
    {
        if (!isset($this->adapters[$provider])) {
            throw new \Exception("Accounting adapter '{$provider}' not registered");
        }

        $adapter = $this->adapters[$provider];

        if ($auth) {
            $adapter->authenticate($auth);
        }

        return $adapter;
    }

    /**
     * Connect to accounting provider
     */
    public function connect(string $tenantId, string $provider, array $credentials, array $settings = []): array
    {
        $adapter = $this->getAdapter($provider);

        // Authenticate
        $authResult = $adapter->authenticate($credentials);

        if (!$authResult['success']) {
            return [
                'success' => false,
                'message' => $authResult['message'],
            ];
        }

        // Test connection
        $testResult = $adapter->testConnection();

        // Save connector
        $connector = DB::table('acc_connectors')->updateOrInsert(
            ['tenant_id' => $tenantId, 'provider' => $provider],
            [
                'auth' => Crypt::encryptString(json_encode($credentials)),
                'status' => $testResult['connected'] ? 'connected' : 'error',
                'settings' => json_encode($settings),
                'last_test_at' => now(),
                'last_error' => $testResult['connected'] ? null : $testResult['message'],
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return [
            'success' => $testResult['connected'],
            'message' => $testResult['message'],
            'details' => $testResult['details'] ?? [],
        ];
    }

    /**
     * Create mapping
     */
    public function createMapping(string $tenantId, string $entity, string $localRef, string $remoteRef, array $meta = []): void
    {
        DB::table('acc_mappings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'entity' => $entity, 'local_ref' => $localRef],
            [
                'remote_ref' => $remoteRef,
                'meta' => json_encode($meta),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    /**
     * Issue invoice externally
     */
    public function issueInvoice(string $tenantId, string $orderRef, array $invoiceData): array
    {
        // Get connector
        $connector = DB::table('acc_connectors')
            ->where('tenant_id', $tenantId)
            ->where('status', 'connected')
            ->first();

        if (!$connector) {
            throw new \Exception('No active accounting connector found');
        }

        $auth = json_decode(Crypt::decryptString($connector->auth), true);
        $adapter = $this->getAdapter($connector->provider, $auth);

        // Create job
        $jobId = DB::table('acc_jobs')->insertGetId([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenantId,
            'type' => 'create_invoice',
            'payload' => json_encode($invoiceData),
            'status' => 'processing',
            'attempts' => 0,
            'max_attempts' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // Ensure customer
            $customerResult = $adapter->ensureCustomer($invoiceData['customer']);

            // Ensure products
            $productResults = $adapter->ensureProducts($invoiceData['lines']);

            // Create invoice
            $invoiceResult = $adapter->createInvoice($invoiceData);

            // Update job
            DB::table('acc_jobs')->where('id', $jobId)->update([
                'status' => 'completed',
                'result' => json_encode($invoiceResult),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => true,
                'job_id' => $jobId,
                'external_ref' => $invoiceResult['external_ref'],
                'invoice_number' => $invoiceResult['invoice_number'],
            ];

        } catch (\Exception $e) {
            DB::table('acc_jobs')->where('id', $jobId)->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
                'attempts' => DB::raw('attempts + 1'),
                'next_retry_at' => now()->addMinutes(5),
                'updated_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Get invoice PDF
     */
    public function getInvoicePdf(string $tenantId, string $externalRef): array
    {
        $connector = DB::table('acc_connectors')
            ->where('tenant_id', $tenantId)
            ->where('status', 'connected')
            ->first();

        if (!$connector) {
            throw new \Exception('No active accounting connector');
        }

        $auth = json_decode(Crypt::decryptString($connector->auth), true);
        $adapter = $this->getAdapter($connector->provider, $auth);

        return $adapter->getInvoicePdf($externalRef);
    }

    /**
     * Create credit note
     */
    public function createCreditNote(string $tenantId, string $invoiceExternalRef, array $refundData): array
    {
        $connector = DB::table('acc_connectors')
            ->where('tenant_id', $tenantId)
            ->where('status', 'connected')
            ->first();

        if (!$connector) {
            throw new \Exception('No active accounting connector');
        }

        $auth = json_decode(Crypt::decryptString($connector->auth), true);
        $adapter = $this->getAdapter($connector->provider, $auth);

        return $adapter->createCreditNote($invoiceExternalRef, $refundData);
    }
}
