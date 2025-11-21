# Door Sales / Box Office Microservice

Sell tickets at the door using Tap to Pay on mobile devices.

## Features
- Select event and ticket types
- Adjust quantities
- Tap to Pay (Card, Apple Pay, Google Pay)
- Optional customer email for ticket delivery
- Automatic ticket issuance
- Platform fee tracking (percentage-based)
- Refund support
- Sales history and daily summaries

## Database Tables
- `door_sales` - Transaction records
- `door_sale_items` - Line items per sale
- `door_sale_platform_fees` - Revenue tracking

## Payment Flow
```
1. Staff selects event → chooses ticket type → sets quantity
2. Staff enters customer email (optional)
3. Customer taps card/phone on staff device
4. Payment processed → Tickets issued → Email sent
```

## API Endpoints
- `GET /api/door-sales/events` - Available events
- `GET /api/door-sales/events/{id}/ticket-types` - Ticket types with stock
- `POST /api/door-sales/calculate` - Preview totals with fees
- `POST /api/door-sales/process` - Process payment
- `GET /api/door-sales/{id}` - Sale details
- `GET /api/door-sales/history` - Sales history
- `GET /api/door-sales/summary` - Daily summary
- `POST /api/door-sales/{id}/refund` - Process refund
- `POST /api/door-sales/{id}/resend` - Resend tickets

## Payment Methods
- `card_tap` - Physical card tap
- `apple_pay` - Apple Pay
- `google_pay` - Google Pay

## Revenue Model
```
Platform Fee: 2.5% of subtotal (configurable)
Minimum Fee: €0.10
Processing Fee: 1.4% + €0.25 (Stripe passthrough)

Example: €100 ticket sale
- Subtotal: €100.00
- Platform Fee: €2.50
- Processing Fee: €1.65
- Total Charged: €104.15
```

## Usage

```php
$service = app(DoorSalesService::class);

// Calculate totals
$calculation = $service->calculate([
    'items' => [
        ['ticket_type_id' => 1, 'quantity' => 2],
    ],
]);

// Process sale
$result = $service->process([
    'tenant_id' => $tenantId,
    'event_id' => $eventId,
    'user_id' => $staffUserId,
    'items' => [
        ['ticket_type_id' => 1, 'quantity' => 2],
    ],
    'payment_method' => 'apple_pay',
    'customer_email' => 'customer@example.com',
]);
```

## Stripe Terminal Integration

Uses Stripe Terminal SDK for Tap to Pay:
- iPhone: Tap to Pay on iPhone (iOS 15.4+)
- Android: Tap to Pay on Android

Configure in `.env`:
```
STRIPE_TERMINAL_LOCATION=tml_xxxxx
```

## Configuration

See `config/door-sales.php` for all options including:
- Fee percentages
- Volume discount tiers
- Transaction limits
- Payment methods
