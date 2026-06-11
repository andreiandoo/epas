# eFactura (RO) Microservice

**Version:** 1.0.0
**Pricing:** 3 EUR/month (recurring)
**Category:** Compliance / Tax Automation

## Overview

The eFactura microservice provides automatic submission of invoices to the Romanian ANAF SPV (Sistemul Privat Virtual) system. It handles the complete workflow:

1. Transform internal invoices to ANAF-compliant XML format (UBL or CII)
2. Sign and package invoices with digital certificates
3. Submit to ANAF SPV
4. Poll for acceptance/rejection status
5. Handle retries with exponential backoff
6. Store responses and ANAF receipts

## Key Features

- **Automatic XML Transformation**: Converts internal invoice structure to eFactura format (UBL 2.1 or CII)
- **Digital Signature**: XMLDSig or PKCS#7 signing with per-tenant certificates
- **Queue Management**: Idempotent submission with retry logic
- **Status Tracking**: queued → submitted → accepted/rejected workflow
- **Error Diagnostics**: Detailed ANAF error messages and validation failures
- **Retry Logic**: Exponential backoff (5min, 15min, 30min, 1h, 2h)
- **Audit Trail**: Complete logging of all submissions and responses
- **Multi-Tenant**: Isolated credentials and processing per tenant
- **Provider-Agnostic**: Adapter pattern allows custom ANAF integrations

## Database Schema

### `anaf_queue` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `tenant_id` | string | Tenant identifier |
| `invoice_id` | bigint | Internal invoice ID |
| `payload_ref` | string | Path to stored XML payload |
| `status` | enum | queued, submitted, accepted, rejected, error |
| `error_message` | text | Error details if rejected/failed |
| `anaf_ids` | json | Remote IDs, download_id, confirmation_number |
| `attempts` | int | Number of submission attempts |
| `max_attempts` | int | Maximum retry limit (default: 5) |
| `last_attempt_at` | timestamp | Last submission attempt |
| `next_retry_at` | timestamp | When to retry (exponential backoff) |
| `response_data` | json | Full ANAF response for audit |
| `xml_hash` | string | SHA256 hash of submitted XML |
| `submitted_at` | timestamp | When submitted to ANAF |
| `accepted_at` | timestamp | When accepted by ANAF |
| `rejected_at` | timestamp | When rejected by ANAF |

**Indexes:**
- `(tenant_id, invoice_id)` - Unique constraint for idempotency
- `(status, next_retry_at)` - For retry queue processing
- `(tenant_id, status)` - For tenant statistics

## API Endpoints

### 1. Submit Invoice to eFactura Queue

**POST** `/api/efactura/submit`

Queue an invoice for automatic submission to ANAF. Idempotent by `(tenant, invoice_id)`.

**Request:**
```json
{
  "tenant": "tenant_123",
  "invoice_id": 456,
  "invoice": {
    "invoice_number": "FAC-2025-001",
    "issue_date": "2025-11-16",
    "seller": {
      "name": "SC Example SRL",
      "vat_id": "RO12345678",
      "address": "Str. Exemplu 1, București"
    },
    "buyer": {
      "name": "SC Client SRL",
      "vat_id": "RO87654321",
      "address": "Str. Client 5, Cluj-Napoca"
    },
    "lines": [
      {
        "description": "Bilet Concert",
        "quantity": 2,
        "unit_price": 150.00,
        "vat_rate": 19,
        "total": 300.00
      }
    ],
    "total": 300.00,
    "vat_total": 47.90,
    "grand_total": 347.90
  }
}
```

**Response:**
```json
{
  "success": true,
  "queue_id": 1,
  "status": "queued",
  "message": "Invoice queued for eFactura submission"
}
```

### 2. Manual Retry

**POST** `/api/efactura/retry`

Manually retry a failed submission.

**Request:**
```json
{
  "queue_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "remote_id": "ANAF-ABC123XYZ",
  "message": "Submitted to ANAF"
}
```

### 3. Poll Status

**POST** `/api/efactura/poll`

Poll ANAF for the current status of a submitted invoice.

**Request:**
```json
{
  "queue_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "status": "accepted",
  "message": "Invoice accepted by ANAF"
}
```

### 4. Get Queue Entry Status

**GET** `/api/efactura/status/{queueId}`

Get detailed status of a queue entry.

**Response:**
```json
{
  "success": true,
  "queue_id": 1,
  "status": "accepted",
  "invoice_id": 456,
  "anaf_ids": {
    "remote_id": "ANAF-ABC123XYZ",
    "download_id": "DWN-ABC123",
    "confirmation_number": "CONF-12345678"
  },
  "attempts": 1,
  "max_attempts": 5,
  "submitted_at": "2025-11-16T10:00:00Z",
  "accepted_at": "2025-11-16T10:05:00Z",
  "created_at": "2025-11-16T09:50:00Z"
}
```

