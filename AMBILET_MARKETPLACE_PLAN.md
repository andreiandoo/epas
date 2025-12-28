# Ambilet Marketplace Admin Dashboard - Implementation Plan

## Current State Analysis

After reviewing the `core-main` branch, the following components **already exist**:

### Existing Backend (Laravel)

#### Marketplace Models (`app/Models/`)
- `MarketplaceClient` - The marketplace entity (ambilet)
- `MarketplaceAdmin` - Admin users with roles & permissions
- `MarketplaceOrganizer` - Event organizers
- `MarketplaceCustomer` - Ticket buyers
- `MarketplaceEvent` - Organizer-created events
- `MarketplaceTicketType` - Ticket types for events
- `MarketplacePayout` - Organizer payouts
- `MarketplaceTransaction` - Financial transactions
- `MarketplaceCart` - Shopping carts
- `MarketplaceTicketTransfer` - Ticket transfers between customers
- `MarketplaceOrganizerPromoCode` - Promo codes
- `MarketplacePromoCodeUsage` - Promo code usage tracking

#### Marketplace Admin Controllers (`app/Http/Controllers/Api/MarketplaceClient/Admin/`)
- `AuthController` - Login, logout, password reset
- `DashboardController` - Stats, timeline, activity, top organizers/events
- `EventsController` - Approve, reject, feature, suspend events
- `OrganizersController` - Approve, verify, suspend, manage commission
- `PayoutsController` - Process payout requests
- `SettingsController` - Marketplace settings, webhooks, API credentials

#### API Routes (already defined in `routes/api.php`)
- `POST /api/marketplace-client/admin/login` - Admin login
- `GET /api/marketplace-client/admin/dashboard` - Dashboard stats
- `GET /api/marketplace-client/admin/events` - List events
- `GET /api/marketplace-client/admin/organizers` - List organizers
- `GET /api/marketplace-client/admin/payouts` - List payouts
- And 50+ more admin endpoints...

#### Core Models (shared, can be used by marketplace)
- `Artist` - With social stats, genres, types
- `Venue` - With facilities, categories, types, seating
- `EventType`, `EventGenre` - Event classification
- `Affiliate`, `AffiliateLink`, `AffiliateConversion` - Affiliate system
- `Coupon/*` - Coupon system
- `Gamification/*` - Points, referrals
- `Shop/*` - Online shop products
- And 100+ more models...

---

## What Needs To Be Built

Based on your requirements, here's what's **missing**:

### 1. Backend Additions (Priority)

#### 1.1 New Admin Controllers

```
app/Http/Controllers/Api/MarketplaceClient/Admin/
├── TicketsController.php       # View all tickets, check-in history, void tickets
├── CustomersController.php     # List, view, block customers
├── OrdersController.php        # List, view, refund orders (extend existing)
├── ReportsController.php       # Sales, tickets, customers, financial reports
├── UsersController.php         # Manage admin staff (exists but needs extension)
├── CategoriesController.php    # Custom event categories for marketplace
├── ArtistsController.php       # View/add artists (ownership-based)
├── VenuesController.php        # View/add venues (ownership-based)
└── MicroservicesController.php # Access enabled microservices
```

#### 1.2 New Database Migrations

```php
// Marketplace Event Categories
Schema::create('marketplace_event_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('marketplace_event_categories');
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();
    $table->string('icon')->nullable();      // Icon name or emoji
    $table->string('image')->nullable();     // Category image
    $table->string('color')->nullable();     // Brand color
    $table->integer('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->boolean('is_featured')->default(false);
    $table->timestamps();

    $table->unique(['marketplace_client_id', 'slug']);
});

// Artist ownership for marketplace
Schema::create('marketplace_artists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
    $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
    $table->string('ownership')->default('shared'); // owned, shared
    $table->timestamps();

    $table->unique(['marketplace_client_id', 'artist_id']);
});

// Venue ownership for marketplace
Schema::create('marketplace_venues', function (Blueprint $table) {
    $table->id();
    $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
    $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
    $table->string('ownership')->default('shared'); // owned, shared
    $table->timestamps();

    $table->unique(['marketplace_client_id', 'venue_id']);
});

// Marketplace enabled microservices
Schema::create('marketplace_microservices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
    $table->foreignId('microservice_id')->constrained()->cascadeOnDelete();
    $table->boolean('is_enabled')->default(true);
    $table->json('settings')->nullable();
    $table->timestamps();

    $table->unique(['marketplace_client_id', 'microservice_id']);
});

// Multiple payment gateways per marketplace
Schema::create('marketplace_payment_gateways', function (Blueprint $table) {
    $table->id();
    $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
    $table->string('gateway_code'); // netopia, stripe, paypal
    $table->string('name');
    $table->string('platform'); // web, app, pos, all
    $table->json('credentials'); // encrypted
    $table->string('mode')->default('sandbox'); // sandbox, live
    $table->boolean('is_active')->default(true);
    $table->boolean('is_default')->default(false);
    $table->decimal('fee_percentage', 5, 2)->default(0);
    $table->decimal('fee_fixed', 10, 2)->default(0);
    $table->timestamps();

    $table->index(['marketplace_client_id', 'platform', 'is_active']);
});
```

