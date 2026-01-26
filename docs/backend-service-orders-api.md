# Backend API Requirements for Service Orders

This document describes the API endpoints and data models required in the core backend (Tixello) to support the Organizer Services feature in the marketplace.

## Data Models

### ServiceOrder

The main model for tracking service purchases by organizers.

```php
// Suggested database schema for service_orders table
Schema::create('service_orders', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique(); // For public reference
    $table->string('order_number')->unique(); // e.g., "SVC-2024-00001"
    $table->foreignId('marketplace_id')->constrained();
    $table->foreignId('organizer_id')->constrained();
    $table->foreignId('event_id')->constrained();

    // Service type: featuring, email, tracking, campaign
    $table->enum('service_type', ['featuring', 'email', 'tracking', 'campaign']);

    // Service configuration (JSON)
    $table->json('config')->nullable();
    /*
    Config examples:

    Featuring:
    {
        "locations": ["home", "category", "city"],
        "start_date": "2024-02-01",
        "end_date": "2024-02-14"
    }

    Email Marketing:
    {
        "audience_type": "own", // "own" (organizer's customers) or "marketplace" (all users)
        "filters": {
            "age_min": 25,
            "age_max": 45,
            "city": "bucuresti",
            "category": "concerte",
            "genre": "rock"
        },
        "recipient_count": 12500,
        "price_per_email": 0.40, // 0.40 for own, 0.50 for marketplace
        "send_date": "2024-02-01T10:00:00Z",
        "sent_at": null,
        "sent_count": 0,
        "brevo_campaign_id": null // Set after campaign is created in Brevo
    }

    Ad Tracking:
    {
        "platforms": ["facebook", "google"],
        "duration_months": 3,
        "pixel_ids": {
            "facebook": "123456789",
            "google": "UA-XXXXX-Y"
        }
    }

    Campaign Creation:
    {
        "campaign_type": "standard", // "basic", "standard", "premium"
        "platforms": ["facebook", "google"],
        "ad_budget": 1000,
        "notes": "Focus on young adults..."
    }
    */

    // Pricing
    $table->decimal('subtotal', 10, 2);
    $table->decimal('tax', 10, 2)->default(0);
    $table->decimal('total', 10, 2);
    $table->string('currency', 3)->default('RON');

    // Payment
    $table->enum('payment_method', ['card', 'transfer'])->nullable();
    $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
    $table->timestamp('paid_at')->nullable();
    $table->string('payment_reference')->nullable(); // Transaction ID from Netopia

    // Order status
    $table->enum('status', [
        'draft',           // Order created but not submitted
        'pending_payment', // Awaiting payment
        'processing',      // Payment received, service being set up
        'active',          // Service is active
        'completed',       // Service period ended
        'cancelled',       // Cancelled by organizer or admin
        'refunded'         // Payment refunded
    ])->default('draft');

    // For email campaigns
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('executed_at')->nullable();

    // Service period
    $table->date('service_start_date')->nullable();
    $table->date('service_end_date')->nullable();

    // Admin notes
    $table->text('admin_notes')->nullable();
    $table->foreignId('assigned_to')->nullable()->constrained('users');

    $table->timestamps();
    $table->softDeletes();

    // Indexes
    $table->index(['marketplace_id', 'status']);
    $table->index(['organizer_id', 'status']);
    $table->index(['service_type', 'status']);
});
```

### ServiceType (Reference table for pricing)

