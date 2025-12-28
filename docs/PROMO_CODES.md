# Promo Codes / Vouchers Microservice

Comprehensive promo code (voucher/coupon) system for tenants to create and manage discount codes.

## Table of Contents

1. [Features](#features)
2. [Database Structure](#database-structure)
3. [API Endpoints](#api-endpoints)
4. [Usage Examples](#usage-examples)
5. [CLI Commands](#cli-commands)
6. [Integration Guide](#integration-guide)
7. [Configuration](#configuration)

---

## Features

### Discount Types
- **Fixed Amount**: Discount a specific amount (e.g., $10 off)
- **Percentage**: Discount a percentage (e.g., 20% off)
- **Maximum Cap**: Set max discount for percentage codes

### Application Scope
- **Cart Level**: Apply to entire cart/order
- **Event Level**: Apply only to specific event tickets
- **Ticket Type Level**: Apply only to specific ticket types

### Conditions & Limits
- **Minimum Purchase**: Require minimum cart value
- **Minimum Tickets**: Require minimum number of tickets
- **Usage Limits**: Limit total uses and/or per-customer uses
- **Validity Period**: Set start and end dates
- **IP Tracking**: Track where codes are used from

### Management
- **Auto-generation**: Generate random codes or use custom codes
- **Status Management**: Active, Inactive, Expired, Depleted
- **Usage Tracking**: Complete audit trail of all code usage
- **Statistics**: Track revenue impact and usage patterns

---

## Database Structure

### `promo_codes` Table

Stores promo code definitions:

```sql
- id (uuid)
- tenant_id (uuid)
- code (string, unique) - The actual promo code
- name (string) - Internal name
- description (text) - Public description
- type (enum: fixed, percentage)
- value (decimal) - Discount value
- applies_to (enum: cart, event, ticket_type)
- event_id (uuid, nullable)
- ticket_type_id (uuid, nullable)
- min_purchase_amount (decimal, nullable)
- max_discount_amount (decimal, nullable)
- min_tickets (integer, nullable)
- usage_limit (integer, nullable)
- usage_limit_per_customer (integer, nullable)
- usage_count (integer)
- starts_at (timestamp)
- expires_at (timestamp, nullable)
- status (enum: active, inactive, expired, depleted)
- is_public (boolean)
- metadata (json)
- created_by (uuid)
- timestamps
```

### `promo_code_usage` Table

Tracks every use of a promo code:

```sql
- id (uuid)
- promo_code_id (uuid)
- tenant_id (uuid)
- order_id (uuid)
- customer_id (uuid, nullable)
- customer_email (string, nullable)
- original_amount (decimal)
- discount_amount (decimal)
- final_amount (decimal)
- applied_to (json) - Which items got the discount
- notes (text)
- ip_address (string)
- used_at (timestamp)
- timestamps
```

### `orders` Table Extension

Added fields to orders table:

```sql
- promo_code_id (uuid, nullable)
- promo_code (string, nullable)
- promo_discount (decimal)
```

---

## API Endpoints

### Management Endpoints

#### List Promo Codes
```http
GET /api/promo-codes/{tenantId}
```

**Query Parameters:**
- `status` - Filter by status (active, inactive, expired, depleted)
- `type` - Filter by type (fixed, percentage)
- `applies_to` - Filter by application scope
- `event_id` - Filter by event
- `search` - Search by code or name
- `order_by` - Sort field (default: created_at)
- `order_dir` - Sort direction (asc/desc)
- `limit` - Results per page (max: 100)
- `offset` - Pagination offset

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "code": "SUMMER25",
      "name": "Summer Sale",
      "description": "25% off all tickets",
      "type": "percentage",
      "value": 25,
      "applies_to": "cart",
      "min_purchase_amount": 50,
      "max_discount_amount": 100,
      "usage_limit": 1000,
      "usage_count": 347,
      "starts_at": "2025-06-01 00:00:00",
      "expires_at": "2025-08-31 23:59:59",
      "status": "active"
    }
  ]
}
```

#### Create Promo Code
```http
POST /api/promo-codes/{tenantId}
```

**Request Body:**
```json
{
  "code": "WELCOME10",
  "name": "Welcome Discount",
  "description": "$10 off your first order",
  "type": "fixed",
  "value": 10,
  "applies_to": "cart",
  "min_purchase_amount": 50,
  "usage_limit": 500,
  "usage_limit_per_customer": 1,
  "expires_at": "2025-12-31"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Promo code created successfully",
  "data": {
    "id": "uuid",
    "code": "WELCOME10",
    "status": "active",
    ...
  }
}
```

#### Update Promo Code
```http
PUT /api/promo-codes/{id}
```

**Request Body:**
```json
{
  "name": "Updated Name",
  "usage_limit": 1000,
  "expires_at": "2026-01-31"
}
```

#### Deactivate Promo Code
```http
POST /api/promo-codes/{id}/deactivate
```

#### Delete Promo Code
```http
DELETE /api/promo-codes/{id}
```

#### Get Statistics
```http
GET /api/promo-codes/{id}/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "usage_count": 347,
    "usage_limit": 1000,
    "total_discount_given": 8675.50,
    "total_revenue_affected": 34702.00,
    "unique_customers": 325,
    "average_discount": 25.00
  }
}
```

### Public/Customer Endpoints

#### Validate Promo Code
```http
POST /api/promo-codes/{tenantId}/validate
```

**Request Body:**
```json
{
  "code": "SUMMER25",
  "cart": {
    "total": 150.00,
    "items": [
      {
        "id": "item-1",
        "event_id": "event-uuid",
        "ticket_type_id": "ticket-type-uuid",
        "price": 50.00,
        "quantity": 3
      }
    ]
  },
  "customer_id": "customer-uuid"
}
```

**Success Response:**
```json
{
  "success": true,
  "valid": true,
  "promo_code": {
    "id": "uuid",
    "code": "SUMMER25",
    "description": "25% off all tickets",
    "type": "percentage",
    "value": 25
  },
  "discount": {
    "amount": 37.50,
    "formatted": "25% off",
    "applied_to": ["cart"],
    "final_amount": 112.50
  }
}
```

**Validation Failure Response:**
```json
{
  "success": false,
  "valid": false,
  "reason": "Minimum purchase of $50.00 required to use this promo code"
}
```

---

## Usage Examples

### Example 1: Cart-Level Fixed Discount

Create a $20 off code for orders over $100:

```bash
curl -X POST https://api.epas.com/api/promo-codes/tenant-123 \
  -H "Content-Type: application/json" \
  -d '{
    "code": "SAVE20",
    "type": "fixed",
    "value": 20,
    "applies_to": "cart",
    "min_purchase_amount": 100
  }'
```

### Example 2: Event-Specific Percentage Discount

Create a 30% off code for a specific event:

```bash
curl -X POST https://api.epas.com/api/promo-codes/tenant-123 \
  -H "Content-Type: application/json" \
  -d '{
    "code": "CONCERT30",
    "type": "percentage",
    "value": 30,
    "applies_to": "event",
    "event_id": "event-uuid",
    "max_discount_amount": 50
  }'
