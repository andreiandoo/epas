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
     * Get adapter by provider key (public access for test connection)
     */
    public function getAdapterPublic(string $provider): AccountingAdapterInterface
    {
        return $this->getAdapter($provider);
    }

    /**
     * Check if a marketplace has an active accounting connector
     */
    public function hasMarketplaceConnector(int $marketplaceClientId): bool
    {
        return DB::table('acc_connectors')
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('status', 'connected')
            ->exists();
    }

    /**
     * Issue invoice for marketplace context
     */
    public function issueMarketplaceInvoice(int $marketplaceClientId, string $orderRef, array $invoiceData): array
    {
        $tenantId = "marketplace_{$marketplaceClientId}";

        // Try marketplace-specific connector first
        $connector = DB::table('acc_connectors')
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('status', 'connected')
            ->first();

        if (!$connector) {
            // Fall back to tenant_id-based lookup
            return $this->issueInvoice($tenantId, $orderRef, $invoiceData);
        }

        $auth = json_decode(Crypt::decryptString($connector->auth), true);
        $adapter = $this->getAdapter($connector->provider, $auth);

        // Create job
        $jobId = DB::table('acc_jobs')->insertGetId([
            'marketplace_client_id' => $marketplaceClientId,
            'tenant_id' => null,
            'type' => 'create_invoice',
            'payload' => json_encode($invoiceData),
            'status' => 'processing',
            'attempts' => 0,
            'max_attempts' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $customerResult = $adapter->ensureCustomer($invoiceData['customer']);
            $productResults = $adapter->ensureProducts($invoiceData['lines']);
            $invoiceResult = $adapter->createInvoice($invoiceData);

            DB::table('acc_jobs')->where('id', $jobId)->update([
                'status' => 'completed',
                'result' => json_encode($invoiceResult),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => true,
                'job_id' => $jobId,
                'external_ref' => $invoiceResult['external_ref'] ?? null,
                'invoice_number' => $invoiceResult['invoice_number'] ?? null,
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
     * Connect marketplace to accounting provider
     */
    public function connectMarketplace(int $marketplaceClientId, string $provider, array $credentials, array $settings = []): array
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

        // Save connector using marketplace_client_id
        $existing = DB::table('acc_connectors')
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('provider', $provider)
            ->first();

        $values = [
            'auth' => Crypt::encryptString(json_encode($credentials)),
            'status' => $testResult['connected'] ? 'connected' : 'error',
            'settings' => json_encode($settings),
            'last_test_at' => now(),
            'last_error' => $testResult['connected'] ? null : $testResult['message'],
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('acc_connectors')->where('id', $existing->id)->update($values);
        } else {
            DB::table('acc_connectors')->insert(array_merge($values, [
                'marketplace_client_id' => $marketplaceClientId,
                'provider' => $provider,
                'created_at' => now(),
            ]));
        }

        return [
            'success' => $testResult['connected'],
            'message' => $testResult['message'],
            'details' => $testResult['details'] ?? [],
        ];
    }

    /**
     * Connect to accounting provider (tenant context)
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
        $existing = DB::table('acc_connectors')
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();

        $values = [
            'auth' => Crypt::encryptString(json_encode($credentials)),
            'status' => $testResult['connected'] ? 'connected' : 'error',
            'settings' => json_encode($settings),
            'last_test_at' => now(),
            'last_error' => $testResult['connected'] ? null : $testResult['message'],
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('acc_connectors')->where('id', $existing->id)->update($values);
        } else {
            DB::table('acc_connectors')->insert(array_merge($values, [
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'created_at' => now(),
            ]));
        }

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
        $existing = DB::table('acc_mappings')
            ->where('tenant_id', $tenantId)
            ->where('entity', $entity)
            ->where('local_ref', $localRef)
            ->first();

        $values = [
            'remote_ref' => $remoteRef,
            'meta' => json_encode($meta),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('acc_mappings')->where('id', $existing->id)->update($values);
        } else {
            DB::table('acc_mappings')->insert(array_merge($values, [
                'tenant_id' => $tenantId,
                'entity' => $entity,
                'local_ref' => $localRef,
                'created_at' => now(),
            ]));
        }
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
                'external_ref' => $invoiceResult['external_ref'] ?? null,
                'invoice_number' => $invoiceResult['invoice_number'] ?? null,
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
     * Get invoice PDF for marketplace context
     */
    public function getMarketplaceInvoicePdf(int $marketplaceClientId, string $externalRef, string $docType = 'invoice'): array
    {
        $connector = DB::table('acc_connectors')
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('status', 'connected')
            ->first();

        if (!$connector) {
            // Fall back to tenant_id-based lookup
            $tenantId = "marketplace_{$marketplaceClientId}";
            return $this->getInvoicePdf($tenantId, $externalRef);
        }

        $auth = json_decode(Crypt::decryptString($connector->auth), true);
        $adapter = $this->getAdapter($connector->provider, $auth);

        if ($adapter instanceof \App\Services\Accounting\Adapters\OblioAdapter) {
            return $adapter->getInvoicePdf($externalRef, $docType);
        }

        return $adapter->getInvoicePdf($externalRef);
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
