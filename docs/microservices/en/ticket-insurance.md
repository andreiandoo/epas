# Ticket Insurance

## Short Presentation

Give your customers peace of mind with Ticket Insurance. Life is unpredictable, and sometimes plans change. This optional add-on allows ticket buyers to protect their purchase against unforeseen circumstances, creating an additional revenue stream while enhancing customer satisfaction.

During checkout, customers can opt to add insurance coverage for a small premium. If they can't attend the event due to covered reasons, they can claim their money back. It's that simple.

The hierarchical configuration system gives you complete control. Set insurance options at the tenant level as defaults, customize them per event, or fine-tune settings for specific ticket types. Pricing can be a fixed amount or a percentage of the ticket price, with minimum and maximum caps to ensure appropriate coverage.

Our provider-agnostic architecture supports multiple insurance partners, with real-time quote calculations and automated policy issuance. The entire process is seamless - policies are issued automatically when orders are confirmed, and all documentation is stored securely for easy access.

Refund processing is equally straightforward, with configurable policies for no-refund, proportional refund, or full refund if unused. Complete tracking from quote to claim provides transparency for both you and your customers.

Best of all, this service is free to activate. Revenue comes from insurance commissions, meaning there's no upfront cost to offer this valuable customer benefit.

---

## Detailed Description

Ticket Insurance is a comprehensive insurance add-on service designed specifically for event ticketing platforms. It provides a seamless way to offer ticket protection to customers while generating additional revenue through insurance commissions.

### How Insurance Works

When a customer proceeds to checkout, they're presented with an option to add insurance to their ticket purchase. The premium is calculated in real-time based on the configured pricing model and ticket value. If selected, the insurance cost is added to the order total.

Upon successful payment, insurance policies are issued automatically and linked to each ticket. Policy documents are stored securely and accessible to both the customer and event organizer.

### Hierarchical Configuration

The service supports a three-tier configuration hierarchy:

1. **Tenant Level**: Default settings that apply to all events
2. **Event Level**: Overrides for specific events
3. **Ticket Type Level**: Fine-grained control per ticket category

This allows maximum flexibility while minimizing configuration overhead for standard cases.

### Pricing Models

- **Fixed Amount**: A set premium regardless of ticket price (e.g., €2.00)
- **Percentage**: Premium calculated as percentage of ticket value (e.g., 5%)
- **Tiered**: Different rates for different price ranges
- **Caps**: Minimum and maximum premium limits

### Provider Integration

The adapter-based architecture supports integration with multiple insurance providers. Each provider implements a standard interface for:
- Getting quotes
- Issuing policies
- Processing voids and refunds
- Synchronizing policy status

---

## Features

### Configuration
- Hierarchical config: tenant → event → ticket_type
- Pricing modes: fixed amount or percentage of ticket price
- Min/max premium caps
- Tax policy configuration (inclusive/exclusive)
- Scope options: per-ticket or per-order
- Eligibility rules (country, ticket type, event, price range)

### Quote System
- Real-time premium calculation
- Provider adapter integration
- Multiple pricing strategies
- Tax calculation support

### Policy Management
- Idempotent policy issuance
- Provider-agnostic adapter pattern
- Policy document storage and URLs
- Status tracking: pending, issued, voided, refunded, error
- Comprehensive event logging

### Refund & Void
- Configurable cancellation policies
- Policy options: no_refund, proportional, full_if_unused
- Provider sync for refunds/voids
- Partial refund support

### Checkout Integration
- Optional add-on checkbox in checkout
- Real-time premium display
- Terms & consent UI
- Per-line or per-order selection

### Security & Compliance
- Clear separation from ticket price
- Explicit consent required
- PII minimization
- Secure document storage
- GDPR compliant

### Reporting & Analytics
- Attach rate tracking
- Premium GMV calculation
- Void/refund rate monitoring
- Provider error tracking
- CSV export

---

## Use Cases

### Concert & Festival Protection
Offer fans the confidence to buy tickets months in advance. With insurance, they know they're protected if illness, travel issues, or family emergencies prevent attendance.

### Corporate Event Tickets
B2B customers purchasing tickets for employees appreciate the flexibility insurance provides, especially for non-refundable events.

### High-Value VIP Packages
Premium tickets with significant cost benefit most from insurance options. Customers are more likely to splurge on VIP experiences when they can protect their investment.