```

### Example 3: First-Time Customer Discount

Create a one-time use per customer code:

```bash
curl -X POST https://api.epas.com/api/promo-codes/tenant-123 \
  -H "Content-Type: application/json" \
  -d '{
    "code": "FIRST10",
    "type": "percentage",
    "value": 10,
    "applies_to": "cart",
    "usage_limit_per_customer": 1
  }'
```

### Example 4: Limited-Time Flash Sale

Create a code valid for 24 hours with usage limit:

```bash
curl -X POST https://api.epas.com/api/promo-codes/tenant-123 \
  -H "Content-Type: application/json" \
  -d '{
    "code": "FLASH50",
    "type": "percentage",
    "value": 50,
    "applies_to": "cart",
    "usage_limit": 100,
    "starts_at": "2025-12-25 00:00:00",
    "expires_at": "2025-12-25 23:59:59"
  }'
```

---

## CLI Commands

### Create a Promo Code

```bash
php artisan promo:create tenant-123 \
  --code=WELCOME10 \
  --name="Welcome Discount" \
  --type=fixed \
  --value=10 \
  --applies-to=cart \
  --min-amount=50 \
  --usage-limit=500 \
  --expires=2025-12-31
```

**Auto-generated Code:**
```bash
php artisan promo:create tenant-123 \
  --type=percentage \
  --value=20
# Generates random code like: A7K2P9XZ
```

### List Promo Codes

```bash
# List all active codes
php artisan promo:list tenant-123 --status=active

# List fixed amount codes
php artisan promo:list tenant-123 --type=fixed --limit=50

# List all codes
php artisan promo:list tenant-123
```

**Output:**
```
Found 3 promo code(s)

Code       Type        Value   Applies To  Status   Usage     Expires
SUMMER25   Percentage  25%     Cart        Active   347/1000  2025-08-31
WELCOME10  Fixed       10.00   Cart        Active   28/500    2025-12-31
CONCERT30  Percentage  30%     Event       Active   15        Never
```

---

## Integration Guide

### Order/Checkout Integration

#### Step 1: Validate Code Before Checkout

When customer enters a promo code:

```javascript
const response = await fetch('/api/promo-codes/tenant-123/validate', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    code: userEnteredCode,
    cart: {
      total: cartTotal,
      items: cartItems
    },
    customer_id: customerId
  })
});

const result = await response.json();

