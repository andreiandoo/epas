<?php

namespace App\Services\EFactura;

use App\Models\AnafQueue;
use App\Models\MarketplaceClientMicroservice;
use App\Services\EFactura\Adapters\AnafAdapterInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * eFactura Service
 *
 * Manages the complete lifecycle of eFactura submission to ANAF:
 * - Queue invoices for submission
 * - Build and sign XML
 * - Submit to ANAF SPV
 * - Poll for status updates
 * - Handle retries with backoff
 * - Store responses and artifacts
 */
class EFacturaService
{
    protected array $adapters = [];
    protected ?AnafAdapterInterface $defaultAdapter = null;

    /**
     * Register ANAF adapter
     */
    public function registerAdapter(string $key, AnafAdapterInterface $adapter): void
    {
        $this->adapters[$key] = $adapter;

        if ($key === 'default' || $this->defaultAdapter === null) {
            $this->defaultAdapter = $adapter;
        }
    }

    /**
     * Get adapter for tenant (with authentication)
     *
     * Selects real ANAF adapter when environment=production and credentials are available,
     * otherwise falls back to mock adapter.
     */
    protected function getAdapter(string $tenantId, ?array $credentialsOverride = null): AnafAdapterInterface
    {
        // Load credentials
        $credentials = $credentialsOverride ?? $this->getTenantCredentials($tenantId);

        // Select adapter based on environment setting
        $environment = $credentials['environment'] ?? 'test';
        if ($environment === 'production' && $credentials && isset($this->adapters['anaf'])) {
            $adapter = $this->adapters['anaf'];
        } else {
            $adapter = $this->adapters['mock'] ?? $this->defaultAdapter;
        }

        if (!$adapter) {
            throw new \Exception('No ANAF adapter configured');
        }

        // Authenticate with credentials
        if ($credentials) {
            $adapter->authenticate($credentials);
        }

        return $adapter;
    }

    /**
     * Get tenant ANAF credentials from secure storage
     *
     * Supports both regular tenants (from tenant_configs) and marketplace clients
     * (from marketplace_client_microservices.settings where tenant_id starts with "marketplace_")
     */
    protected function getTenantCredentials(string $tenantId): ?array
    {
        // Marketplace context: tenant_id = "marketplace_{id}"
        if (str_starts_with($tenantId, 'marketplace_')) {
            return $this->getMarketplaceCredentials($tenantId);
        }

        $config = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->where('key', 'efactura_credentials')
            ->first();

        if (!$config || !$config->value) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($config->value), true);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt eFactura credentials', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get eFactura credentials from marketplace_client_microservices
     */
    protected function getMarketplaceCredentials(string $tenantId): ?array
    {
        $marketplaceClientId = (int) str_replace('marketplace_', '', $tenantId);

        $mcm = MarketplaceClientMicroservice::whereHas('microservice', function ($q) {
            $q->where('slug', 'efactura-ro');
        })
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('status', 'active')
            ->first();

        if (!$mcm) {
            return null;
        }

        $settings = $mcm->settings ?? [];

        // Map microservice settings to credentials format expected by adapters
        return [
            'environment' => $settings['environment'] ?? 'test',
            'api_token' => $settings['anaf_api_token'] ?? null,
            'certificate' => $settings['anaf_certificate'] ?? null,
            'private_key' => $settings['anaf_private_key'] ?? null,
            'cui' => $settings['anaf_cui'] ?? null,
        ];
    }

    /**
     * Queue a marketplace invoice for eFactura submission
     *
     * Convenience method that uses marketplace_ prefix for tenant_id
     */
    public function queueMarketplaceInvoice(int $marketplaceClientId, int $invoiceId, array $invoiceData): array
    {
        $tenantId = "marketplace_{$marketplaceClientId}";
        return $this->queueInvoice($tenantId, $invoiceId, $invoiceData);
    }

