<?php

namespace App\Services\EFactura\Adapters;

use Illuminate\Support\Str;

/**
 * Mock ANAF Adapter for testing and development
 *
 * Simulates ANAF SPV behavior without making real API calls.
 * Useful for testing the eFactura workflow in sandbox environments.
 */
class MockAnafAdapter implements AnafAdapterInterface
{
    protected bool $authenticated = false;
    protected array $credentials = [];
    protected array $submissions = []; // Simulated submission storage

    /**
     * {@inheritdoc}
     */
    public function authenticate(array $credentials): array
    {
        // Simulate authentication validation
        if (empty($credentials['certificate']) && empty($credentials['api_key'])) {
            return [
                'success' => false,
                'message' => 'Missing certificate or API key',
            ];
        }

        $this->authenticated = true;
        $this->credentials = $credentials;

        return [
            'success' => true,
            'message' => 'Mock authentication successful',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildXml(array $invoice): array
    {
        // Simulate XML building with basic validation
        $errors = [];

        if (empty($invoice['invoice_number'])) {
            $errors[] = 'Invoice number is required';
        }

        if (empty($invoice['seller'])) {
            $errors[] = 'Seller information is required';
        }

        if (empty($invoice['buyer'])) {
            $errors[] = 'Buyer information is required';
        }

        if (empty($invoice['lines'])) {
            $errors[] = 'Invoice must have at least one line item';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'xml' => null,
                'hash' => null,
                'errors' => $errors,
            ];
        }

        // Generate mock UBL XML
        $xml = $this->generateMockXml($invoice);
        $hash = hash('sha256', $xml);

        return [
            'success' => true,
            'xml' => $xml,
            'hash' => $hash,
            'errors' => [],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function signAndPackage(string $xml, array $signingConfig = []): array
    {
        // Simulate signing process
        if (!empty($signingConfig) && empty($signingConfig['certificate'])) {
            return [
                'success' => false,
                'package' => null,
                'message' => 'Certificate required for signing',
            ];
        }

        // Mock signed package (in reality this would be XMLDSig)
        $package = base64_encode($xml);

        return [
            'success' => true,
            'package' => $package,
            'message' => 'Mock package signed successfully',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function submit(string $package, array $metadata = []): array
    {
        if (!$this->authenticated) {
            return [
                'success' => false,
                'remote_id' => null,
                'download_id' => null,
                'message' => 'Not authenticated',
                'submitted_at' => null,
            ];
        }

        // Generate mock ANAF IDs
        $remoteId = 'ANAF-' . strtoupper(Str::random(16));
        $downloadId = 'DWN-' . strtoupper(Str::random(12));

        // Store submission for later polling
        $this->submissions[$remoteId] = [
            'package' => $package,
            'metadata' => $metadata,
            'status' => 'processing',
            'submitted_at' => now()->toIso8601String(),
            'download_id' => $downloadId,
        ];

        return [
            'success' => true,
            'remote_id' => $remoteId,
            'download_id' => $downloadId,
            'message' => 'Mock submission successful',
            'submitted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function poll(string $remoteId): array
    {
        if (!isset($this->submissions[$remoteId])) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'Submission not found',
                'errors' => [],
                'artifacts' => [],
            ];
        }

        $submission = $this->submissions[$remoteId];

        // Simulate ANAF processing: 80% acceptance rate
        if ($submission['status'] === 'processing') {
            $random = rand(1, 100);

            if ($random <= 80) {
                // Accepted
                $this->submissions[$remoteId]['status'] = 'accepted';
                return [
                    'success' => true,
                    'status' => 'accepted',
                    'message' => 'Invoice accepted by ANAF',
                    'errors' => [],
                    'artifacts' => [
                        'download_id' => $submission['download_id'],
                        'confirmation_number' => 'CONF-' . strtoupper(Str::random(8)),
                    ],
                ];
            } else {
                // Rejected
                $this->submissions[$remoteId]['status'] = 'rejected';
                return [
                    'success' => true,
                    'status' => 'rejected',
                    'message' => 'Invoice rejected by ANAF',
                    'errors' => [
                        'Mock validation error: Invalid VAT number format',
                        'Mock validation error: Line item missing unit code',
                    ],
                    'artifacts' => [],
                ];
            }
        }

        // Already processed
        return [
            'success' => true,
            'status' => $submission['status'],
            'message' => 'Final status: ' . $submission['status'],
            'errors' => $submission['status'] === 'rejected' ? ['Previously rejected'] : [],
            'artifacts' => $submission['status'] === 'accepted' ? [
                'download_id' => $submission['download_id'],
            ] : [],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function download(string $downloadId): array
    {
        // Find submission by download_id
        $found = false;
        foreach ($this->submissions as $submission) {
            if (($submission['download_id'] ?? null) === $downloadId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return [
                'success' => false,
                'content' => null,
                'mime_type' => null,
                'filename' => null,
            ];
        }

        // Return mock PDF content
        $pdfContent = '%PDF-1.4 Mock eFactura Receipt';

        return [
            'success' => true,
            'content' => $pdfContent,
            'mime_type' => 'application/pdf',
            'filename' => "efactura_{$downloadId}.pdf",
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): array
    {
        if (!$this->authenticated) {
            return [
                'connected' => false,
                'message' => 'Not authenticated',
            ];
        }

        return [
            'connected' => true,
            'message' => 'Mock connection successful',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Mock ANAF Adapter',
            'version' => '1.0.0',
            'supports_signing' => true,
            'supports_polling' => true,
        ];
    }

    /**
     * Generate mock UBL XML structure
     */
    protected function generateMockXml(array $invoice): string
    {
        $invoiceNumber = $invoice['invoice_number'] ?? 'UNKNOWN';
        $issueDate = $invoice['issue_date'] ?? date('Y-m-d');
        $sellerName = $invoice['seller']['name'] ?? 'Seller';
        $buyerName = $invoice['buyer']['name'] ?? 'Buyer';
        $total = $invoice['total'] ?? 0;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <ID>{$invoiceNumber}</ID>
    <IssueDate>{$issueDate}</IssueDate>
    <AccountingSupplierParty>
        <Party>
            <PartyName>
                <Name>{$sellerName}</Name>
            </PartyName>
        </Party>
    </AccountingSupplierParty>
    <AccountingCustomerParty>
        <Party>
            <PartyName>
                <Name>{$buyerName}</Name>
            </PartyName>
        </Party>
    </AccountingCustomerParty>
    <LegalMonetaryTotal>
        <PayableAmount currencyID="RON">{$total}</PayableAmount>
    </LegalMonetaryTotal>
</Invoice>
XML;
    }
}
