# Door Sales

## Short Presentation

Sell tickets right at the venue door with Door Sales. When customers arrive without tickets, don't turn them away - turn them into attendees. This complete point-of-sale system puts ticket sales in your hands, literally.

Accept cash, card, or mobile payments with ease. The system supports multiple payment processors including Stripe, Square, and SumUp, giving you flexibility in how you handle transactions. Thermal receipt printing ensures customers walk away with proof of purchase.

Real-time inventory sync means you'll never oversell. As tickets are sold at the door, availability updates instantly across all sales channels. Online inventory and door sales stay perfectly synchronized.

When internet goes down, business doesn't stop. Offline mode stores transactions locally and syncs automatically when connectivity returns. Handle up to 1000 transactions offline without missing a beat.

End-of-day reconciliation is simple. Daily reports break down sales by payment method, ticket type, and staff member. Cash drawer management tracks every opening and closing balance.

Multiple devices can run simultaneously at different venue entrances. Staff access controls ensure team members only see what they need, while audit logs track every transaction for complete accountability.

When refunds or exchanges are needed, process them on the spot. Look up customers by phone or email to find their original purchases instantly.

Never miss a sale. Door Sales puts your box office everywhere.

---

## Detailed Description

Door Sales is a comprehensive point-of-sale (POS) solution designed specifically for selling tickets at event venues. It provides all the tools needed for efficient on-site ticket sales while maintaining real-time synchronization with your central ticketing system.

### Multi-Device Support

Deploy multiple tablets or POS terminals at different venue entrances. Each device operates independently while maintaining sync with the central system.

### Payment Processing

Integrated support for major payment processors:
- **Stripe**: Card payments via Stripe Terminal
- **Square**: Square reader integration
- **SumUp**: Portable card reader support
- **Cash**: Full cash management with drawer tracking

### Offline Resilience

The system is built for reliability:
- Local transaction storage when offline
- Automatic sync when connectivity returns
- Capacity for 1000+ offline transactions
- No duplicate sales or inventory conflicts

### Inventory Management

Real-time synchronization ensures accuracy:
- Instant inventory updates across channels
- Prevention of overselling
- Reservation holds during checkout
- Automatic release of abandoned carts

---

## Features

### Core POS
- Real-time ticket inventory synchronization
- Multiple payment methods (cash, card, mobile)
- Thermal receipt printing
- Barcode/QR code ticket generation
- Customer lookup by phone/email

### Reliability
- Offline mode with automatic sync
- Multiple device support per venue
- Transaction queuing
- Conflict resolution

### Management
- Daily sales reports and reconciliation
- Cash drawer management
- Staff access controls and audit logs
- Refund and exchange processing

### Integration
- Integration with main ticketing system
- Payment processor flexibility
- Receipt customization
- Real-time reporting

---

## Use Cases

### Concert Venue Box Office
Staff at multiple windows sell tickets simultaneously, each with their own cash drawer. End of night, reconcile all drawers and export sales data.

### Festival Entry Points
Multiple gates each have a tablet for selling day passes to walk-up customers. Offline mode handles spotty festival connectivity.

### Theater Will-Call Plus Sales
Combine will-call pickup with on-site sales at the same station. Look up reservations and process new purchases.

### Sports Venue
Multiple entry gates handle game-day sales for late-arriving fans. Quick transactions minimize lines.

---

## Technical Documentation

### API Endpoints

```
POST /api/door-sales/transaction
```
Process a sale transaction.

```
GET /api/door-sales/inventory/{eventId}
```
Get available inventory for event.

```
POST /api/door-sales/sync
```
Sync offline transactions.

```
GET /api/door-sales/reports/{tenantId}
```
Get sales reports.

```
POST /api/door-sales/refund
```
Process a refund.

### Configuration

```php
'door_sales' => [
    'supported_devices' => ['tablet', 'pos-terminal', 'mobile'],
    'payment_processors' => ['stripe', 'square', 'sumup'],
    'offline_capacity' => 1000,
    'receipt_printer' => 'thermal',
]
```