    /**
     * Queue invoice for eFactura submission (idempotent)
     */
    public function queueInvoice(string $tenantId, int $invoiceId, array $invoiceData): array
    {
        // Check if already queued (idempotency)
        $existing = AnafQueue::where('tenant_id', $tenantId)
            ->where('invoice_id', $invoiceId)
            ->first();

        if ($existing) {
            return [
                'success' => true,
                'queue_id' => $existing->id,
                'status' => $existing->status,
                'message' => 'Invoice already in queue',
            ];
        }

        // Build XML to validate invoice data
        $adapter = $this->getAdapter($tenantId);
        $xmlResult = $adapter->buildXml($invoiceData);

        if (!$xmlResult['success']) {
            return [
                'success' => false,
                'errors' => $xmlResult['errors'],
                'message' => 'Invoice validation failed',
            ];
        }

        // Store XML payload
        $xmlPath = "efactura/{$tenantId}/{$invoiceId}.xml";
        Storage::put($xmlPath, $xmlResult['xml']);

        // Create queue entry
        $queue = AnafQueue::create([
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'payload_ref' => $xmlPath,
            'status' => AnafQueue::STATUS_QUEUED,
            'xml_hash' => $xmlResult['hash'],
            'next_retry_at' => now(),
        ]);

        return [
            'success' => true,
            'queue_id' => $queue->id,
            'status' => $queue->status,
            'message' => 'Invoice queued for eFactura submission',
        ];
    }

