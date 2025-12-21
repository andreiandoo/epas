# Marketplace Platform Documentation

## Overview

The Marketplace feature extends Tixello to support multi-organizer ticketing platforms. A **Marketplace** is a special type of tenant that can host multiple **Organizers**, each with their own events, revenue tracking, and dashboard.

### Key Concepts

- **Marketplace**: A tenant with `tenant_type='marketplace'` that acts as a platform for multiple event organizers
- **Organizer**: An entity that creates events and sells tickets within a marketplace
- **Commission Structure**: Three-tier revenue distribution:
  1. **Tixello Platform Fee**: Fixed 1% from all orders (hardcoded)
  2. **Marketplace Commission**: Configurable percentage, fixed amount, or both
  3. **Organizer Revenue**: Remaining amount after commissions

## Architecture

### Database Schema

```
tenants (extended)
├── tenant_type: 'marketplace' | 'standard'
├── marketplace_commission_type: 'percent' | 'fixed' | 'both'
├── marketplace_commission_percent
├── marketplace_commission_fixed
└── marketplace_settings (JSON)

marketplace_organizers
├── tenant_id (FK → tenants)
├── name, slug, description
├── contact_name, contact_email, contact_phone
├── company_name, cui, reg_com
├── status: 'pending_approval' | 'active' | 'suspended' | 'closed'
├── commission_type/percent/fixed (overrides)
├── payout_method, payout_details, payout_frequency
├── total_events, total_orders, total_revenue, pending_payout
└── is_verified, verified_at

marketplace_organizer_users
├── organizer_id (FK)
├── name, email, password
├── role: 'admin' | 'editor' | 'viewer'
└── is_active, last_login_at

marketplace_payouts
├── organizer_id (FK)
├── reference (unique)
├── period_start, period_end
├── total_amount, currency
├── status: 'pending' | 'processing' | 'completed' | 'failed'
└── transaction_reference, processed_at

marketplace_payout_items
├── payout_id (FK)
├── order_id (FK)
└── amount

orders (extended)
├── organizer_id (FK → marketplace_organizers, nullable)
├── tixello_commission
├── marketplace_commission
├── organizer_revenue
└── payout_id (FK → marketplace_payouts, nullable)

events (extended)
└── organizer_id (FK → marketplace_organizers, nullable)
```

### Authentication

The system uses Laravel's multi-guard authentication:

```php
// config/auth.php
'guards' => [
    'web' => [...],        // Standard users
    'organizer' => [       // Organizer users
        'driver' => 'session',
        'provider' => 'organizer_users',
    ],
],

'providers' => [
    'users' => [...],
    'organizer_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\Marketplace\MarketplaceOrganizerUser::class,
    ],
],
```

## Commission Calculation

### Formula

```
Order Total: $100.00

1. Tixello Fee (1%): $1.00
2. Remaining: $99.00

3. Marketplace Commission (e.g., 10%):
   - If 'percent': $99.00 × 10% = $9.90
   - If 'fixed': $5.00 (fixed amount)
   - If 'both': $99.00 × 10% + $5.00 = $14.90

4. Organizer Revenue: $99.00 - $9.90 = $89.10
```

### Code Example

```php
// In App\Models\Tenant

public function calculateMarketplaceCommission(
    float $orderTotal,
    ?MarketplaceOrganizer $organizer = null
): array {
    // Tixello always takes 1%
    $tixelloCommission = round($orderTotal * self::TIXELLO_PLATFORM_FEE, 2);
    $afterTixello = $orderTotal - $tixelloCommission;

    // Calculate marketplace commission
    $marketplaceCommission = $this->calculateCommissionAmount(
        $afterTixello,
        $organizer // May have custom rates
    );

    $organizerRevenue = $afterTixello - $marketplaceCommission;

    return [
        'order_total' => $orderTotal,
        'tixello_commission' => $tixelloCommission,
        'marketplace_commission' => $marketplaceCommission,
        'organizer_revenue' => $organizerRevenue,
    ];
}
```

## Filament Panels

