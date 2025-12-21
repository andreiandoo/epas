# Ticketing Platform (Ticketsweb) Registration Plan

## Executive Summary

This document outlines the implementation plan for adding a new entity type called **Ticketsweb** - a ticketing platform partner registration system. This is fundamentally different from the current Tenant model and enables a B2B2B2C marketplace structure.

---

## Current Architecture Analysis

### Existing Entity Hierarchy

```
Platform (EPAS/Tixello)
├── Users (super-admin, admin, editor, tenant roles)
├── Tenants (event organizers with their own websites)
│   ├── Events
│   ├── Venues
│   ├── Orders
│   ├── Tickets
│   └── Customers (end-users who buy tickets)
```

### Key Differences: Tenant vs Ticketsweb

| Aspect | Tenant | Ticketsweb |
|--------|--------|------------|
| Purpose | Individual event organizer | Ticketing platform partner |
| Website | Generated SPA per domain | Uses own existing website |
| Dashboard | Single tenant panel | Multi-organiser management |
| Users | Owner + editors | Platform admins + Event Organisers |
| Customers | Direct relationship | Shared across organisers |
| Events | Own events only | Events from multiple organisers |
| Branding | Per-tenant theming | API-based integration |

---

## Proposed Architecture

### New Entity Hierarchy

```
Platform (EPAS/Tixello)
├── Users (super-admin, admin, editor, tenant roles)
├── Tenants (existing - unchanged)
│   └── ... (existing structure)
│
└── TicketswebPlatforms (NEW)
    ├── TicketswebUsers (platform admins)
    ├── EventOrganisers (like mini-tenants within ticketsweb)
    │   ├── OrganiserUsers (organiser staff)
    │   ├── Events
    │   ├── Venues
    │   └── Revenue/Analytics
    ├── Customers (shared customer pool)
    ├── Orders
    ├── Tickets
    └── Platform Analytics
```

---

## Implementation Plan

### Phase 1: Database Foundation

#### 1.1 Core Tables

**`ticketsweb_platforms` table** - Main ticketing platform entity
```php
Schema::create('ticketsweb_platforms', function (Blueprint $table) {
    $table->id();
    $table->string('name');                          // Platform name (e.g., "TicketsWeb Romania")
    $table->string('slug')->unique();                // URL-safe identifier
    $table->string('status')->default('pending');    // pending, active, suspended, closed

    // Company Details
    $table->string('company_name')->nullable();
    $table->string('cui')->nullable();               // Tax ID
    $table->string('reg_com')->nullable();           // Registration number
    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('country')->default('RO');

    // Contact Information
    $table->string('contact_email');
    $table->string('contact_phone')->nullable();
    $table->string('website_url')->nullable();       // Their existing ticketing website

    // API Configuration
    $table->string('api_key')->unique()->nullable();
    $table->string('api_secret')->nullable();
    $table->json('api_settings')->nullable();        // Rate limits, allowed endpoints, etc.
    $table->json('webhook_urls')->nullable();        // Callbacks for events

    // Billing & Commission
    $table->decimal('commission_rate', 5, 2)->default(5.00);  // Platform commission %
    $table->string('billing_cycle')->default('monthly');
    $table->date('billing_starts_at')->nullable();
    $table->date('next_billing_date')->nullable();
    $table->string('payment_processor')->nullable();
    $table->string('currency')->default('RON');

    // Features & Settings
    $table->json('features')->nullable();            // Enabled features/modules
    $table->json('settings')->nullable();            // Platform-specific settings

    // Contract
    $table->string('contract_status')->default('draft');
    $table->timestamp('contract_signed_at')->nullable();
    $table->string('contract_signed_ip')->nullable();

    $table->foreignId('owner_id')->constrained('users');
    $table->timestamps();
    $table->softDeletes();
});
```

**`ticketsweb_event_organisers` table** - Event organisers within a ticketsweb platform
```php
Schema::create('ticketsweb_event_organisers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticketsweb_platform_id')->constrained()->cascadeOnDelete();

    $table->string('name');
    $table->string('slug');
    $table->string('status')->default('active');     // active, suspended, closed

    // Company Details
    $table->string('company_name')->nullable();
    $table->string('cui')->nullable();
    $table->string('reg_com')->nullable();
    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('country')->default('RO');

    // Contact
    $table->string('contact_email');
    $table->string('contact_phone')->nullable();
    $table->string('logo')->nullable();

    // Commission (can override platform default)
    $table->decimal('commission_rate', 5, 2)->nullable();

    // Settings
    $table->json('settings')->nullable();
    $table->json('features')->nullable();

    // Billing
    $table->string('payout_method')->nullable();     // bank_transfer, paypal, etc.
    $table->json('payout_details')->nullable();      // Bank account, etc.

    $table->timestamps();
    $table->softDeletes();

    $table->unique(['ticketsweb_platform_id', 'slug']);
});
```