#### 1.3 New API Routes

```php
// In routes/api.php - add to marketplace admin group

// Tickets Management
Route::get('admin/tickets', [TicketsController::class, 'index']);
Route::get('admin/tickets/{ticket}', [TicketsController::class, 'show']);
Route::post('admin/tickets/{ticket}/void', [TicketsController::class, 'void']);
Route::get('admin/tickets/{ticket}/history', [TicketsController::class, 'history']);

// Customers Management
Route::get('admin/customers', [CustomersController::class, 'index']);
Route::get('admin/customers/{customer}', [CustomersController::class, 'show']);
Route::put('admin/customers/{customer}', [CustomersController::class, 'update']);
Route::post('admin/customers/{customer}/block', [CustomersController::class, 'block']);
Route::post('admin/customers/{customer}/unblock', [CustomersController::class, 'unblock']);
Route::get('admin/customers/{customer}/orders', [CustomersController::class, 'orders']);
Route::get('admin/customers/{customer}/tickets', [CustomersController::class, 'tickets']);

// Orders Management (extends existing)
Route::get('admin/orders', [OrdersController::class, 'index']);
Route::get('admin/orders/{order}', [OrdersController::class, 'show']);
Route::post('admin/orders/{order}/refund', [OrdersController::class, 'refund']);
Route::post('admin/orders/{order}/resend', [OrdersController::class, 'resendTickets']);

// Reports
Route::get('admin/reports/sales', [ReportsController::class, 'sales']);
Route::get('admin/reports/tickets', [ReportsController::class, 'tickets']);
Route::get('admin/reports/customers', [ReportsController::class, 'customers']);
Route::get('admin/reports/organizers', [ReportsController::class, 'organizers']);
Route::get('admin/reports/financial', [ReportsController::class, 'financial']);
Route::get('admin/reports/export/{type}', [ReportsController::class, 'export']);

// Staff Users (extend existing AuthController)
Route::get('admin/users', [UsersController::class, 'index']);
Route::post('admin/users', [UsersController::class, 'store']);
Route::put('admin/users/{user}', [UsersController::class, 'update']);
Route::delete('admin/users/{user}', [UsersController::class, 'destroy']);
Route::put('admin/users/{user}/permissions', [UsersController::class, 'updatePermissions']);

// Event Categories
Route::get('admin/categories', [CategoriesController::class, 'index']);
Route::post('admin/categories', [CategoriesController::class, 'store']);
Route::put('admin/categories/{category}', [CategoriesController::class, 'update']);
Route::delete('admin/categories/{category}', [CategoriesController::class, 'destroy']);
Route::put('admin/categories/reorder', [CategoriesController::class, 'reorder']);

// Artists (with ownership)
Route::get('admin/artists', [ArtistsController::class, 'index']);
Route::post('admin/artists', [ArtistsController::class, 'store']);
Route::get('admin/artists/{artist}', [ArtistsController::class, 'show']);
Route::put('admin/artists/{artist}', [ArtistsController::class, 'update']); // only own
Route::delete('admin/artists/{artist}', [ArtistsController::class, 'destroy']); // only own

// Venues (with ownership)
Route::get('admin/venues', [VenuesController::class, 'index']);
Route::post('admin/venues', [VenuesController::class, 'store']);
Route::get('admin/venues/{venue}', [VenuesController::class, 'show']);
Route::put('admin/venues/{venue}', [VenuesController::class, 'update']); // only own
Route::delete('admin/venues/{venue}', [VenuesController::class, 'destroy']); // only own
Route::get('admin/venues/{venue}/seating', [VenuesController::class, 'seating']);

// Payment Gateways
Route::get('admin/payment-gateways', [PaymentGatewaysController::class, 'index']);
Route::post('admin/payment-gateways', [PaymentGatewaysController::class, 'store']);
Route::put('admin/payment-gateways/{gateway}', [PaymentGatewaysController::class, 'update']);
Route::delete('admin/payment-gateways/{gateway}', [PaymentGatewaysController::class, 'destroy']);
Route::post('admin/payment-gateways/{gateway}/test', [PaymentGatewaysController::class, 'test']);

// Microservices
Route::get('admin/microservices', [MicroservicesController::class, 'index']);
Route::put('admin/microservices/{code}/toggle', [MicroservicesController::class, 'toggle']);
Route::put('admin/microservices/{code}/settings', [MicroservicesController::class, 'updateSettings']);
```