### 1. Admin Panel (`/admin`)

Manages marketplaces at the platform level:

- `MarketplaceResource`: Create/manage marketplace tenants
  - Commission configuration
  - Organizer overview
  - Revenue statistics

### 2. Tenant Panel (`/tenant`)

Extended for marketplace owners:

- `OrganizerResource`: Manage organizers
  - Approve/suspend organizers
  - View events and orders
  - Manage payouts
  - Team management

### 3. Organizer Panel (`/organizer`)

Dedicated panel for organizer users:

- **Dashboard**: Stats, revenue chart, recent orders
- **Events**: Create and manage events
- **Orders**: View orders (read-only)
- **Payouts**: View payout history
- **Team**: Manage team members (admin only)

## API Endpoints

### Marketplace Client API (`/api/marketplace-client`)

```
GET    /bootstrap              - Initial marketplace data
GET    /categories             - Event categories
GET    /events                 - List events
GET    /events/featured        - Featured events
GET    /events/{slug}          - Event details
GET    /events/{slug}/tickets  - Ticket types
GET    /organizers             - List organizers
GET    /organizers/featured    - Featured organizers
GET    /organizers/{slug}      - Organizer profile
GET    /organizers/{slug}/events - Organizer's events
POST   /register               - Organizer registration
GET    /cart                   - Get cart
POST   /cart/add               - Add to cart
PUT    /cart/update            - Update cart item
DELETE /cart/remove            - Remove from cart
POST   /checkout/init          - Initialize checkout
POST   /checkout/complete      - Complete order
GET    /checkout/order/{id}/status - Order status
```

### Request Headers

```
X-Marketplace-Id: {marketplace_tenant_id}
X-Cart-Id: {cart_session_id}
```

## Services

### CommissionService

```php
use App\Services\Marketplace\CommissionService;

$service = app(CommissionService::class);

// Calculate for a single order
$breakdown = $service->calculateOrderCommission($order);

// Calculate period summary for organizer
$summary = $service->calculatePeriodSummary($organizer, $startDate, $endDate);
```

### PayoutService

```php
use App\Services\Marketplace\PayoutService;

$service = app(PayoutService::class);

// Generate payout for organizer
$payout = $service->generatePayout($organizer, $periodStart, $periodEnd);

// Process payout
$service->processPayout($payout, $transactionReference);
```

### OrganizerRegistrationService

```php
use App\Services\Marketplace\OrganizerRegistrationService;

$service = app(OrganizerRegistrationService::class);

// Register new organizer
$organizer = $service->register($marketplace, $organizerData, $userData);

// Approve organizer
$service->approve($organizer, $approvedBy);

// Suspend organizer
$service->suspend($organizer, $reason);
```

## Console Commands

```bash
# Generate payouts for organizers
php artisan marketplace:generate-payouts
php artisan marketplace:generate-payouts --marketplace=1
php artisan marketplace:generate-payouts --dry-run

# Refresh organizer statistics
php artisan marketplace:refresh-stats
php artisan marketplace:refresh-stats --organizer=5

# Send payout reminders
php artisan marketplace:send-payout-reminders --days=7

# Cleanup expired carts
php artisan marketplace:cleanup-carts
```

## Notifications

- `OrganizerRegistrationSubmitted` - Sent to marketplace owner when new organizer registers
- `OrganizerApproved` - Sent to organizer when approved
- `OrganizerSuspended` - Sent to organizer when suspended
- `PayoutReady` - Sent to organizer when payout is generated
- `PayoutProcessed` - Sent to organizer when payout is completed
- `NewOrganizerOrder` - Sent to organizer for new orders

## Migrations

Run migrations in order:

```bash
php artisan migrate
```

Migration files:
1. `add_marketplace_fields_to_tenants_table`
2. `create_marketplace_organizers_table`
3. `create_marketplace_organizer_users_table`
4. `add_organizer_id_to_events_table`
5. `create_marketplace_payouts_table`
6. `create_marketplace_payout_items_table`
7. `add_commission_fields_to_orders_table`