**`ticketsweb_users` table** - Users who manage ticketsweb platforms/organisers
```php
Schema::create('ticketsweb_users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticketsweb_platform_id')->constrained()->cascadeOnDelete();
    $table->foreignId('organiser_id')->nullable()->constrained('ticketsweb_event_organisers')->nullOnDelete();

    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->string('phone')->nullable();
    $table->string('role');                          // platform_admin, platform_editor, organiser_admin, organiser_editor
    $table->string('avatar')->nullable();

    $table->boolean('is_active')->default(true);
    $table->timestamp('last_login_at')->nullable();
    $table->rememberToken();

    $table->timestamps();
    $table->softDeletes();
});
```

#### 1.2 Relationship Tables

**`ticketsweb_events` table** - Events linked to organisers
```php
Schema::create('ticketsweb_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticketsweb_platform_id')->constrained()->cascadeOnDelete();
    $table->foreignId('organiser_id')->constrained('ticketsweb_event_organisers')->cascadeOnDelete();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();

    // Ticketsweb-specific event settings
    $table->decimal('platform_commission', 5, 2)->nullable();  // Override if needed
    $table->json('external_data')->nullable();                  // Data from their system
    $table->string('external_id')->nullable();                  // ID in their system

    $table->timestamps();

    $table->unique(['ticketsweb_platform_id', 'event_id']);
});
```

**`ticketsweb_customers` table** - Customer-platform associations
```php
Schema::create('ticketsweb_customers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticketsweb_platform_id')->constrained()->cascadeOnDelete();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

    $table->string('external_customer_id')->nullable();  // ID in their system
    $table->json('external_data')->nullable();
    $table->timestamp('first_purchase_at')->nullable();

    $table->timestamps();

    $table->unique(['ticketsweb_platform_id', 'customer_id']);
});
```

**`ticketsweb_orders` table** - Orders via ticketsweb platforms
```php
Schema::create('ticketsweb_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticketsweb_platform_id')->constrained()->cascadeOnDelete();
    $table->foreignId('organiser_id')->constrained('ticketsweb_event_organisers');
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();

    $table->string('external_order_id')->nullable();    // ID in their system
    $table->json('external_data')->nullable();

    // Commission tracking
    $table->decimal('platform_commission_amount', 10, 2)->nullable();
    $table->decimal('organiser_payout_amount', 10, 2)->nullable();

    $table->timestamps();

    $table->unique(['ticketsweb_platform_id', 'order_id']);
});
```

---

### Phase 2: Models & Relationships

#### 2.1 Core Models

**`TicketswebPlatform` Model**
```php
class TicketswebPlatform extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $casts = [
        'features' => 'array',
        'settings' => 'array',
        'api_settings' => 'array',
        'webhook_urls' => 'array',
        'billing_starts_at' => 'date',
        'next_billing_date' => 'date',
        'contract_signed_at' => 'datetime',
        'commission_rate' => 'decimal:2',
    ];

    // Relationships
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function organisers() { return $this->hasMany(TicketswebEventOrganiser::class); }
    public function users() { return $this->hasMany(TicketswebUser::class); }
    public function events() { return $this->hasManyThrough(Event::class, TicketswebEvent::class); }
    public function customers() { return $this->belongsToMany(Customer::class, 'ticketsweb_customers'); }
    public function orders() { return $this->hasManyThrough(Order::class, TicketswebOrder::class); }

    // Scopes
    public function scopeActive($query) { return $query->where('status', 'active'); }

    // Helpers
    public function generateApiCredentials() { ... }
    public function getEffectiveCommissionRate($organiser = null) { ... }
}
```

