<?php

namespace App\Services\EFactura;

use App\Models\AnafQueue;
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
     */
    protected function getAdapter(string $tenantId): AnafAdapterInterface
    {
        // For now, use default adapter
        // In production, you would load tenant-specific credentials and adapter type
        $adapter = $this->defaultAdapter ?? $this->adapters['mock'] ?? null;

        if (!$adapter) {
            throw new \Exception('No ANAF adapter configured');
        }

        // Load and decrypt tenant credentials
        $credentials = $this->getTenantCredentials($tenantId);
        if ($credentials) {
            $adapter->authenticate($credentials);
        }

        return $adapter;
    }

    /**
     * Get tenant ANAF credentials from secure storage
     */
    protected function getTenantCredentials(string $tenantId): ?array
    {
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
     */
    protected function getSigningConfig(string $tenantId): array
    {
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