## Creating a Marketplace

### Via Admin Panel

1. Go to `/admin/marketplaces`
2. Click "Create Marketplace"
3. Fill in details:
   - Name and slug
   - Commission type and rates
   - Owner assignment
4. Save

### Via Code

```php
$marketplace = Tenant::create([
    'name' => 'My Marketplace',
    'slug' => 'my-marketplace',
    'tenant_type' => Tenant::TYPE_MARKETPLACE,
    'marketplace_commission_type' => 'percent',
    'marketplace_commission_percent' => 10.00,
]);
```

## Organizer Registration Flow

1. **Registration**: Organizer submits registration form on marketplace website
2. **Pending Approval**: Account created with `pending_approval` status
3. **Notification**: Marketplace owner notified
4. **Review**: Owner reviews in Tenant Panel
5. **Approval/Rejection**: Owner approves or rejects
6. **Active**: Organizer can now create events

## Payout Flow

1. **Order Paid**: Commission calculated and stored on order
2. **Revenue Accumulates**: `pending_payout` updated on organizer
3. **Payout Generated**: When minimum reached and frequency met
4. **Processing**: Marketplace processes payment
5. **Completed**: Orders marked as paid out

## Frontend Integration

The marketplace frontend should be a separate Vue.js/React SPA that consumes the Marketplace Client API.

### Bootstrap Example

```javascript
// On app load
const response = await fetch('/api/marketplace-client/bootstrap', {
  headers: {
    'X-Marketplace-Id': marketplaceId,
  }
});
const { marketplace, stats } = await response.json();
```

### Cart Example

```javascript
// Add to cart
await fetch('/api/marketplace-client/cart/add', {
  method: 'POST',
  headers: {
    'X-Marketplace-Id': marketplaceId,
    'X-Cart-Id': cartId,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    ticket_type_id: 123,
    quantity: 2,
  }),
});
```

## Security Considerations

1. **Organizer Isolation**: Organizers can only access their own data
2. **Role-Based Access**: Admin/Editor/Viewer roles in organizer teams
3. **Commission Lock**: Tixello 1% fee is hardcoded and cannot be modified
4. **Payout Verification**: Multi-step payout processing with audit trail

## Extending the System

### Adding Custom Commission Types

1. Add new type to `marketplace_commission_type` enum
2. Update `calculateCommissionAmount()` in Tenant model
3. Update Filament forms

### Adding New Payout Methods

1. Add to `payout_method` options
2. Implement payment provider integration
3. Update `PayoutService::processPayout()`

## Troubleshooting

### Organizer Can't Login

1. Check user exists in `marketplace_organizer_users`
2. Verify `is_active = true`
3. Ensure organizer status is `active`
4. Check authentication guard is `organizer`

### Commission Not Calculated

1. Verify order has `organizer_id` set
2. Check `calculateCommission()` was called after payment
3. Verify marketplace commission settings

### Payout Not Generated

1. Check `pending_payout` meets minimum
2. Verify payout frequency schedule
3. Run `marketplace:generate-payouts --dry-run` to debug

## File Structure

```
app/
├── Filament/
│   ├── Organizer/          # Organizer panel
│   │   ├── Pages/
│   │   ├── Resources/
│   │   └── Widgets/
│   ├── Resources/
│   │   └── Marketplace/    # Admin marketplace resources
│   └── Tenant/
│       └── Resources/
│           └── OrganizerResource.php
├── Http/
│   └── Controllers/
│       └── Api/
│           └── MarketplaceClient/
├── Models/
│   └── Marketplace/
│       ├── MarketplaceOrganizer.php
│       ├── MarketplaceOrganizerUser.php
│       ├── MarketplacePayout.php
│       └── MarketplacePayoutItem.php
├── Notifications/
│   └── Marketplace/
├── Providers/
│   └── Filament/
│       └── OrganizerPanelProvider.php
└── Services/
    └── Marketplace/
        ├── CommissionService.php
        ├── PayoutService.php
        └── OrganizerRegistrationService.php
```
