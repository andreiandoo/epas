# Group Booking

## Short Presentation

Simplify bulk ticket purchases with Group Booking. Corporate events, school trips, tour groups - when organizations need multiple tickets, the standard checkout doesn't cut it. Group Booking provides a streamlined experience for large orders.

Enable tiered group discounts that reward larger purchases. Buy 10 tickets, get 10% off. Buy 50, get 20% off. The more they buy, the more they save - and the more seats you fill.

The group leader dashboard puts organizers in control. Manage attendee lists, collect individual details, and distribute tickets to group members. Need dietary requirements or accessibility needs? Customize booking forms to collect exactly what you need.

Seat block reservations ensure groups sit together. Reserve an entire section or scattered seats across the venue - whatever works best for the event.

Approval workflows protect your inventory. Large group requests can require manual approval before tickets are released, giving you control over significant orders.

Partial payments make large purchases manageable. Collect a deposit to hold tickets, then collect the balance closer to the event date.

Group check-in at the venue is seamless. Process the entire group at once rather than scanning individual tickets. Perfect for tour buses arriving at the door.

From corporate team outings to family reunions, Group Booking handles the complexity of large orders gracefully.

---

## Features

### Booking Management
- Bulk ticket ordering interface
- Tiered group discounts
- Group leader dashboard
- Attendee list management
- Individual ticket distribution
- Seat block reservations

### Workflow
- Approval workflow for large groups
- Custom group booking forms
- Dietary/accessibility requirements collection
- Group communication tools
- Waitlist for sold-out group allocations

### Payments
- Group invoice generation
- Partial payment support
- Deposit collection

### Venue Operations
- Group check-in at venue
- Bulk ticket scanning

---

## Technical Documentation

### API Endpoints

```
POST /api/group-booking/request
```
Submit group booking request.

```
GET /api/group-booking/{bookingId}
```
Get booking details.

```
PUT /api/group-booking/{bookingId}/attendees
```
Update attendee list.

```
POST /api/group-booking/{bookingId}/distribute
```
Distribute tickets to group members.

```
GET /api/group-booking/discounts/{eventId}
```
Get available group discounts.

### Configuration

```php
'group_booking' => [
    'min_group_size' => 10,
    'max_group_size' => 500,
    'payment_split' => true,
    'require_approval' => true,
]
```
