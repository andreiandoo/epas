# Affiliate Tracking & Commissions Microservice

## Overview

A comprehensive affiliate tracking system with last-click attribution, commission management, and detailed reporting. This microservice can be purchased for a one-time payment of 10 EUR and activated per tenant.

## Features

- **Last-click attribution** with configurable 90-day cookie window
- **Dual tracking methods**: Link-based and coupon-based tracking
- **Flexible commission rates**: Percentage or fixed amount
- **Automatic deduplication** of conversions
- **Self-purchase guard** to prevent fraud
- **Detailed analytics** and reporting dashboards
- **CSV export** for accounting purposes
- **Conversion status management**: pending, approved, reversed
- **Real-time tracking** of clicks and conversions

## Database Schema

### Tables

1. **affiliates** - Affiliate information
   - `id`, `tenant_id`, `code`, `name`, `contact_email`, `status`, `meta`

2. **affiliate_links** - Tracking links
   - `id`, `affiliate_id`, `slug`, `code`, `landing_url`, `meta`

3. **affiliate_coupons** - Coupon codes assigned to affiliates
   - `id`, `affiliate_id`, `coupon_code`, `active`

4. **affiliate_conversions** - Conversion records
   - `id`, `tenant_id`, `affiliate_id`, `order_ref`, `amount`, `commission_value`, `commission_type`, `status`, `attributed_by`, `click_ref`, `meta`

5. **affiliate_clicks** - Click tracking (optional analytics)
   - `id`, `affiliate_id`, `tenant_id`, `ip_hash`, `user_agent`, `referer`, `landing_url`, `utm_params`, `clicked_at`

## Installation

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Seed the Microservice

```bash
php artisan db:seed --class=AffiliateTrackingMicroserviceSeeder
```

This will create the "Affiliate Tracking & Commissions" microservice in your database with a price of 10.00 EUR.

### 3. Activate for a Tenant

In the admin panel:
1. Go to **Tenants** > Select a tenant
2. Attach the "Affiliate Tracking" microservice
3. Configure the microservice settings:
   - Cookie duration (default: 90 days)
   - Commission type (percent or fixed)
   - Commission value
   - Enable/disable self-purchase guard

## Configuration

The microservice configuration is stored in the `tenant_microservice` pivot table. Example configuration:

```json
{
  "cookie_name": "aff_ref",
  "cookie_duration_days": 90,
  "commission_type": "percent",
  "commission_value": 10.00,
  "self_purchase_guard": true,
  "exclude_taxes_from_commission": true
}
```

## API Endpoints

### Track Click

```http
POST /api/affiliates/track-click
Content-Type: application/json

{
  "tenant_id": 1,
  "affiliate_code": "PARTNER123",
  "url": "https://example.com/event/concert",
  "utm": {
    "source": "facebook",
    "medium": "social",
    "campaign": "summer2025"
  }
}
```

**Response:**
```json
{
  "success": true,
  "click_id": 456,
  "affiliate_code": "PARTNER123",
  "cookie_name": "aff_ref",
  "cookie_duration_days": 90,
  "cookie_value": "{\"affiliate_code\":\"PARTNER123\",\"click_id\":456,\"timestamp\":1234567890}"
}
```

### Attribute Order (Preview)

```http
POST /api/affiliates/attribute-order
Content-Type: application/json

{
  "tenant_id": 1,
  "order_amount": 100.00,
  "coupon_code": "SAVE10",
  "cookie_value": "{...}"
}
```

### Confirm Order (Create Conversion)

```http
POST /api/affiliates/confirm-order
Content-Type: application/json

{
  "tenant_id": 1,
  "order_ref": "ORD-2025-001",
  "order_amount": 100.00,
  "buyer_email": "customer@example.com",
  "coupon_code": "SAVE10",
  "cookie_value": "{...}"
}
```

### Approve Conversion (Payment Captured)

```http
POST /api/affiliates/approve-order
Content-Type: application/json

{
  "tenant_id": 1,
  "order_ref": "ORD-2025-001"
}
```

### Reverse Conversion (Refund/Chargeback)

```http
POST /api/affiliates/reverse-order
Content-Type: application/json

{
  "tenant_id": 1,
  "order_ref": "ORD-2025-001"
}
```

### Create Affiliate

```http
POST /api/affiliates
Content-Type: application/json

{
  "tenant_id": 1,
  "code": "PARTNER123",
  "name": "Partner Company",
  "contact_email": "partner@example.com",
  "coupon_code": "PARTNER10"
}
```

### Get Affiliate Dashboard

```http
GET /api/affiliates/{id}/dashboard?start_date=2025-01-01&end_date=2025-12-31
```

### Get Affiliate Stats (Self-Service)

```http
GET /api/affiliate/me?affiliate_code=PARTNER123&start_date=2025-01-01&end_date=2025-12-31
```

### Export Conversions to CSV

```http
GET /api/affiliates/{id}/export?start_date=2025-01-01&end_date=2025-12-31
```

## Admin Panel Features

### Microservice Management

- **View Microservice**: See all details about the affiliate tracking microservice
- **Edit Pricing**: Change the price of the microservice
- **View Tenants**: List all tenants using this microservice

### Affiliate Management (Tenant Dashboard)

Navigate to **Marketing** > **Affiliates** to:

1. **Create Affiliates**: Add new affiliate partners
2. **Manage Coupons**: Assign coupon codes to affiliates
3. **Create Tracking Links**: Generate custom tracking links
4. **View Statistics**: See detailed conversion and commission data
5. **Export Reports**: Download CSV files for accounting

### Affiliate Statistics Page

For each affiliate, view:
- Total conversions
- Approved/pending/reversed conversions
- Total commission earned
- Pending commission
- Total sales generated
- Conversion history with filters

## Business Logic

### Attribution Priority

1. **Coupon Code** (highest priority) - If a valid affiliate coupon is used
2. **Last-Click Link** - If affiliate cookie exists and is within the attribution window

### Deduplication

Only one conversion per `order_ref` is allowed. Duplicate attempts will return the existing conversion.

### Self-Purchase Guard

If enabled, conversions where `buyer_email` matches `affiliate.contact_email` are rejected.

### Commission Calculation

- **Percentage**: `commission = (order_amount * commission_value) / 100`
- **Fixed**: `commission = commission_value` (per order)

### Conversion Lifecycle

1. **Pending**: Created when order is placed
2. **Approved**: Set when payment is captured
3. **Reversed**: Set on refund or chargeback

## Frontend Integration Example

```html
<!-- Track affiliate click -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const affCode = urlParams.get('aff');

  if (affCode) {
    fetch('/api/affiliates/track-click', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        tenant_id: {{ $tenantId }},
        affiliate_code: affCode,
        url: window.location.href,
        utm: {
          source: urlParams.get('utm_source'),
          medium: urlParams.get('utm_medium'),
          campaign: urlParams.get('utm_campaign')
        }
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        // Set cookie
        document.cookie = `${data.cookie_name}=${data.cookie_value}; max-age=${data.cookie_duration_days * 86400}; path=/`;
      }
    });
  }
});
</script>
```

## Testing

Run the test suite:

```bash
php artisan test --filter=AffiliateTrackingTest
```

Tests cover:
- Click tracking
- Order attribution
- Conversion creation
- Deduplication
- Self-purchase guard
- Status transitions
- Statistics calculation

## Security Considerations

- IP addresses are hashed using SHA-256
- Rate limiting on API endpoints
- Input validation on all endpoints
- Self-purchase detection
- Unique constraints on conversions

## Support & Customization

For custom requirements or issues, please contact the development team.

## License

Proprietary - Part of the EPAS platform