    /**
     * Process queue entry: build, sign, and submit to ANAF
     */
    public function processQueueEntry(AnafQueue $queue): array
    {
        if (!$queue->canRetry()) {
            return [
                'success' => false,
                'message' => 'Max retry attempts reached',
            ];
        }

        try {
            $adapter = $this->getAdapter($queue->tenant_id);

            // Load XML payload
            $xml = Storage::get($queue->payload_ref);
            if (!$xml) {
                throw new \Exception('XML payload not found');
            }

            // Sign and package
            $signingConfig = $this->getSigningConfig($queue->tenant_id);
            $packageResult = $adapter->signAndPackage($xml, $signingConfig);

            if (!$packageResult['success']) {
                throw new \Exception($packageResult['message']);
            }

            // Submit to ANAF
            $submitResult = $adapter->submit($packageResult['package'], [
                'tenant_id' => $queue->tenant_id,
                'invoice_id' => $queue->invoice_id,
            ]);

            if (!$submitResult['success']) {
                throw new \Exception($submitResult['message']);
            }

            // Mark as submitted
            $queue->markAsSubmitted(
                $submitResult['remote_id'],
                $submitResult
            );

            // Store download_id if provided
            if (!empty($submitResult['download_id'])) {
                $queue->storeAnafArtifacts([
                    'download_id' => $submitResult['download_id'],
                ]);
            }

            Log::info('eFactura submitted to ANAF', [
                'queue_id' => $queue->id,
                'remote_id' => $submitResult['remote_id'],
            ]);

            return [
                'success' => true,
                'remote_id' => $submitResult['remote_id'],
                'message' => 'Submitted to ANAF',
            ];

        } catch (\Exception $e) {
            $queue->markAsError($e->getMessage());

            Log::error('eFactura submission failed', [
                'queue_id' => $queue->id,
                'error' => $e->getMessage(),
                'attempts' => $queue->attempts,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Poll ANAF for submission status
     */
    public function pollStatus(AnafQueue $queue): array
    {
        if ($queue->isFinal()) {
            return [
                'success' => true,
                'status' => $queue->status,
                'message' => 'Already in final state',
            ];
        }

        $remoteId = $queue->getRemoteId();
        if (!$remoteId) {
            return [
                'success' => false,
                'message' => 'No remote_id found',
            ];
        }

        try {
            $adapter = $this->getAdapter($queue->tenant_id);
            $pollResult = $adapter->poll($remoteId);

            if (!$pollResult['success']) {
                throw new \Exception($pollResult['message']);
            }

            // Update queue based on ANAF status
            switch ($pollResult['status']) {
                case 'accepted':
                    $queue->markAsAccepted($pollResult);

                    // Store artifacts
                    if (!empty($pollResult['artifacts'])) {
                        $queue->storeAnafArtifacts($pollResult['artifacts']);
                    }

                    Log::info('eFactura accepted by ANAF', [
                        'queue_id' => $queue->id,
                        'remote_id' => $remoteId,
                    ]);
                    break;

                case 'rejected':
                    $errors = implode('; ', $pollResult['errors'] ?? ['Unknown rejection']);
                    $queue->markAsRejected($errors, $pollResult);

                    Log::warning('eFactura rejected by ANAF', [
                        'queue_id' => $queue->id,
                        'remote_id' => $remoteId,
                        'errors' => $pollResult['errors'],
                    ]);
                    break;

                case 'processing':
                    // Still processing, no update needed
                    Log::debug('eFactura still processing', [
                        'queue_id' => $queue->id,
                        'remote_id' => $remoteId,
                    ]);
                    break;

                default:
                    Log::warning('Unknown ANAF status', [
                        'queue_id' => $queue->id,
                        'status' => $pollResult['status'],
                    ]);
            }

            return [
                'success' => true,
                'status' => $pollResult['status'],
                'message' => $pollResult['message'],
            ];

        } catch (\Exception $e) {
            Log::error('eFactura poll failed', [
                'queue_id' => $queue->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Manual retry for failed submission
     */
    public function retry(int $queueId): array
    {
        $queue = AnafQueue::find($queueId);

        if (!$queue) {
            return [
                'success' => false,
                'message' => 'Queue entry not found',
            ];
        }

        if ($queue->isFinal()) {
            return [
                'success' => false,
                'message' => 'Cannot retry final status',
            ];
        }

        // Reset for retry
        $queue->resetForRetry();

        // Process immediately
        return $this->processQueueEntry($queue);
    }

    /**
     * Process all ready queue entries
     */
    public function processQueue(int $limit = 10): array
    {
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        $entries = AnafQueue::readyForProcessing()
            ->limit($limit)
            ->get();

        foreach ($entries as $queue) {
            $result = $this->processQueueEntry($queue);
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
     * Poll all submitted entries awaiting response
     */
    public function pollPending(int $limit = 20): array
    {
        $polled = 0;
        $accepted = 0;
        $rejected = 0;

        $entries = AnafQueue::awaitingPoll()
            ->limit($limit)
            ->get();

        foreach ($entries as $queue) {
            $result = $this->pollStatus($queue);
            $polled++;

            $queue->refresh();
            if ($queue->status === AnafQueue::STATUS_ACCEPTED) {
                $accepted++;
            } elseif ($queue->status === AnafQueue::STATUS_REJECTED) {
                $rejected++;
            }
        }

        return [
            'polled' => $polled,
            'accepted' => $accepted,
            'rejected' => $rejected,
        ];
    }

    /**
     * Download ANAF receipt/artifact
     */
    public function downloadReceipt(int $queueId): array
    {
        $queue = AnafQueue::find($queueId);

        if (!$queue) {
            return [
                'success' => false,
                'message' => 'Queue entry not found',
            ];
        }

        $downloadId = $queue->anaf_ids['download_id'] ?? null;
        if (!$downloadId) {
            return [
                'success' => false,
                'message' => 'No download_id available',
            ];
        }

        try {
            $adapter = $this->getAdapter($queue->tenant_id);
            $downloadResult = $adapter->download($downloadId);

            if (!$downloadResult['success']) {
                throw new \Exception('Download failed');
            }

            return [
                'success' => true,
                'content' => $downloadResult['content'],
                'mime_type' => $downloadResult['mime_type'],
                'filename' => $downloadResult['filename'],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get signing configuration for tenant
     *
     * For marketplace context, signing config comes from the same microservice settings
     */
    protected function getSigningConfig(string $tenantId): array
    {
        // Marketplace context: use credentials (certificate + private_key) as signing config
        if (str_starts_with($tenantId, 'marketplace_')) {
            $credentials = $this->getMarketplaceCredentials($tenantId);
            if ($credentials) {
                return [
                    'certificate' => $credentials['certificate'] ?? null,
                    'private_key' => $credentials['private_key'] ?? null,
                ];
            }
            return [];
        }

        $config = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->where('key', 'efactura_signing')
            ->first();

        if (!$config || !$config->value) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($config->value), true);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt signing config', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get queue statistics for tenant
     */
    public function getStats(string $tenantId): array
    {
        $stats = AnafQueue::forTenant($tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'queued' => $stats[AnafQueue::STATUS_QUEUED] ?? 0,
            'submitted' => $stats[AnafQueue::STATUS_SUBMITTED] ?? 0,
            'accepted' => $stats[AnafQueue::STATUS_ACCEPTED] ?? 0,
            'rejected' => $stats[AnafQueue::STATUS_REJECTED] ?? 0,
            'error' => $stats[AnafQueue::STATUS_ERROR] ?? 0,
        ];
    }
}
