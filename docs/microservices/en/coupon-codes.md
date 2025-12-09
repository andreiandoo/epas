# Coupon Codes

## Short Presentation

Supercharge your sales with Coupon Codes. Strategic discounts drive ticket sales, reward loyal customers, and fill seats for undersold shows. Our comprehensive coupon system gives you the power and flexibility to run promotions that deliver results.

Create campaigns with precision. Generate thousands of unique codes in seconds, each trackable individually. Or create simple universal codes everyone can use. Set percentage discounts, fixed amounts, free shipping, or buy-X-get-Y offers that match your promotion strategy.

Control every aspect of your promotions. Limit how many times each code can be used - once per customer, ten times total, or unlimited. Set minimum purchase requirements and maximum discount caps. Restrict codes to specific events, ticket types, or customer segments.

Scheduling is built-in. Set start and end dates for time-limited campaigns. Run flash sales, early-bird promotions, or holiday specials without manual intervention.

Real-time validation ensures codes are verified instantly at checkout. Invalid, expired, or already-used codes are rejected immediately with clear messaging. Customers know exactly why a code didn't work.

Track everything. See which codes are used, by whom, and for what purchases. Analyze campaign performance with detailed reports. Export data for deeper analysis or accounting purposes.

Coupon codes turn marketing ideas into measurable results. Run smarter promotions that grow your business.

---

## Detailed Description

The Coupon Codes microservice provides a complete promotional code management system for event ticketing. It handles campaign creation, code generation, validation, redemption tracking, and performance analytics.

### Campaign Types

- **Percentage Discount**: Reduce prices by a percentage (e.g., 20% off)
- **Fixed Amount**: Subtract a fixed value (e.g., €10 off)
- **Free Shipping**: Waive delivery fees
- **Buy-X-Get-Y**: Bundle discounts (e.g., buy 3 get 1 free)

### Code Generation

The system supports multiple code formats:
- **Alphanumeric**: Mixed letters and numbers (ABC123)
- **Numeric**: Numbers only (123456)
- **Alphabetic**: Letters only (SUMMER)
- **Custom**: User-defined patterns with prefix/suffix

Bulk generation creates thousands of unique codes in seconds, each with its own tracking history.

### Validation Rules

Codes can be restricted by:
- Usage limits (per user, total uses)
- Minimum/maximum order value
- Specific products or categories
- Customer segments or first-time buyers
- Date ranges and times
- Geographic restrictions

### Redemption Tracking

Every redemption is logged with:
- Customer information
- Order details
- Discount amount applied
- Timestamp and metadata

This enables detailed attribution and ROI calculation for each campaign.

---

## Features

### Campaign Management
- Campaign management with scheduling
- Bulk code generation (thousands of codes)
- Custom code formats (alphanumeric, numeric, custom)
- Code prefixes and suffixes
- Campaign activation/deactivation

### Discount Types
- Percentage discounts
- Fixed amount discounts
- Free shipping promotions
- Buy-X-get-Y offers
- Combinable discount rules

### Usage Controls
- Per-user usage limits
- Total usage limits
- First purchase only option
- Minimum purchase requirements
- Maximum discount caps

### Targeting
- Product/category targeting
- Product/category exclusions
- Event-specific codes
- Customer segment restrictions
- Geographic targeting

### Validation & Redemption
- Real-time code validation
- Redemption tracking
- Validation attempt logging
- Redemption reversal support
- Prevent duplicate usage

### Analytics & Export
- Campaign analytics and reporting
- Code export (CSV/JSON)
- Assign codes to specific users
- Performance dashboards

---

## Use Cases

### Early Bird Discounts
Reward customers who book early with percentage discounts. Drive early sales momentum and better predict attendance.

### Last-Minute Sales
Fill empty seats with flash sale codes distributed via email or social media. Time-limited urgency drives quick decisions.

### Partner & Affiliate Codes
Create unique codes for partners, influencers, or affiliates. Track exactly which partnerships drive sales.

### Customer Loyalty
Reward repeat customers with exclusive codes. Send personalized discounts based on purchase history.

### Corporate Sales
Provide company-specific codes for B2B clients. Track corporate purchases and offer volume discounts.