---

### 2. Admin Dashboard Frontend (Next.js)

This is the main deliverable - a complete admin dashboard.

#### 2.1 Technology Stack

```
Framework:     Next.js 14 (App Router)
Language:      TypeScript
Styling:       Tailwind CSS + shadcn/ui
State:         Zustand
Forms:         React Hook Form + Zod
API:           TanStack Query (React Query)
Charts:        Recharts
Tables:        TanStack Table
```

#### 2.2 Folder Structure

```
marketplace-clients/ambilet/admin/
├── app/
│   ├── (auth)/
│   │   ├── login/page.tsx
│   │   └── forgot-password/page.tsx
│   ├── (dashboard)/
│   │   ├── layout.tsx                 # Dashboard layout with sidebar
│   │   ├── page.tsx                   # Main dashboard
│   │   ├── events/
│   │   │   ├── page.tsx               # Events list
│   │   │   ├── pending/page.tsx       # Pending approval
│   │   │   └── [id]/page.tsx          # Event details
│   │   ├── orders/
│   │   │   ├── page.tsx               # Orders list
│   │   │   └── [id]/page.tsx          # Order details
│   │   ├── tickets/
│   │   │   ├── page.tsx               # Tickets list
│   │   │   └── [id]/page.tsx          # Ticket details
│   │   ├── organizers/
│   │   │   ├── page.tsx               # Organizers list
│   │   │   ├── pending/page.tsx       # Pending approval
│   │   │   └── [id]/page.tsx          # Organizer details
│   │   ├── customers/
│   │   │   ├── page.tsx               # Customers list
│   │   │   └── [id]/page.tsx          # Customer details
│   │   ├── payouts/
│   │   │   ├── page.tsx               # Payouts list
│   │   │   └── [id]/page.tsx          # Payout details
│   │   ├── reports/
│   │   │   ├── page.tsx               # Reports overview
│   │   │   ├── sales/page.tsx
│   │   │   ├── tickets/page.tsx
│   │   │   ├── customers/page.tsx
│   │   │   └── financial/page.tsx
│   │   ├── artists/
│   │   │   ├── page.tsx               # Artists list
│   │   │   ├── create/page.tsx
│   │   │   └── [id]/page.tsx          # Artist details/edit
│   │   ├── venues/
│   │   │   ├── page.tsx               # Venues list
│   │   │   ├── create/page.tsx
│   │   │   └── [id]/page.tsx          # Venue details/edit
│   │   ├── categories/
│   │   │   └── page.tsx               # Event categories
│   │   ├── users/
│   │   │   └── page.tsx               # Staff users
│   │   ├── microservices/
│   │   │   └── page.tsx               # Enabled microservices
│   │   ├── payments/
│   │   │   └── page.tsx               # Payment gateways
│   │   └── settings/
│   │       ├── page.tsx               # General settings
│   │       ├── branding/page.tsx
│   │       └── webhooks/page.tsx
│   └── layout.tsx
├── components/
│   ├── ui/                            # shadcn/ui components
│   ├── layout/
│   │   ├── Sidebar.tsx
│   │   ├── Header.tsx
│   │   └── Breadcrumbs.tsx
│   ├── dashboard/
│   │   ├── StatsCards.tsx
│   │   ├── RevenueChart.tsx
│   │   ├── ActivityFeed.tsx
│   │   └── TopOrganizers.tsx
│   ├── tables/
│   │   ├── EventsTable.tsx
│   │   ├── OrdersTable.tsx
│   │   ├── TicketsTable.tsx
│   │   └── ...
│   └── forms/
│       ├── CategoryForm.tsx
│       ├── ArtistForm.tsx
│       └── ...
├── lib/
│   ├── api/
│   │   ├── client.ts                  # API client
│   │   ├── auth.ts
│   │   ├── events.ts
│   │   ├── orders.ts
│   │   └── ...
│   ├── hooks/
│   │   ├── useAuth.ts
│   │   ├── useEvents.ts
│   │   └── ...
│   └── utils/
│       ├── formatters.ts
│       └── validators.ts
├── stores/
│   ├── authStore.ts
│   └── uiStore.ts
├── types/
│   ├── api.ts
│   ├── models.ts
│   └── ...
├── next.config.js
├── tailwind.config.js
├── tsconfig.json
└── package.json
```