**`TicketswebEventOrganiser` Model**
```php
class TicketswebEventOrganiser extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $casts = [
        'settings' => 'array',
        'features' => 'array',
        'payout_details' => 'array',
        'commission_rate' => 'decimal:2',
    ];

    // Relationships
    public function platform() { return $this->belongsTo(TicketswebPlatform::class, 'ticketsweb_platform_id'); }
    public function users() { return $this->hasMany(TicketswebUser::class, 'organiser_id'); }
    public function events() { return $this->hasMany(TicketswebEvent::class, 'organiser_id'); }
    public function orders() { return $this->hasMany(TicketswebOrder::class, 'organiser_id'); }

    // Helpers
    public function getCommissionRate() {
        return $this->commission_rate ?? $this->platform->commission_rate;
    }
}
```

**`TicketswebUser` Model**
```php
class TicketswebUser extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $guard = 'ticketsweb';

    // Roles
    const ROLE_PLATFORM_ADMIN = 'platform_admin';
    const ROLE_PLATFORM_EDITOR = 'platform_editor';
    const ROLE_ORGANISER_ADMIN = 'organiser_admin';
    const ROLE_ORGANISER_EDITOR = 'organiser_editor';

    // Relationships
    public function platform() { return $this->belongsTo(TicketswebPlatform::class, 'ticketsweb_platform_id'); }
    public function organiser() { return $this->belongsTo(TicketswebEventOrganiser::class, 'organiser_id'); }

    // Authorization helpers
    public function isPlatformAdmin() { return $this->role === self::ROLE_PLATFORM_ADMIN; }
    public function isPlatformUser() { return in_array($this->role, [self::ROLE_PLATFORM_ADMIN, self::ROLE_PLATFORM_EDITOR]); }
    public function isOrganiserUser() { return in_array($this->role, [self::ROLE_ORGANISER_ADMIN, self::ROLE_ORGANISER_EDITOR]); }

    // Panel access
    public function canAccessPanel(Panel $panel): bool {
        if ($panel->getId() === 'ticketsweb') {
            return $this->isPlatformUser();
        }
        if ($panel->getId() === 'organiser') {
            return $this->isOrganiserUser() && $this->organiser_id !== null;
        }
        return false;
    }
}
```

---

### Phase 3: Authentication System

#### 3.1 Auth Configuration

**Add new guard in `config/auth.php`:**
```php
'guards' => [
    // ... existing guards
    'ticketsweb' => [
        'driver' => 'session',
        'provider' => 'ticketsweb_users',
    ],
],

'providers' => [
    // ... existing providers
    'ticketsweb_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\TicketswebUser::class,
    ],
],
```

#### 3.2 Registration Flow

**Public Registration Controller:**
```php
class TicketswebRegistrationController extends Controller
{
    public function showRegistrationForm()
    {
        return view('ticketsweb.register');
    }

    public function register(TicketswebRegistrationRequest $request)
    {
        DB::transaction(function () use ($request) {
            // Create platform
            $platform = TicketswebPlatform::create([
                'name' => $request->platform_name,
                'slug' => Str::slug($request->platform_name),
                'status' => 'pending',  // Requires admin approval
                'company_name' => $request->company_name,
                'cui' => $request->cui,
                'contact_email' => $request->email,
                'contact_phone' => $request->phone,
                'website_url' => $request->website_url,
                'owner_id' => null,  // Set after user creation
            ]);

            // Create platform admin user
            $user = TicketswebUser::create([
                'ticketsweb_platform_id' => $platform->id,
                'name' => $request->contact_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'role' => TicketswebUser::ROLE_PLATFORM_ADMIN,
            ]);

            // Update platform owner
            $platform->update(['owner_id' => $user->id]);

            // Generate API credentials (inactive until approved)
            $platform->generateApiCredentials();

            // Notify admins for approval
            Notification::send(
                User::where('role', 'super-admin')->get(),
                new NewTicketswebRegistration($platform)
            );
        });

        return redirect()->route('ticketsweb.registration.pending')
            ->with('success', 'Registration submitted. Awaiting approval.');
    }
}
```

---

### Phase 4: Filament Panels

#### 4.1 Admin Panel Extensions

**New Admin Resource: `TicketswebPlatformResource`**
- List all ticketsweb platforms
- Approve/reject pending registrations
- Manage platform settings
- View analytics across all platforms
- Configure commission rates
- Manage contracts

**Admin Dashboard Widgets:**
- Ticketsweb platforms overview
- Pending approvals
- Revenue from ticketsweb platforms
- Top performing platforms

#### 4.2 Ticketsweb Platform Panel (NEW)