### International Events
Travelers booking events abroad have additional risks. Insurance provides peace of mind against flight cancellations, visa issues, or unexpected travel restrictions.

### Group Bookings
When organizing group attendance, having insurance ensures the entire group is protected, not just individual travelers.

### Season Tickets
Long-term commitments like season passes benefit from insurance coverage that spans multiple events throughout the season.

---

## Technical Documentation

### Overview

The Ticket Insurance microservice provides optional insurance add-ons for ticket purchases. It handles quote calculation, policy issuance, status tracking, and refund processing through a provider-agnostic adapter system.

### Architecture

```
Checkout Flow → Insurance Service → Provider Adapter → Insurance Provider API
                      ↓
               Configuration Engine (tenant → event → ticket_type)
                      ↓
               Policy Manager → Document Storage
```

### Database Schema

| Table | Description |
|-------|-------------|
| `insurance_configs` | Hierarchical configuration settings |
| `insurance_policies` | Issued policy records |
| `insurance_events` | Policy lifecycle events |

### Configuration Model

```php
// Insurance Config
[
    'tenant_id' => 'tenant_001',
    'event_id' => null, // null = tenant default
    'ticket_type_id' => null,
    'enabled' => true,
    'pricing_mode' => 'percentage', // or 'fixed'
    'rate' => 5.00, // 5% or €5.00
    'min_premium' => 1.00,
    'max_premium' => 50.00,
    'tax_mode' => 'inclusive', // or 'exclusive'
    'scope' => 'per_ticket', // or 'per_order'
    'eligibility' => [
        'countries' => ['RO', 'DE', 'FR'],
        'min_ticket_price' => 10.00,
        'max_ticket_price' => 1000.00,
    ],
    'cancellation_policy' => 'proportional',
]
```

### API Endpoints

#### Get Quote
```
POST /api/insurance/quote
```
Calculate insurance premium for cart items.

**Request:**
```json
{
  "items": [
    {"ticket_type_id": 1, "quantity": 2, "unit_price": 50.00}
  ],
  "event_id": 123
}
```

**Response:**
```json
{
  "quote_id": "q_abc123",
  "premium": 5.00,
  "currency": "EUR",
  "valid_until": "2025-01-15T12:00:00Z",
  "coverage_details": {...}
}
```

#### Issue Policy
```
POST /api/insurance/policies
```
Issue insurance policy for an order.

#### Get Policy
```
GET /api/insurance/policies/{policyId}
```
Retrieve policy details and document URL.

#### Void Policy
```
POST /api/insurance/policies/{policyId}/void
```
Cancel policy (e.g., when order is cancelled).

#### Refund Policy
```
POST /api/insurance/policies/{policyId}/refund
```
Process policy refund.

### Provider Adapter Interface

```php
interface InsuranceAdapterInterface
{
    public function getQuote(QuoteRequest $request): QuoteResponse;
    public function issuePolicy(PolicyRequest $request): PolicyResponse;
    public function voidPolicy(string $policyId): bool;
    public function refundPolicy(string $policyId, float $amount): RefundResponse;
    public function syncStatus(string $policyId): PolicyStatus;
}
```

### Policy Status Flow

```
pending → issued → [active]
                ↘ voided
                ↘ refunded
                ↘ error
```

### Integration Example

```php
use App\Services\Insurance\InsuranceService;

// Get quote during checkout
$insurance = app(InsuranceService::class);
$quote = $insurance->getQuote($cartItems, $event);

// Issue policy after payment
$policy = $insurance->issuePolicy($order, $quote);

// Process void on cancellation
$insurance->voidPolicy($policy->id);
```

### Configuration Resolution

The service resolves configuration by checking (in order):
1. Ticket type specific config
2. Event specific config
3. Tenant default config

```php
$config = $insurance->resolveConfig($ticketTypeId, $eventId, $tenantId);
```

### Webhook Events

The service emits the following events:
- `insurance.quote.created`
- `insurance.policy.issued`
- `insurance.policy.voided`
- `insurance.policy.refunded`
- `insurance.policy.error`

### Metrics

Track insurance performance:
- Attach rate (% of orders with insurance)
- Total premium collected
- Void/refund rates
- Average premium per order
- Provider response times