if (result.valid) {
  // Show discount in UI
  updateCartTotal(result.discount.final_amount);
  showDiscount(result.discount.amount);
} else {
  // Show error message
  showError(result.reason);
}
```

#### Step 2: Apply to Order

When creating the order, include promo code:

```php
$order = Order::create([
    'tenant_id' => $tenantId,
    'customer_id' => $customerId,
    'subtotal' => $cartTotal,
    'promo_code_id' => $promoCodeId,
    'promo_code' => $promoCode,
    'promo_discount' => $discountAmount,
    'total' => $cartTotal - $discountAmount,
    // ... other fields
]);
```

#### Step 3: Record Usage

After successful order:

```php
$promoCodeService->recordUsage($promoCodeId, $order->id, [
    'customer_id' => $customerId,
    'customer_email' => $customerEmail,
    'original_amount' => $cartTotal,
    'discount_amount' => $discountAmount,
    'final_amount' => $finalAmount,
    'applied_to' => $appliedItems,
    'ip_address' => $request->ip(),
]);
```

### Invoice Integration

Include promo code discount in invoice:

```php
$invoice = [
    'subtotal' => $order->subtotal,
    'promo_code' => $order->promo_code,
    'promo_discount' => -$order->promo_discount, // Negative for discount
    'total' => $order->total,
];
```

**Invoice Display:**
```
Subtotal:                    $150.00
Promo Code (SUMMER25):       -$37.50
────────────────────────────────────
Total:                       $112.50
```

---

## Configuration

### Environment Variables

```env
# Promo codes configuration
PROMO_CODES_ENABLED=true
PROMO_CODES_DEFAULT_LENGTH=8
PROMO_CODES_MAX_PERCENTAGE=100
PROMO_CODES_DEFAULT_VALIDITY_DAYS=30
PROMO_CODES_ALLOW_STACKING=false
PROMO_CODES_CLEANUP_DAYS=365
```

### Config File

`config/microservices.php`:

```php
'promo_codes' => [
    'enabled' => env('PROMO_CODES_ENABLED', true),
    'default_code_length' => env('PROMO_CODES_DEFAULT_LENGTH', 8),
    'max_percentage' => env('PROMO_CODES_MAX_PERCENTAGE', 100),
    'default_validity_days' => env('PROMO_CODES_DEFAULT_VALIDITY_DAYS', 30),
    'allow_stacking' => env('PROMO_CODES_ALLOW_STACKING', false),
    'cleanup_expired_after_days' => env('PROMO_CODES_CLEANUP_DAYS', 365),
],
```

---

## Best Practices

### Creating Codes

1. **Use descriptive internal names** for easy management
2. **Set expiration dates** to create urgency
3. **Use minimum purchase amounts** to increase cart values
4. **Limit usage** to prevent abuse
5. **Track per-customer usage** for exclusive offers

### Code Naming

**Good Examples:**
- `WELCOME10` - Clear purpose and value
- `SUMMER2025` - Time-bound campaign
- `VIP50` - Target audience specific
- `EARLYBIRD` - Event-based discount

**Bad Examples:**
- `ABC123` - No meaning
- `DISCOUNT` - Too generic
- `AAAAA` - Unprofessional

### Security

1. **Use uppercase codes** - Easier to read and type
2. **Avoid offensive word combinations** when auto-generating
3. **Monitor unusual usage patterns** (same IP, rapid uses)
4. **Set reasonable max discounts** to prevent revenue loss
5. **Review expired codes regularly**

### Marketing

1. **Create urgency** with time limits
2. **Segment codes** by customer type (new, VIP, etc.)
3. **Track ROI** using statistics endpoint
4. **A/B test** different discount amounts
5. **Combine with email campaigns**

---

## Troubleshooting

### Code Not Working

**Customer says code isn't working:**

1. Check code status: `php artisan promo:list tenant-123 --search=CODE123`
2. Check if expired or depleted
3. Verify cart meets minimum requirements
4. Check event/ticket type applicability
5. Verify customer hasn't exceeded per-customer limit

### High Discount Impact

**Too much discount being given:**

1. Review codes without usage limits
2. Check max_discount_amount on percentage codes
3. Monitor usage statistics regularly
4. Consider deactivating underperforming codes

### Database Performance

**Slow validation queries:**

1. Ensure indexes are created (migration handles this)
2. Monitor `promo_code_usage` table size
3. Run cleanup for old records periodically
4. Consider archiving old usage data

---

## Analytics & Reporting

### Key Metrics

Track these metrics for promo code success:

- **Usage Rate**: `usage_count / usage_limit`
- **Average Discount**: Total discount / usage count
- **Revenue Impact**: Total revenue affected
- **Customer Acquisition**: Unique customers using code
- **ROI**: Revenue vs. discount given

### Report Queries

**Most Used Codes:**
```sql
SELECT code, usage_count, total_discount_given
FROM promo_codes
ORDER BY usage_count DESC
LIMIT 10;
```

**Revenue Impact by Code:**
```sql
SELECT
    pc.code,
    COUNT(pcu.id) as uses,
    SUM(pcu.discount_amount) as total_discount,
    SUM(pcu.original_amount) as revenue_affected
FROM promo_codes pc
LEFT JOIN promo_code_usage pcu ON pc.id = pcu.promo_code_id
GROUP BY pc.id, pc.code
ORDER BY total_discount DESC;
```

---

## Support

For questions or issues with promo codes:
- **Documentation**: https://docs.epas.com/promo-codes
- **API Reference**: https://api.epas.com/docs
- **Support**: support@epas.com
