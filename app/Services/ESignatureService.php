<?php

namespace App\Services;

use App\Models\ContractAmendment;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ESignatureService
{
    protected string $provider;
    protected ?string $apiKey;
    protected ?string $apiUrl;

    public function __construct()
    {
        $this->provider = config('services.esignature.provider', 'opensign');
        $this->apiKey = config('services.esignature.api_key');
        $this->apiUrl = config('services.esignature.api_url');
    }

    /**
     * Check if e-signature service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiUrl);
    }

    /**
     * Send a contract for e-signature
     */
    public function sendForSignature(Tenant $tenant): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'E-signature service not configured',
            ];
        }

        if (!$tenant->contract_file) {
            return [
                'success' => false,
                'error' => 'No contract file found',
            ];
        }

        try {
            $pdfContent = Storage::disk('public')->get($tenant->contract_file);
            $filename = 'Contract-' . ($tenant->contract_number ?? 'CTR-' . $tenant->id) . '.pdf';

            $response = $this->createDocument([
                'title' => "Contract {$tenant->contract_number}",
                'file' => base64_encode($pdfContent),
                'filename' => $filename,
                'signers' => [
                    [
                        'name' => trim($tenant->contact_first_name . ' ' . $tenant->contact_last_name),
                        'email' => $tenant->contact_email,
                        'role' => 'signer',
                    ],
                ],
                'message' => "Please review and sign your contract with us.",
                'redirect_url' => config('app.url') . '/contract-signed?tenant=' . $tenant->id,
            ]);

            if ($response['success']) {
                // Update tenant with e-signature document ID
                $tenant->update([
                    'esignature_document_id' => $response['document_id'],
                    'esignature_status' => 'pending',
                    'esignature_sent_at' => now(),
                ]);

                Log::info('Contract sent for e-signature', [
                    'tenant_id' => $tenant->id,
                    'document_id' => $response['document_id'],
                ]);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to send contract for e-signature', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an amendment for e-signature
     */
    public function sendAmendmentForSignature(ContractAmendment $amendment): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'E-signature service not configured',
            ];
        }

        if (!$amendment->file_path) {
            return [
                'success' => false,
                'error' => 'No amendment file found',
            ];
        }

        try {
            $tenant = $amendment->tenant;
            $pdfContent = Storage::disk('public')->get($amendment->file_path);

            $response = $this->createDocument([
                'title' => "Amendment {$amendment->amendment_number}",
                'file' => base64_encode($pdfContent),
                'filename' => "Amendment-{$amendment->amendment_number}.pdf",
                'signers' => [
                    [
                        'name' => trim($tenant->contact_first_name . ' ' . $tenant->contact_last_name),
                        'email' => $tenant->contact_email,
                        'role' => 'signer',
                    ],
                ],
                'message' => "Please review and sign this contract amendment.",
            ]);

            if ($response['success']) {
                $amendment->update([
                    'metadata' => array_merge($amendment->metadata ?? [], [
                        'esignature_document_id' => $response['document_id'],
                        'esignature_sent_at' => now()->toISOString(),
                    ]),
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to send amendment for e-signature', [
                'amendment_id' => $amendment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check signature status
     */
    public function checkStatus(string $documentId): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'E-signature service not configured',
            ];
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->apiUrl}/documents/{$documentId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->json('status'),
                    'signed_at' => $response->json('signed_at'),
                    'signers' => $response->json('signers'),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('message', 'Unknown error'),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Download signed document
     */
    public function downloadSigned(string $documentId): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->apiUrl}/documents/{$documentId}/download");

            if ($response->successful()) {
                return $response->body();
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to download signed document', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create document for signing
     */
    protected function createDocument(array $data): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->apiUrl}/documents", $data);

        if ($response->successful()) {
            return [
                'success' => true,
                'document_id' => $response->json('id'),
                'signing_url' => $response->json('signing_url'),
            ];
        }

        return [
            'success' => false,
            'error' => $response->json('message', 'Failed to create document'),
        ];
    }

    /**
     * Get API headers
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get available e-signature providers
     */
    public static function getProviders(): array
    {
        return [
            'opensign' => [
                'name' => 'OpenSign',
                'description' => 'Free, open-source e-signature solution',
                'pricing' => 'Free (self-hosted) or cloud',
                'url' => 'https://opensignlabs.com',
            ],
            'signrequest' => [
                'name' => 'SignRequest',
                'description' => 'Simple e-signature API',
                'pricing' => '10 docs/month free',
                'url' => 'https://signrequest.com',
            ],
            'boldsign' => [
                'name' => 'BoldSign',
                'description' => 'Laravel-friendly e-signature API',
                'pricing' => '100 docs/month free trial',
                'url' => 'https://boldsign.com',
            ],
        ];
    }
}