```php
Schema::create('service_types', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique(); // featuring, email, tracking, campaign
    $table->string('name');
    $table->text('description')->nullable();
    $table->json('pricing'); // Pricing configuration
    /*
    {
        "featuring": {
            "home": {"daily_rate": 99},
            "category": {"daily_rate": 69},
            "genre": {"daily_rate": 59},
            "city": {"daily_rate": 49}
        },
        "email": {
            "own_per_email": 0.40,        // Price when sending to organizer's own customers
            "marketplace_per_email": 0.50, // Price when sending to full marketplace database
            "minimum": 100
        },
        "tracking": {
            "per_platform_monthly": 49,
            "discounts": {"3": 0.10, "6": 0.15, "12": 0.25}
        },
        "campaign": {
            "basic": 499,
            "standard": 899,
            "premium": 1499
        }
    }
    */
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

## API Endpoints

All endpoints require organizer authentication via Bearer token.

### GET /organizer/services

List active services for the organizer.

**Query Parameters:**
- `event_id` (optional): Filter by event
- `type` (optional): Filter by service type
- `status` (optional): Filter by status
- `page`, `per_page`: Pagination

**Response:**
```json
{
    "success": true,
    "data": {
        "services": [
            {
                "id": 123,
                "type": "featuring",
                "event_id": 456,
                "event_title": "Concert Example",
                "details": "Pagina Principala, Categorie",
                "start_date": "2024-02-01",
                "end_date": "2024-02-14",
                "status": "active"
            }
        ],
        "pagination": {...}
    }
}
```

### GET /organizer/services/stats

Get service usage statistics.

**Response:**
```json
{
    "success": true,
    "data": {
        "active_count": 3,
        "total_views": 15420,
        "emails_sent": 45000,
        "total_spent": 2500.00
    }
}
```

### GET /organizer/services/types

Get available service types with current pricing.

**Response:**
```json
{
    "success": true,
    "data": {
        "types": [
            {
                "code": "featuring",
                "name": "Promovare Eveniment",
                "description": "...",
                "pricing": {...}
            }
        ]
    }
}
```

### GET /organizer/services/email-audiences

Get available email audiences with recipient counts. Supports filtering to narrow down the audience.

**Query Parameters:**
- `event_id` (required): The event to get audience data for
- `audience_type` (required): "own" (organizer's customers) or "marketplace" (all platform users)
- `age_min` (optional): Minimum age filter (e.g., 18, 25, 30)
- `age_max` (optional): Maximum age filter (e.g., 25, 35, 50)
- `city` (optional): City slug filter (e.g., "bucuresti")
- `category` (optional): Event category slug filter (e.g., "concerte")
- `genre` (optional): Music genre slug filter (e.g., "rock")

**Response:**
```json
{
    "success": true,
    "data": {
        "audience_type": "own",
        "total_count": 5000,      // Total before filters
        "filtered_count": 1250,   // Count after applying filters
        "filters_applied": {
            "age_min": 25,
            "age_max": 45,
            "city": "bucuresti"
        },
        "price_per_email": 0.40   // Based on audience_type
    }
}
```

**Important Notes:**
- Organizers NEVER see individual customer data (name, email, phone)
- Only aggregate counts are returned
- Filtering is done server-side to protect customer privacy
- The email campaign is sent through the platform (Brevo), not exposed to the organizer

### POST /organizer/services/orders

Create a new service order.

**Request Body:**
```json
{
    "service_type": "featuring",
    "event_id": 456,
    "payment_method": "card",
    "locations": ["home", "category"],
    "start_date": "2024-02-01",
    "end_date": "2024-02-14"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "order": {
            "id": "svc-uuid-123",
            "order_number": "SVC-2024-00001",
            "service_type": "featuring",
            "event_id": 456,
            "config": {...},
            "subtotal": 2352.00,
            "tax": 447.00,
            "total": 2799.00,
            "status": "pending_payment"
        }
    }
}
```

### GET /organizer/services/orders/{id}

Get service order details.

**Response:**
```json
{
    "success": true,
    "data": {
        "order": {
            "id": "svc-uuid-123",
            "order_number": "SVC-2024-00001",
            "service_type": "email",
            "event_id": 456,
            "event": {
                "id": 456,
                "title": "Concert Example",
                "image": "https://...",
                "date": "2024-03-15",
                "venue": "Sala Palatului"
            },
            "config": {
                "audience": "filtered",
                "recipient_count": 45000
            },
            "subtotal": 2250.00,
            "tax": 427.50,
            "total": 2677.50,
            "payment_status": "paid",
            "status": "processing",
            "created_at": "2024-01-26T10:00:00Z",
            "paid_at": "2024-01-26T10:05:00Z"
        }
    }
}
```

### POST /organizer/services/orders/{id}/pay

Initiate payment for service order. Uses the marketplace's configured payment gateway (Netopia).

**Request Body:**
```json
{
    "return_url": "https://marketplace.com/organizator/services/success?order=123&type=email&event=456",
    "cancel_url": "https://marketplace.com/organizator/services?cancelled=1"
}
```

**Response (for Netopia):**
```json
{
    "success": true,
    "data": {
        "payment_url": "https://secure.netopia-payments.com/...",
        "method": "POST",
        "form_data": {
            "env_key": "...",
            "data": "..."
        }
    }
}
```

### POST /organizer/services/orders/{id}/send-email

Trigger email campaign send (for email marketing orders).

**Request Body:**
```json
{
    "scheduled_time": null  // null for immediate, or ISO datetime for scheduled
}
```

**Response:**
```json
{
    "success": true,
    "message": "Campania a fost trimisa cu succes",
    "data": {
        "sent_count": 45000,
        "scheduled_at": null,
        "executed_at": "2024-01-26T10:30:00Z"
    }
}
```

### POST /organizer/services/orders/{id}/cancel

Cancel a pending service order.

**Response:**
```json
{
    "success": true,
    "message": "Comanda a fost anulata"
}
```

## Admin API Endpoints

### GET /admin/services/pricing

Get current service pricing configuration.

**Response:**
```json
{
    "success": true,
    "data": {
        "email": {
            "own_per_email": 0.40,
            "marketplace_per_email": 0.50,
            "minimum": 100
        },
        "featuring": {
            "home": 99,
            "category": 69,
            "genre": 59,
            "city": 49
        },
        "tracking": {
            "per_platform_monthly": 49,
            "discounts": {
                "1": 0,
                "3": 0.10,
                "6": 0.15,
                "12": 0.25
            }
        },
        "campaign": {
            "basic": 499,
            "standard": 899,
            "premium": 1499
        }
    }
}
```

### POST /admin/services/pricing

Update service pricing configuration.

**Request Body:**
```json
{
    "email": {
        "own_per_email": 0.40,
        "marketplace_per_email": 0.50,
        "minimum": 100
    },
    "featuring": {
        "home": 99,
        "category": 69,
        "genre": 59,
        "city": 49
    },
    "tracking": {
        "per_platform_monthly": 49,
        "discounts": {
            "1": 0,
            "3": 0.10,
            "6": 0.15,
            "12": 0.25
        }
    },
    "campaign": {
        "basic": 499,
        "standard": 899,
        "premium": 1499
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Pricing updated successfully"
}
```

### GET /admin/services/orders

List all service orders (admin view).

**Query Parameters:**
- `status` (optional): Filter by order status
- `type` (optional): Filter by service type
- `organizer_id` (optional): Filter by organizer
- `page`, `per_page`: Pagination

### GET /admin/services/orders/{id}

Get service order details (admin view).

### PUT /admin/services/orders/{id}

Update service order (admin actions like status change, notes, assignment).

## Admin Panel Integration

Service orders should appear in the marketplace admin panel under Orders with:

1. **List View Columns:**
   - Order Number
   - Organizer Name
   - Event Name
   - Service Type (with badge color)
   - Total Amount
   - Payment Status
   - Order Status
   - Created Date
   - Actions

2. **Filters:**
   - Service Type
   - Payment Status
   - Order Status
   - Date Range
   - Organizer

3. **Detail View:**
   - Full order details
   - Event information
   - Service configuration
   - Payment details
   - Timeline/Activity log
   - Admin notes field
   - Assign to team member (for campaigns)
   - Status update actions

4. **Actions:**
   - Mark as Paid (for bank transfers)
   - Activate Service
   - Complete Service
   - Cancel Order
   - Refund Order
   - Add Admin Note

## Webhook/IPN Handling

For Netopia payment confirmations:

```php
// POST /webhooks/netopia/service-orders
public function handleNetopiaIPN(Request $request)
{
    // Validate IPN
    // Update service order payment status
    // If paid, update status to 'processing' or 'active'
    // Send confirmation email to organizer
}
```

## Cron Jobs

1. **Activate Featuring Services:**
   - Run daily at 00:01
   - Find orders with `service_type=featuring`, `status=processing`, `service_start_date=today`
   - Update event featuring flags
   - Update order status to `active`

2. **Deactivate Expired Featuring:**
   - Run daily at 23:59
   - Find active featuring orders where `service_end_date=today`
   - Remove event featuring flags
   - Update order status to `completed`

3. **Send Scheduled Emails:**
   - Run every 5 minutes
   - Find email orders with `scheduled_at <= now()` and `executed_at IS NULL`
   - Trigger email send
   - Update `executed_at`

4. **Expire Tracking Subscriptions:**
   - Run daily
   - Find active tracking orders where subscription period ended
   - Deactivate tracking pixels
   - Update order status to `completed`
