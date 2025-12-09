# Accounting Connectors

## Short Presentation

Bridge the gap between your ticketing platform and your accounting software with Accounting Connectors. This powerful integration service automatically syncs your invoices with leading accounting platforms, eliminating manual data entry and reducing errors.

Whether you use SmartBill, FGO, Exact, Xero, or QuickBooks, our provider-agnostic adapter system connects seamlessly with your existing accounting workflow. Every ticket sale, refund, and transaction flows directly into your accounting software without lifting a finger.

The intelligent mapping wizard walks you through connecting products, taxes, accounts, and invoice series between systems. Once configured, the service automatically ensures customers exist in your accounting software before issuing invoices, syncs products with proper tax configurations, and creates invoices with all required details.

Need credit notes for refunds? They're generated automatically. Want PDF invoices delivered to customers? We retrieve them from your accounting provider and deliver them seamlessly. The job queue with retry logic ensures nothing gets lost, even during temporary connectivity issues.

Security-first design means OAuth2 and API key authentication options, encrypted credential storage, and connection testing before going live. Complete error tracking and dead-letter queue management means you're always aware of any issues.

Stop copying data between systems. Let Accounting Connectors do the heavy lifting while you focus on growing your event business.

---

## Detailed Description

Accounting Connectors is a comprehensive integration service that automates the flow of financial data between your ticketing platform and external accounting systems. Built with a provider-agnostic adapter pattern, it supports multiple accounting software providers while maintaining a consistent integration experience.

### Supported Providers

- **SmartBill** - Popular Romanian accounting software
- **FGO** - Enterprise resource planning
- **Exact** - Cloud business software
- **Xero** - Cloud-based accounting
- **QuickBooks** - Small business accounting

### How It Works

1. **Configuration**: Use the mapping wizard to connect your platform data structures with your accounting software's entities (customers, products, taxes, accounts, invoice series).

2. **Customer Sync**: Before issuing an invoice, the service ensures the customer exists in your accounting software using the `ensureCustomer` function. If not, it creates them automatically.

3. **Product Sync**: The `ensureProducts` function verifies all line items exist in your accounting system with proper tax configurations.

4. **Invoice Issuance**: Invoices are created via the provider's API with all mapped fields, proper tax calculations, and correct invoice series.

5. **Document Delivery**: Once issued, PDF invoices are retrieved from the accounting provider and can be automatically delivered to customers.

### External Issuance Mode

The service supports `issue_extern` mode where invoices are created directly in the external accounting system rather than locally. This is ideal for businesses that want their accounting software to be the source of truth for invoicing.

### eFactura Integration

For Romanian businesses, the service integrates with eFactura submission. You can choose whether eFactura is handled by the accounting provider (provider-managed) or by our platform's eFactura service.

---

## Features

### Provider Integration
- Provider-agnostic adapter pattern
- Supported providers: SmartBill, FGO, Exact, Xero, QuickBooks
- External invoice issuance (issue_extern mode)
- OAuth2 and API key authentication
- Connection testing before activation

### Data Mapping
- Mapping wizard for products, taxes, accounts, and series
- Customer sync with automatic creation (ensureCustomer)
- Product sync with tax configuration (ensureProducts)
- Custom field mapping support
- Multi-currency support

### Invoice Processing
- Invoice creation via provider API
- PDF retrieval and delivery
- Credit note generation for refunds
- Prevent duplicate issuance
- Batch invoice processing

### Reliability
- Job queue with retry logic
- Error tracking and dead-letter queue (DLQ)
- Automatic failover handling
- Transaction logging
- Encrypted credential storage

### Compliance
- eFactura integration options
- Provider-managed or platform eFactura
- VAT handling per provider specifications
- Regulatory compliance support

---

## Use Cases

### Automated Bookkeeping
Every ticket sale automatically creates a corresponding invoice in your accounting software. End-of-day reconciliation becomes a simple verification rather than hours of data entry.

### Multi-Provider Setup
Large organizations using different accounting software for different entities can configure separate providers for each tenant while managing everything from one platform.

### Real-Time Financial Visibility
Finance teams see revenue in their accounting software as it happens, enabling real-time reporting and cash flow management.

### Refund Processing
When refunds are issued, credit notes are automatically generated and synced to your accounting software, maintaining accurate financial records.

### Tax Compliance
Proper tax mapping ensures all invoices are issued with correct VAT rates and comply with local tax regulations.