### Social Media Campaigns
Create shareable codes for social promotions. Track which platforms drive the most conversions.

---

## Technical Documentation

### Overview

The Coupon Codes microservice manages promotional code campaigns including creation, generation, validation, redemption, and analytics.

### Database Schema

| Table | Description |
|-------|-------------|
| `coupon_campaigns` | Campaign definitions |
| `coupon_codes` | Individual codes |
| `coupon_redemptions` | Usage records |
| `coupon_generation_jobs` | Bulk generation tasks |
| `coupon_validation_attempts` | Validation log |

### API Endpoints

#### Campaign Management

```
GET /api/coupons/campaigns
```
List all campaigns.

```
POST /api/coupons/campaigns
```
Create new campaign.

```
GET /api/coupons/campaigns/{id}
```
Get campaign details.

```
PUT /api/coupons/campaigns/{id}
```
Update campaign.

```
DELETE /api/coupons/campaigns/{id}
```
Delete campaign.

```
POST /api/coupons/campaigns/{id}/activate
```
Activate campaign.

#### Code Generation

```
POST /api/coupons/campaigns/{id}/generate
```
Generate codes for campaign.

**Request:**
```json
{
  "quantity": 1000,
  "format": "alphanumeric",
  "prefix": "SUMMER",
  "length": 6
}
```

```
GET /api/coupons/campaigns/{id}/codes
```
List codes in campaign.

#### Validation & Redemption

```
POST /api/coupons/validate
```
Validate code at checkout.

**Request:**
```json
{
  "code": "SUMMER20ABC",
  "cart": {
    "items": [...],
    "total": 150.00
  },
  "customer_id": "cust_123"
}
```

```
POST /api/coupons/redeem
```
Record code redemption.

```
POST /api/coupons/reverse
```
Reverse redemption (refund).

#### Analytics

```
GET /api/coupons/campaigns/{id}/stats
```
Campaign performance statistics.

```
GET /api/coupons/redemptions
```
List all redemptions.

```
GET /api/coupons/export/{campaignId}
```
Export codes to CSV/JSON.

### Campaign Structure

```json
{
  "id": "camp_123",
  "name": "Summer Sale 2025",
  "type": "percentage",
  "value": 20,
  "rules": {
    "min_purchase": 50.00,
    "max_discount": 100.00,
    "usage_limit": 1000,
    "per_user_limit": 1,
    "first_purchase_only": false,
    "valid_from": "2025-06-01T00:00:00Z",
    "valid_until": "2025-08-31T23:59:59Z",
    "applicable_events": [1, 2, 3],
    "excluded_categories": ["vip"]
  },
  "code_format": {
    "type": "alphanumeric",
    "prefix": "SUMMER",
    "length": 6
  },
  "status": "active",
  "stats": {
    "codes_generated": 1000,
    "codes_used": 245,
    "total_discount": 4900.00,
    "revenue_generated": 24500.00
  }
}
```

### Validation Response

```json
{
  "valid": true,
  "code": "SUMMER20ABC",
  "discount": {
    "type": "percentage",
    "value": 20,
    "amount": 30.00
  },
  "message": "Code applied successfully",
  "restrictions": {
    "remaining_uses": 755,
    "expires_at": "2025-08-31T23:59:59Z"
  }
}
```

### Configuration

```php
'coupons' => [
    'discount_types' => ['percentage', 'fixed', 'free_shipping', 'buy_x_get_y'],
    'code_formats' => ['alphanumeric', 'numeric', 'alphabetic', 'custom'],
    'max_codes_per_batch' => 10000,
    'validation' => [
        'log_attempts' => true,
        'rate_limit' => 100, // per minute
    ],
]
```

### Integration Example

```php
use App\Services\PromoCodes\PromoCodeService;
use App\Services\PromoCodes\PromoCodeValidator;

$service = app(PromoCodeService::class);
$validator = app(PromoCodeValidator::class);

// Validate code
$result = $validator->validate($code, $cart, $customer);

if ($result->isValid()) {
    // Apply discount to cart
    $cart->applyDiscount($result->getDiscount());

    // Record redemption after payment
    $service->redeem($code, $order);
}
```
