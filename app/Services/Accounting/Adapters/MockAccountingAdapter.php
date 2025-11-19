<?php

namespace App\Services\Accounting\Adapters;

use Illuminate\Support\Str;

class MockAccountingAdapter implements AccountingAdapterInterface
{
    protected array $credentials = [];

    public function authenticate(array $credentials): array
    {
        $this->credentials = $credentials;

        if (empty($credentials['api_key'])) {
            return ['success' => false, 'message' => 'API key required'];
        }

        return ['success' => true, 'message' => 'Authentication successful'];
    }

    public function testConnection(): array
    {
        return [
            'connected' => true,
            'message' => 'Mock provider connected',
            'details' => [
                'provider' => 'Mock Accounting',
                'version' => '1.0.0',
            ],
        ];
    }

    public function ensureCustomer(array $customer): array
    {
        return [
            'customer_id' => 'CUST-' . Str::random(8),
            'created' => rand(0, 1) === 1,
        ];
    }

    public function ensureProducts(array $lines): array
    {
        return array_map(fn($line) => [
            'product_id' => 'PROD-' . Str::random(8),
            'created' => false,
        ], $lines);
    }

    public function createInvoice(array $invoice): array
    {
        return [
            'external_ref' => 'INV-' . now()->format('Y') . '-' . Str::random(6),
            'invoice_number' => 'FACT' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'details' => [
                'date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'total' => $invoice['totals']['total'] ?? 0,
            ],
        ];
    }

    public function getInvoicePdf(string $externalRef): array
    {
        return [
            'pdf_url' => "https://mock-accounting.test/invoices/{$externalRef}.pdf",
            'pdf_content' => null,
        ];
    }

    public function createCreditNote(string $invoiceExternalRef, array $refund): array
    {
        return [
            'external_ref' => 'CN-' . now()->format('Y') . '-' . Str::random(6),
            'credit_note_number' => 'NC' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
        ];
    }

    public function getCustomers(): array
    {
        return [];
    }

    public function getProducts(): array
    {
        return [];
    }
}