#### 2.3 Key Features by Page

| Page | Features |
|------|----------|
| **Dashboard** | Revenue chart, tickets sold, orders count, pending items, activity feed, top organizers |
| **Events** | List with filters (status, date, organizer), approve/reject, feature, suspend |
| **Orders** | List with filters, view details, refund, resend tickets |
| **Tickets** | List all tickets, search by barcode, check-in history, void |
| **Organizers** | List, approve, verify, suspend, set commission, view events/payouts |
| **Customers** | List, view profile, order history, block/unblock |
| **Payouts** | Pending requests, approve, process, complete, reject |
| **Reports** | Sales by period/event/organizer, ticket stats, customer analytics, financial |
| **Artists** | List all (owned + shared), create new (becomes owned), edit own only |
| **Venues** | List all (owned + shared), create new (becomes owned), edit own only |
| **Categories** | CRUD for event categories with icons, images, colors |
| **Users** | Staff management with roles (super_admin, admin, editor, scanner) |
| **Microservices** | Enable/disable, configure settings |
| **Payments** | Configure Netopia (web), Stripe (app), etc. |
| **Settings** | General, branding, webhooks, API keys |

---

## Implementation Phases

### Phase 1: Backend Extensions (2-3 days)
- [ ] Create new migrations for categories, artist/venue ownership, payment gateways
- [ ] Create TicketsController, CustomersController, OrdersController
- [ ] Create ReportsController with export functionality
- [ ] Create CategoriesController, ArtistsController, VenuesController
- [ ] Create PaymentGatewaysController
- [ ] Add new routes to api.php
- [ ] Run migrations, create seeders

### Phase 2: Frontend Setup & Auth (1-2 days)
- [ ] Create Next.js project with TypeScript
- [ ] Install and configure Tailwind CSS, shadcn/ui
- [ ] Set up API client with interceptors
- [ ] Implement login page
- [ ] Implement auth state management (Zustand)
- [ ] Create dashboard layout (sidebar, header)

### Phase 3: Core Dashboard Pages (3-4 days)
- [ ] Dashboard overview with charts and stats
- [ ] Events list with approval actions
- [ ] Orders list with refund functionality
- [ ] Tickets list with search and void
- [ ] Organizers management
- [ ] Customers management
- [ ] Payouts management

### Phase 4: Content Management (2-3 days)
- [ ] Event categories CRUD
- [ ] Artists list/create/edit (with ownership)
- [ ] Venues list/create/edit (with ownership)

### Phase 5: Reports & Settings (2-3 days)
- [ ] Reports dashboard with charts
- [ ] Sales, tickets, customers reports
- [ ] Export functionality (CSV, PDF)
- [ ] Staff users management
- [ ] Payment gateways configuration
- [ ] Microservices settings
- [ ] General settings, branding, webhooks

### Phase 6: Testing & Polish (1-2 days)
- [ ] Test all API endpoints
- [ ] Test all frontend functionality
- [ ] Mobile responsiveness
- [ ] Error handling
- [ ] Loading states

---

## Summary

| Component | Status | Action Required |
|-----------|--------|-----------------|
| **Marketplace Models** | ✅ Complete | None |
| **Admin Auth API** | ✅ Complete | None |
| **Dashboard API** | ✅ Complete | None |
| **Events API** | ✅ Complete | None |
| **Organizers API** | ✅ Complete | None |
| **Payouts API** | ✅ Complete | None |
| **Settings API** | ✅ Complete | None |
| **Tickets API** | ❌ Missing | Create TicketsController |
| **Customers API** | ❌ Missing | Create CustomersController |
| **Orders API** | ⚠️ Partial | Extend with refund, resend |
| **Reports API** | ❌ Missing | Create ReportsController |
| **Categories API** | ❌ Missing | Create migration + controller |
| **Artists API** | ❌ Missing | Create ownership + controller |
| **Venues API** | ❌ Missing | Create ownership + controller |
| **Payment Gateways** | ❌ Missing | Create migration + controller |
| **Admin Frontend** | ❌ Missing | Create Next.js app |

**Total Estimated Time: 12-17 days**

---

## Approval Required

Please confirm:
1. Is the scope correct based on existing code?
2. Should I proceed with Phase 1 (Backend Extensions)?
3. Any features to add or remove?