**Panel Configuration:**
```php
class TicketswebPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('ticketsweb')
            ->path('ticketsweb')
            ->login(TicketswebLogin::class)
            ->authGuard('ticketsweb')
            ->colors(['primary' => Color::Indigo])
            ->navigation(function (NavigationBuilder $builder) {
                return $builder
                    ->groups([
                        NavigationGroup::make('Dashboard'),
                        NavigationGroup::make('Event Organisers'),
                        NavigationGroup::make('Events'),
                        NavigationGroup::make('Orders & Tickets'),
                        NavigationGroup::make('Customers'),
                        NavigationGroup::make('Analytics'),
                        NavigationGroup::make('API & Integration'),
                        NavigationGroup::make('Settings'),
                    ]);
            });
    }
}
```

**Ticketsweb Panel Resources:**
1. **Dashboard** - Platform overview, key metrics
2. **OrganiserResource** - Manage event organisers
3. **EventResource** - View/manage all events across organisers
4. **OrderResource** - All orders with filtering by organiser
5. **CustomerResource** - Shared customer database
6. **AnalyticsPage** - Platform-wide analytics
7. **ApiSettingsPage** - API keys, webhooks, integration docs
8. **SettingsPage** - Platform settings, billing

#### 4.3 Event Organiser Panel (NEW)

**Panel Configuration:**
```php
class OrganiserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('organiser')
            ->path('organiser')
            ->login(OrganiserLogin::class)
            ->authGuard('ticketsweb')  // Same guard, different role
            ->tenant(TicketswebEventOrganiser::class)
            ->colors(['primary' => Color::Emerald])
            ->navigation(function (NavigationBuilder $builder) {
                return $builder
                    ->groups([
                        NavigationGroup::make('Dashboard'),
                        NavigationGroup::make('Events'),
                        NavigationGroup::make('Orders'),
                        NavigationGroup::make('Revenue'),
                        NavigationGroup::make('Settings'),
                    ]);
            });
    }
}
```

**Organiser Panel Resources:**
1. **Dashboard** - Organiser-specific metrics
2. **EventResource** - Create/manage organiser's events
3. **OrderResource** - Organiser's orders only
4. **RevenueReport** - Earnings, payouts, commission breakdown
5. **SettingsPage** - Company details, payout settings

---

### Phase 5: API System

#### 5.1 API Routes

```php
// routes/api.php

Route::prefix('ticketsweb/v1')->middleware(['ticketsweb.api'])->group(function () {
    // Authentication
    Route::post('/auth/token', [TicketswebApiAuthController::class, 'token']);
    Route::post('/auth/refresh', [TicketswebApiAuthController::class, 'refresh']);

    // Event Organisers
    Route::apiResource('organisers', TicketswebOrganiserApiController::class);

    // Events
    Route::apiResource('events', TicketswebEventApiController::class);
    Route::post('/events/{event}/publish', [TicketswebEventApiController::class, 'publish']);
    Route::post('/events/{event}/unpublish', [TicketswebEventApiController::class, 'unpublish']);

    // Ticket Types
    Route::apiResource('events/{event}/ticket-types', TicketswebTicketTypeApiController::class);

    // Orders
    Route::post('/orders/create', [TicketswebOrderApiController::class, 'create']);
    Route::get('/orders/{order}', [TicketswebOrderApiController::class, 'show']);
    Route::post('/orders/{order}/confirm', [TicketswebOrderApiController::class, 'confirm']);
    Route::post('/orders/{order}/cancel', [TicketswebOrderApiController::class, 'cancel']);

    // Tickets
    Route::get('/tickets/{ticket}', [TicketswebTicketApiController::class, 'show']);
    Route::get('/tickets/{ticket}/validate', [TicketswebTicketApiController::class, 'validate']);
    Route::post('/tickets/{ticket}/check-in', [TicketswebTicketApiController::class, 'checkIn']);

    // Customers
    Route::apiResource('customers', TicketswebCustomerApiController::class);
    Route::get('/customers/{customer}/orders', [TicketswebCustomerApiController::class, 'orders']);

    // Venues
    Route::apiResource('venues', TicketswebVenueApiController::class);

    // Analytics
    Route::get('/analytics/sales', [TicketswebAnalyticsApiController::class, 'sales']);
    Route::get('/analytics/events', [TicketswebAnalyticsApiController::class, 'events']);
    Route::get('/analytics/customers', [TicketswebAnalyticsApiController::class, 'customers']);

    // Webhooks
    Route::get('/webhooks', [TicketswebWebhookApiController::class, 'list']);
    Route::post('/webhooks', [TicketswebWebhookApiController::class, 'register']);
    Route::delete('/webhooks/{id}', [TicketswebWebhookApiController::class, 'delete']);
});
```

