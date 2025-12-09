# eFactura (RO)

## Short Presentation

Navigate Romanian tax compliance with ease using our eFactura integration. As electronic invoicing becomes mandatory for businesses operating in Romania, this service ensures you stay compliant without the technical headaches.

Our eFactura service automatically transforms your platform invoices into the required UBL/CII XML format, digitally signs them, and submits them directly to ANAF SPV (the Romanian National Tax Agency's electronic system). No manual intervention required.

The intelligent queue management system handles the entire submission lifecycle. Invoices are queued, submitted, and monitored for acceptance or rejection. If ANAF's system is temporarily unavailable, our fallback queue ensures no invoice is lost. Exponential backoff retry logic handles transient failures gracefully.

Security is paramount. Your digital certificates are stored with encryption, and multi-tenant credential isolation ensures your data remains private. Complete audit logs provide full traceability for regulatory compliance.

Track everything from a comprehensive dashboard: submission status, acceptance rates, error diagnostics, and real-time statistics. Export error reports to CSV for easy resolution. Download ANAF receipts and confirmations directly from the platform.

Whether you're issuing hundreds or thousands of invoices, eFactura scales with your business while keeping you compliant with Romanian tax regulations.

---

## Detailed Description

The eFactura microservice provides automated electronic invoice submission to Romania's National Agency for Fiscal Administration (ANAF) through their SPV (Virtual Private Space) system. This service is essential for businesses operating in Romania where electronic invoicing is becoming mandatory across all sectors.

### Automated Compliance

When an invoice is generated on your platform, the eFactura service takes over. It transforms the invoice data into the required XML format (UBL 2.1 or CII), applies digital signatures, packages the document according to ANAF specifications, and submits it to the SPV system.

### Intelligent Queue Management

The service implements a sophisticated queue management system that ensures reliable delivery:

1. **Queued**: Invoice is transformed and ready for submission
2. **Submitted**: Successfully sent to ANAF, awaiting response
3. **Accepted**: ANAF has accepted the invoice
4. **Rejected**: ANAF has rejected the invoice (with detailed error information)
5. **Error**: Transient error occurred, scheduled for retry

### Error Handling

When submissions fail, the system automatically retries with exponential backoff (5min, 15min, 30min, 1h, 2h). Detailed error messages from ANAF are captured and displayed, allowing quick identification and resolution of issues.

### Security & Isolation

Each tenant's ANAF credentials and digital certificates are stored with encryption and isolated from other tenants. The system supports both test and production environments, allowing you to validate your setup before going live.

### Idempotent Submissions

The service uses XML hashing to prevent duplicate submissions. If the same invoice is submitted twice, the system recognizes it and returns the existing submission status rather than creating a duplicate entry in ANAF's system.

---

## Features

### Invoice Processing
- Automatic invoice transformation to eFactura XML (UBL 2.1/CII format)
- Digital signature and packaging according to ANAF specifications
- Automatic submission to ANAF SPV
- Idempotent submission preventing duplicates
- Support for invoice corrections and cancellations

### Queue Management
- Queue management with intelligent retry logic
- Status workflow: queued → submitted → accepted/rejected
- Exponential backoff retry schedule (5min, 15min, 30min, 1h, 2h)
- Maximum 5 retry attempts with configurable limits
- Fallback queue for SPV downtime

### Status Tracking
- Status polling with exponential backoff
- Real-time status updates via webhooks
- Download ANAF receipts and confirmations
- Error tracking and diagnostics
- Real-time statistics dashboard

### Security
- Provider-agnostic adapter pattern
- Encrypted certificate storage
- Multi-tenant credential isolation
- Comprehensive audit logs
- Test/production environment switching

### Reporting
- CSV export of errors
- Submission history and analytics
- Success/failure rate tracking
- Processing time metrics

---

## Use Cases

### Event Ticketing Platforms
Automatically submit all ticket sale invoices to ANAF. Handle high-volume sales during popular events while maintaining 100% compliance.

### Multi-Event Organizers
Manage eFactura submissions across multiple events and invoice series. Each event can have its own invoice numbering while maintaining centralized compliance.

### Corporate Ticket Sales (B2B)
Issue compliant invoices to corporate clients purchasing tickets in bulk. Include all required B2B fields automatically.

### Subscription-Based Services
Handle recurring invoices for season passes, memberships, or subscription packages with automatic monthly submissions.

### Refund Processing
When refunds are issued, automatically generate and submit the required credit notes (facturi de stornare) to ANAF.

### Multi-Entity Operations
Manage eFactura compliance for multiple legal entities from a single platform, each with their own certificates and credentials.

---

## Technical Documentation

### Overview

The eFactura microservice automates the submission of electronic invoices to ANAF SPV (Romanian tax authority). It handles XML generation, digital signing, submission, status polling, and error management.

### Architecture

The service uses an adapter pattern to abstract ANAF API communication. This allows for easy testing with mock adapters and potential support for third-party eFactura providers.

### Database Schema

| Table | Description |
|-------|-------------|
| `anaf_queue` | Invoice submission queue with status tracking |

#### Queue Table Fields

| Field | Type | Description |
|-------|------|-------------|
| `tenant_id` | string | Tenant identifier |
| `invoice_id` | integer | Reference to original invoice |
| `payload_ref` | string | Storage path for XML file |
| `status` | enum | queued, submitted, accepted, rejected, error |
| `anaf_ids` | json | ANAF response identifiers |
| `error_message` | text | Error description if failed |
| `response_data` | json | Full ANAF response |
| `attempts` | integer | Number of submission attempts |
| `max_attempts` | integer | Maximum retry attempts |
| `xml_hash` | string | SHA-256 hash for idempotency |
| `submitted_at` | timestamp | When submitted to ANAF |
| `accepted_at` | timestamp | When accepted by ANAF |
| `rejected_at` | timestamp | When rejected by ANAF |
| `next_retry_at` | timestamp | Scheduled retry time |

### API Endpoints

#### Submit Invoice
```
POST /api/efactura/submit
```
Queue an invoice for eFactura submission.

**Request Body:**
```json
{
  "invoice_id": 1001,
  "force": false
}
```

#### Retry Failed Submission
```
POST /api/efactura/retry
```
Manually retry a failed submission.

#### Poll Status
```
POST /api/efactura/poll
```
Trigger status polling for submitted invoices.

#### Get Status
```
GET /api/efactura/status/{queueId}
```
Get current status of a specific submission.

#### Download Receipt
```
GET /api/efactura/download/{queueId}
```
Download ANAF receipt/confirmation PDF.

#### Get Statistics
```
GET /api/efactura/stats/{tenantId}
```
Get submission statistics and analytics.

#### List Queue
```
GET /api/efactura/queue/{tenantId}
```
List all queue entries with filtering options.

### Status Workflow

```
queued → submitted → accepted
                  ↘ rejected
                  ↘ error → (retry) → submitted
```

### Configuration

```php
'efactura' => [
    'environment' => env('ANAF_ENV', 'test'), // test or production
    'max_retries' => 5,
    'backoff_schedule' => [5, 15, 30, 60, 120], // minutes
    'poll_interval' => 300, // seconds
    'storage_path' => 'efactura/{tenant_id}/{invoice_id}.xml',
    'supported_formats' => ['UBL 2.1', 'CII'],
    'signing_methods' => ['XMLDSig', 'PKCS#7'],
]
```

### Integration Example

```php
use App\Services\EFactura\EFacturaService;

// Submit invoice to eFactura
$efactura = app(EFacturaService::class);
$queueEntry = $efactura->submit($invoice);

// Check status
$status = $efactura->getStatus($queueEntry->id);

// Download receipt after acceptance
if ($status === 'accepted') {
    $receipt = $efactura->downloadReceipt($queueEntry->id);
}
```

### XML Generation

The service generates XML conforming to ANAF's eFactura specification:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
  <cbc:ID>INV-2025-001</cbc:ID>
  <cbc:IssueDate>2025-01-15</cbc:IssueDate>
  <!-- ... -->
</Invoice>
```

### Error Codes

| Code | Description | Action |
|------|-------------|--------|
| `INVALID_VAT` | Invalid VAT number format | Verify customer VAT |
| `MISSING_FIELD` | Required field missing | Check invoice data |
| `SIGNATURE_ERROR` | Digital signature invalid | Check certificate |
| `SPV_UNAVAILABLE` | ANAF system down | Automatic retry |
| `DUPLICATE` | Invoice already submitted | Check existing entry |

### Monitoring

The service exposes metrics for monitoring:
- Submissions per hour/day
- Acceptance rate
- Average processing time
- Error rate by type
- Queue depth