### Audit Trail
Complete synchronization logs provide auditors with clear traceability between platform transactions and accounting records.

---

## Technical Documentation

### Overview

The Accounting Connectors microservice provides automated invoice synchronization between the ticketing platform and external accounting software providers. It uses an adapter pattern for provider-agnostic integration.

### Architecture

```
Platform Invoice → Accounting Service → Provider Adapter → External API
                                     ↓
                              Mapping Engine
                                     ↓
                              Queue Manager
```

### Adapter Interface

Each provider implements the `AccountingAdapterInterface`:

```php
interface AccountingAdapterInterface
{
    public function authenticate(): bool;
    public function testConnection(): bool;
    public function ensureCustomer(Customer $customer): string;
    public function ensureProducts(array $products): array;
    public function createInvoice(Invoice $invoice): InvoiceResponse;
    public function createCreditNote(Invoice $original, array $items): CreditNoteResponse;
    public function getInvoicePdf(string $invoiceId): string;
    public function getInvoiceStatus(string $invoiceId): string;
}
```

### Configuration

```php
'accounting' => [
    'default_provider' => env('ACCOUNTING_PROVIDER', 'smartbill'),
    'providers' => [
        'smartbill' => [
            'api_key' => env('SMARTBILL_API_KEY'),
            'email' => env('SMARTBILL_EMAIL'),
            'company_cif' => env('SMARTBILL_CIF'),
        ],
        'xero' => [
            'client_id' => env('XERO_CLIENT_ID'),
            'client_secret' => env('XERO_CLIENT_SECRET'),
        ],
        // ... other providers
    ],
    'issue_extern' => true,
    'auto_pdf_delivery' => true,
    'retry_attempts' => 3,
    'retry_delay' => 60, // seconds
]
```

### API Endpoints

#### Test Connection
```
POST /api/accounting/test-connection
```
Verify credentials and connectivity with the accounting provider.

#### Sync Customer
```
POST /api/accounting/customers/sync
```
Ensure customer exists in accounting system.

#### Create Invoice
```
POST /api/accounting/invoices
```
Create invoice in external accounting system.

#### Create Credit Note
```
POST /api/accounting/credit-notes
```
Create credit note for refund.

#### Get Invoice PDF
```
GET /api/accounting/invoices/{id}/pdf
```
Retrieve PDF from accounting provider.

#### Get Sync Status
```
GET /api/accounting/sync-status/{jobId}
```
Check status of sync job.

### Mapping Configuration

```json
{
  "products": {
    "ticket_general": "external_product_id_1",
    "ticket_vip": "external_product_id_2"
  },
  "taxes": {
    "standard": "19%",
    "reduced": "9%"
  },
  "accounts": {
    "revenue": "411",
    "receivables": "4111"
  },
  "series": {
    "default": "EPAS",
    "refund": "STORNO"
  }
}
```

### Integration Example

```php
use App\Services\Accounting\AccountingService;

// Get service instance
$accounting = app(AccountingService::class);

// Ensure customer exists
$externalCustomerId = $accounting->ensureCustomer($customer);

// Create invoice
$response = $accounting->createInvoice($invoice, [
    'customer_id' => $externalCustomerId,
    'series' => 'EPAS',
    'send_to_anaf' => true,
]);

// Get PDF
$pdf = $accounting->getInvoicePdf($response->external_id);
```

### Job Queue

Failed operations are queued for retry:

```php
// Queue structure
[
    'job_id' => 'uuid',
    'type' => 'create_invoice',
    'payload' => [...],
    'attempts' => 0,
    'max_attempts' => 3,
    'next_retry_at' => '2025-01-15 10:00:00',
    'error_message' => null,
]
```

### Error Handling

| Error | Description | Action |
|-------|-------------|--------|
| `AUTH_FAILED` | Authentication failed | Check credentials |
| `CUSTOMER_NOT_FOUND` | Customer doesn't exist | Run ensureCustomer |
| `PRODUCT_NOT_MAPPED` | Product not in mapping | Update mapping config |
| `RATE_LIMITED` | API rate limit hit | Automatic retry |
| `PROVIDER_ERROR` | External API error | Check provider status |

### Monitoring

Track synchronization health:
- Invoices synced per hour
- Success/failure rates
- Average sync latency
- Queue depth
- Error rates by type
