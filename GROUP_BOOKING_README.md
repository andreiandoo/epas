# Group Booking Microservice

Corporate and group ticket booking with split payments and tiered discounts.

## Features
- Group reservations with organizer management
- Split payment support (each member pays individually)
- Tiered volume discounts
- CSV member import
- Payment tracking per member
- Automatic confirmation when all paid

## Database Tables
- `group_bookings` - Main booking records
- `group_booking_members` - Individual member records with payments
- `group_pricing_tiers` - Discount tiers by group size

## API Endpoints
- `POST /api/group-bookings` - Create group booking
- `GET /api/group-bookings/{id}` - Get booking details
- `POST /api/group-bookings/{id}/members` - Add members
- `POST /api/group-bookings/{id}/import` - Import members from CSV
- `POST /api/group-bookings/{id}/confirm` - Confirm booking
- `POST /api/group-bookings/members/{id}/pay` - Process member payment
- `GET /api/group-bookings/stats` - Get statistics

## Payment Types
- **full** - Organizer pays entire amount
- **split** - Each member gets payment link
- **invoice** - Corporate invoicing

## Usage
```php
$service = app(GroupBookingService::class);
$result = $service->create([
    'tenant_id' => $tenantId,
    'event_id' => $eventId,
    'organizer_customer_id' => $customerId,
    'group_name' => 'Company Outing',
    'total_tickets' => 25,
    'ticket_price' => 50.00,
    'payment_type' => 'split',
]);
```