#### 5.2 API Authentication Middleware

```php
class TicketswebApiMiddleware
{
    public function handle($request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');
        $signature = $request->header('X-API-Signature');

        if (!$apiKey) {
            return response()->json(['error' => 'API key required'], 401);
        }

        $platform = TicketswebPlatform::where('api_key', $apiKey)
            ->where('status', 'active')
            ->first();

        if (!$platform) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Verify signature if required
        if ($platform->api_settings['require_signature'] ?? false) {
            if (!$this->verifySignature($request, $platform->api_secret, $signature)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        // Rate limiting
        $rateLimiter = app(RateLimiter::class);
        $key = 'ticketsweb:' . $platform->id;
        $maxAttempts = $platform->api_settings['rate_limit'] ?? 1000;

        if ($rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }
        $rateLimiter->hit($key, 60);

        // Attach platform to request
        $request->merge(['ticketsweb_platform' => $platform]);

        return $next($request);
    }
}
```

#### 5.3 Webhook System

```php
class TicketswebWebhookService
{
    public function dispatch(TicketswebPlatform $platform, string $event, array $payload)
    {
        $webhooks = $platform->webhook_urls[$event] ?? [];

        foreach ($webhooks as $url) {
            dispatch(new SendTicketswebWebhook($platform, $url, $event, $payload));
        }
    }
}

// Supported webhook events:
// - order.created
// - order.paid
// - order.cancelled
// - order.refunded
// - ticket.checked_in
// - event.created
// - event.updated
// - event.sold_out
// - customer.created
```

---

### Phase 6: Services Layer

#### 6.1 Core Services

**`TicketswebPlatformService`**
- Platform registration & approval
- API credential management
- Settings management
- Billing & invoicing

**`TicketswebOrganiserService`**
- Organiser CRUD operations
- Commission calculations
- Payout processing
- Access management

**`TicketswebOrderService`**
- Order creation via API
- Commission splitting
- Revenue tracking
- Refund processing

**`TicketswebAnalyticsService`**
- Platform-wide metrics
- Per-organiser analytics
- Revenue reports
- Customer insights

---

### Phase 7: Key Differences from Tenant

| Feature | Tenant Implementation | Ticketsweb Implementation |
|---------|----------------------|---------------------------|
| **Website** | Generated Vue SPA | API-only (no website generation) |
| **Domains** | Custom domains per tenant | N/A - uses their own website |
| **Events** | Tenant owns directly | Events linked via organiser |
| **Customers** | Per-tenant with sharing | Shared across platform |
| **Authentication** | Web-based (Filament) | API + separate dashboard |
| **Commission** | Platform takes from tenant | Platform takes from ticketsweb |
| **Billing** | Direct to tenant | To ticketsweb platform |
| **Payouts** | N/A (tenant keeps revenue) | Ticketsweb → Organisers |

---

## File Structure

```
app/
├── Models/
│   ├── Ticketsweb/
│   │   ├── TicketswebPlatform.php
│   │   ├── TicketswebEventOrganiser.php
│   │   ├── TicketswebUser.php
│   │   ├── TicketswebEvent.php
│   │   ├── TicketswebCustomer.php
│   │   └── TicketswebOrder.php
│   └── ...
├── Filament/
│   ├── Ticketsweb/                    # Platform admin panel
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── ApiSettings.php
│   │   │   └── Analytics.php
│   │   └── Resources/
│   │       ├── OrganiserResource.php
│   │       ├── EventResource.php
│   │       ├── OrderResource.php
│   │       └── CustomerResource.php
│   ├── Organiser/                     # Event organiser panel
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   └── Revenue.php
│   │   └── Resources/
│   │       ├── EventResource.php
│   │       └── OrderResource.php
│   └── Resources/
│       └── TicketswebPlatformResource.php  # Admin management
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── Ticketsweb/
│   │   │       ├── AuthController.php
│   │   │       ├── EventController.php
│   │   │       ├── OrderController.php
│   │   │       ├── CustomerController.php
│   │   │       └── WebhookController.php
│   │   └── Ticketsweb/
│   │       └── RegistrationController.php
│   └── Middleware/
│       └── TicketswebApiMiddleware.php
├── Services/
│   └── Ticketsweb/
│       ├── TicketswebPlatformService.php
│       ├── TicketswebOrganiserService.php
│       ├── TicketswebOrderService.php
│       ├── TicketswebAnalyticsService.php
│       └── TicketswebWebhookService.php
├── Providers/
│   ├── TicketswebPanelProvider.php
│   └── OrganiserPanelProvider.php
└── Notifications/
    └── Ticketsweb/
        ├── NewTicketswebRegistration.php
        ├── PlatformApproved.php
        └── OrganiserInvitation.php

database/
└── migrations/
    ├── xxxx_create_ticketsweb_platforms_table.php
    ├── xxxx_create_ticketsweb_event_organisers_table.php
    ├── xxxx_create_ticketsweb_users_table.php
    ├── xxxx_create_ticketsweb_events_table.php
    ├── xxxx_create_ticketsweb_customers_table.php
    └── xxxx_create_ticketsweb_orders_table.php

resources/
└── views/
    └── ticketsweb/
        ├── register.blade.php
        ├── registration-pending.blade.php
        └── emails/
            ├── registration-received.blade.php
            └── platform-approved.blade.php

routes/
├── api.php           # Add ticketsweb API routes
├── web.php           # Add registration routes
└── ticketsweb.php    # Dedicated ticketsweb routes (optional)

config/
└── ticketsweb.php    # Ticketsweb-specific configuration
```

