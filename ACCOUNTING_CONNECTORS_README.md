# Accounting Connectors Microservice

**Price:** 1 EUR/month (recurring)
**Category:** Accounting Integration

## Overview

Integrate with accounting systems (SmartBill, FGO, Exact, Xero, QuickBooks) for external invoice issuance. When `issue_extern=true`, invoices are created in the external accounting system instead of internally.

## Features

- ✅ Provider-agnostic adapter pattern
- ✅ Multiple providers: SmartBill, FGO, Exact, Xero, QuickBooks
- ✅ External invoice issuance
- ✅ Mapping wizard (products, taxes, accounts, series)
- ✅ Customer/product sync
- ✅ PDF retrieval and delivery
- ✅ Credit note generation
- ✅ Job queue with retry logic
- ✅ Encrypted credentials

## Installation

```bash
php artisan migrate
php artisan db:seed --class=AccountingConnectorsMicroserviceSeeder
```

## Usage

### 1. Connect to Provider

```bash
POST /api/acc/connect
{
  "tenant": "tenant-123",
  "provider": "smartbill",
  "credentials": {
    "api_key": "xxx",
    "api_secret": "yyy"
  },
  "settings": {
    "issue_extern": true,
    "default_series": "FACT"
  }
}
```

### 2. Create Mappings

```bash
POST /api/acc/map
{
  "tenant": "tenant-123",
  "entity": "product",
  "local_ref": "ticket-vip",
  "remote_ref": "PROD-001",
  "meta": {
    "remote_name": "Bilet VIP"
  }
}
```

### 3. Issue Invoice

```bash
POST /api/acc/issue
{
  "tenant": "tenant-123",
  "order_ref": "order-456",
  "customer": {
    "name": "John Doe",
    "email": "john@example.com",
    "vat_number": "RO12345678"
  },
  "lines": [
    {
      "product_name": "VIP Ticket",
      "quantity": 2,
      "unit_price": 100,
      "tax_rate": 19
    }
  ],
  "totals": {
    "subtotal": 200,
    "tax": 38,
    "total": 238
  }
}
```

### 4. Create Credit Note

```bash
POST /api/acc/credit
{
  "tenant": "tenant-123",
  "invoice_external_ref": "INV-2025-001",
  "refund_payload": {
    "amount": 238,
    "reason": "Customer requested refund"
  }
}
```

## Custom Adapter

```php
namespace App\Services\Accounting\Adapters;

class SmartBillAdapter implements AccountingAdapterInterface
{
    public function createInvoice(array $invoice): array
    {
        // Call SmartBill API
        $response = Http::post('https://api.smartbill.ro/invoices', $invoice);

        return [
            'external_ref' => $response['id'],
            'invoice_number' => $response['number'],
            'details' => $response,
        ];
    }

    // Implement other methods...
}
```

Register:
```php
$accountingService->registerAdapter('smartbill', new SmartBillAdapter());
```

## Webhook Integration

```php
// On payment captured
if ($connector->settings['issue_extern']) {
    $accountingService->issueInvoice($tenantId, $orderId, $invoiceData);
}
```

© 2025 EPAS. All rights reserved.