### 5. Download ANAF Receipt

**GET** `/api/efactura/download/{queueId}`

Download the ANAF receipt/confirmation PDF.

**Response:** PDF file download

### 6. Get Statistics

**GET** `/api/efactura/stats/{tenantId}`

Get eFactura statistics for a tenant.

**Response:**
```json
{
  "success": true,
  "tenant_id": "tenant_123",
  "stats": {
    "queued": 5,
    "submitted": 3,
    "accepted": 20,
    "rejected": 2,
    "error": 1
  }
}
```

### 7. List Queue Entries

**GET** `/api/efactura/queue/{tenantId}?status=error&limit=50`

List queue entries for a tenant with optional filtering.

**Query Parameters:**
- `status` (optional): Filter by status (queued, submitted, accepted, rejected, error)
- `limit` (optional): Max entries to return (default: 50, max: 200)
- `offset` (optional): Pagination offset (default: 0)

**Response:**
```json
{
  "success": true,
  "entries": [
    {
      "queue_id": 5,
      "invoice_id": 460,
      "status": "error",
      "error_message": "Connection timeout to ANAF SPV",
      "attempts": 2,
      "created_at": "2025-11-16T09:00:00Z"
    }
  ]
}
```

## Adapter Interface

The `AnafAdapterInterface` provides a vendor-agnostic abstraction for ANAF integration.

### Methods

```php
interface AnafAdapterInterface {
    // Authenticate with ANAF credentials
    public function authenticate(array $credentials): array;

    // Build eFactura XML from invoice data
    public function buildXml(array $invoice): array;

    // Sign and package XML
    public function signAndPackage(string $xml, array $signingConfig = []): array;

    // Submit to ANAF SPV
    public function submit(string $package, array $metadata = []): array;

    // Poll submission status
    public function poll(string $remoteId): array;

    // Download receipt/confirmation
    public function download(string $downloadId): array;

    // Test connection to ANAF
    public function testConnection(): array;

    // Get adapter metadata
    public function getMetadata(): array;
}
```

### Custom Adapter Implementation

To implement a custom ANAF adapter (e.g., using a third-party library or official API):

```php
namespace App\Services\EFactura\Adapters;

class RealAnafAdapter implements AnafAdapterInterface {

    public function buildXml(array $invoice): array {
        // Use UBL/CII library to generate compliant XML
        $ublDocument = new \Einvoicing\Invoice();
        $ublDocument->setNumber($invoice['invoice_number']);
        // ... map all fields

        $xml = $ublDocument->toXML();

        return [
            'success' => true,
            'xml' => $xml,
            'hash' => hash('sha256', $xml),
            'errors' => []
        ];
    }

    public function signAndPackage(string $xml, array $signingConfig): array {
        // Use certificate to sign XML
        $cert = openssl_x509_read($signingConfig['certificate']);
        $privateKey = openssl_pkey_get_private($signingConfig['private_key']);

        // Sign with XMLDSig
        $signedXml = $this->signXmlWithDSig($xml, $cert, $privateKey);

        return [
            'success' => true,
            'package' => $signedXml,
            'message' => 'Signed successfully'
        ];
    }

    public function submit(string $package, array $metadata): array {
        // Call ANAF API
        $response = Http::post('https://api.anaf.ro/spv/submit', [
            'xml' => base64_encode($package)
        ]);

        return [
            'success' => $response->successful(),
            'remote_id' => $response->json('id'),
            'download_id' => $response->json('download_id'),
            'message' => 'Submitted to ANAF',
            'submitted_at' => now()->toIso8601String()
        ];
    }
}
```

**Register the adapter:**

```php
// In a service provider
$eFacturaService->registerAdapter('real', new RealAnafAdapter());
```

## Workflow

### 1. Automatic Submission on Invoice Creation

When an invoice is created in your system:

```php
use App\Services\EFactura\EFacturaService;

// After creating invoice
$eFacturaService = app(EFacturaService::class);

$result = $eFacturaService->queueInvoice(
    tenantId: 'tenant_123',
    invoiceId: 456,
    invoiceData: $invoice->toArray()
);

// Invoice is now queued for automatic submission
```

### 2. Background Job Processing

Set up a scheduled job to process the queue:

```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule): void {
    // Process queued submissions every 5 minutes
    $schedule->call(function () {
        $service = app(EFacturaService::class);
        $service->processQueue(limit: 10);
    })->everyFiveMinutes();

    // Poll submitted invoices for status updates every 10 minutes
    $schedule->call(function () {
        $service = app(EFacturaService::class);
        $service->pollPending(limit: 20);
    })->everyTenMinutes();
}
```

### 3. Status Workflow