---

## Implementation Order

### Sprint 1: Foundation (Core Database & Models)
1. Create migration files for all tables
2. Create Eloquent models with relationships
3. Configure authentication guards
4. Add seeders for testing

### Sprint 2: Admin Management
1. Create `TicketswebPlatformResource` for admin panel
2. Add approval workflow
3. Add admin dashboard widgets
4. Create notification system for registrations

### Sprint 3: Registration & Authentication
1. Build public registration form
2. Create registration controller & service
3. Implement email notifications
4. Build login pages for both panels

### Sprint 4: Ticketsweb Panel
1. Create `TicketswebPanelProvider`
2. Build dashboard with metrics
3. Create OrganiserResource
4. Create EventResource (read-only or limited)
5. Create OrderResource
6. Create CustomerResource
7. Build API settings page

### Sprint 5: Organiser Panel
1. Create `OrganiserPanelProvider`
2. Build organiser dashboard
3. Create event management (create/edit)
4. Create order viewing
5. Build revenue/payout reports

### Sprint 6: API System
1. Create API middleware
2. Implement API authentication
3. Build all API endpoints
4. Implement webhook system
5. Create API documentation

### Sprint 7: Financial System
1. Commission calculation logic
2. Platform billing
3. Organiser payouts
4. Revenue reports

### Sprint 8: Testing & Polish
1. Unit tests for all services
2. Feature tests for API endpoints
3. Integration tests for panels
4. Documentation

---

## Security Considerations

1. **API Security**
   - HMAC signature verification for sensitive operations
   - Rate limiting per platform
   - IP whitelisting option
   - Request logging for audit

2. **Data Isolation**
   - Strict platform-level scoping
   - Organiser-level data isolation
   - Middleware enforcement

3. **Access Control**
   - Role-based permissions
   - Resource policies
   - Audit logging

4. **PCI Compliance**
   - Payment data handled by processor
   - No card storage in our system
   - Secure webhook signatures

---

## Assessment Summary

### Complexity: HIGH
This is a significant architectural addition requiring:
- 6 new database tables
- 6 new Eloquent models
- 2 new Filament panels
- 15+ API endpoints
- New authentication system
- Webhook infrastructure

### Estimated Effort
- **Database & Models**: 2-3 days
- **Admin Panel Extensions**: 2-3 days
- **Ticketsweb Panel**: 4-5 days
- **Organiser Panel**: 3-4 days
- **API System**: 4-5 days
- **Financial Logic**: 2-3 days
- **Testing**: 3-4 days
- **Total**: ~25-30 development days

### Dependencies
- Existing Event, Venue, Order, Ticket, Customer models
- Filament admin framework
- Laravel authentication system
- Stripe/payment processors

### Risks
1. **Complexity creep** - Clear boundaries needed between tenant and ticketsweb
2. **Data isolation** - Must ensure proper scoping at all levels
3. **API stability** - Versioned API required from start
4. **Performance** - Analytics queries need optimization

### Recommendations
1. Start with database migrations and models first
2. Build admin management before public-facing features
3. Use feature flags to gradually enable functionality
4. Create comprehensive API documentation early
5. Consider using GraphQL for more flexible API (optional)