```
[Invoice Created]
       ↓
[Queue Entry: queued] ← next_retry_at = now
       ↓
[Background Job] → buildXml → signAndPackage → submit
       ↓
[Queue Entry: submitted] ← remote_id stored
       ↓
[Background Job] → poll(remote_id)
       ↓
   ┌───┴───┐
   ↓       ↓
[accepted] [rejected]
   ↓       ↓
 ✓ Done  ✗ Error logged
```

### 4. Error Handling

If submission fails (network error, ANAF downtime):

```
[Queue Entry: error] ← error_message stored
       ↓
  next_retry_at = now + backoff
       ↓
[Wait for backoff period]
       ↓
[Background Job] → retry submission
```

**Backoff schedule:**
- Attempt 1: Retry after 5 minutes
- Attempt 2: Retry after 15 minutes
- Attempt 3: Retry after 30 minutes
- Attempt 4: Retry after 1 hour
- Attempt 5: Retry after 2 hours
- Max attempts reached: Manual intervention required

## Configuration

### Tenant Credentials

Store ANAF credentials per tenant in `tenant_configs` table:

```php
DB::table('tenant_configs')->insert([
    'tenant_id' => 'tenant_123',
    'key' => 'efactura_credentials',
    'value' => Crypt::encryptString(json_encode([
        'certificate' => file_get_contents('/path/to/cert.pem'),
        'private_key' => file_get_contents('/path/to/key.pem'),
        'password' => 'cert-password',
        'api_key' => 'optional-api-key' // If using API-based adapter
    ]))
]);
```

### Signing Configuration

```php
DB::table('tenant_configs')->insert([
    'tenant_id' => 'tenant_123',
    'key' => 'efactura_signing',
    'value' => Crypt::encryptString(json_encode([
        'certificate' => 'base64-encoded-cert',
        'alias' => 'my-company-cert',
        'method' => 'XMLDSig' // or 'PKCS7'
    ]))
]);
```

## Security

- **Encrypted Storage**: All certificates and credentials are encrypted using Laravel's `Crypt` facade
- **Multi-Tenant Isolation**: Each tenant's credentials are isolated and cannot be accessed by other tenants
- **Audit Logging**: All submissions and responses are logged for compliance
- **No Sensitive Data in Logs**: Error logs exclude sensitive invoice details
- **Signed URLs**: Download endpoints use signed URLs for secure access

## Testing

The `MockAnafAdapter` simulates ANAF behavior for development/testing:

```php
use App\Services\EFactura\Adapters\MockAnafAdapter;

$eFacturaService->registerAdapter('mock', new MockAnafAdapter());

// Submission will succeed 80% of the time, reject 20%
// Poll will return final status (accepted/rejected)
```

## Monitoring & Diagnostics

### Export Errors to CSV

```php
$errors = AnafQueue::forTenant('tenant_123')
    ->whereIn('status', ['rejected', 'error'])
    ->get();

$csv = "Invoice ID,Status,Error,Attempts,Created At\n";

foreach ($errors as $queue) {
    $csv .= "{$queue->invoice_id},{$queue->status},{$queue->error_message},{$queue->attempts},{$queue->created_at}\n";
}

return response($csv)
    ->header('Content-Type', 'text/csv')
    ->header('Content-Disposition', 'attachment; filename="efactura-errors.csv"');
```

### Dashboard Metrics

```php
$stats = $eFacturaService->getStats('tenant_123');

// Display:
// - Queued: 5
// - Submitted: 3 (awaiting ANAF response)
// - Accepted: 20 (✓ successful)
// - Rejected: 2 (✗ validation errors)
// - Error: 1 (⚠ technical failures)
```

## Troubleshooting

### Issue: Invoice stuck in "submitted" status

**Cause:** ANAF SPV is slow to process, or polling job is not running.

**Solution:**
- Check that the polling job is scheduled and running
- Manually poll: `POST /api/efactura/poll` with `queue_id`
- Check ANAF SPV status page for outages

### Issue: "Max retry attempts reached"

**Cause:** ANAF consistently rejects the invoice or network issues persist.

**Solution:**
- Review `error_message` for validation errors
- Fix invoice data and resubmit as new queue entry
- Check ANAF credentials are valid
- Verify network connectivity to ANAF SPV

### Issue: "Certificate required for signing"

**Cause:** Signing configuration not set for tenant.

**Solution:**
- Ensure `efactura_signing` config exists in `tenant_configs`
- Verify certificate is valid and not expired
- Check certificate password is correct

## Support

For issues or questions about the eFactura microservice:
- Review this documentation
- Check Laravel logs for detailed error messages
- Verify ANAF credentials and certificates
- Contact ANAF support for SPV-specific issues

## Changelog

### v1.0.0 (2025-11-16)
- Initial release
- ANAF SPV integration with adapter pattern
- Queue management with retry logic
- Status polling and workflow tracking
- Mock adapter for testing
- 7 API endpoints
- Comprehensive audit logging
