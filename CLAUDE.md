# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

EventPilot is a full-stack event management platform built with Laravel 12 and Filament 4. The application supports multi-language content (en, ro, de, fr, es) and multi-tenancy for multiple event organizers. The core application is located in `epas/core/`.

**Tech Stack:**
- Laravel 12 (PHP 8.2+)
- Filament 4.1 (Admin panel framework)
- Livewire 3.6 (Reactive components)
- Vite 5.4 + Tailwind CSS 3.4
- Database: SQLite (default) / PostgreSQL (Docker)

## Development Commands

**Initial Setup:**
```bash
composer setup    # Installs dependencies, copies .env, generates key, migrates DB, builds assets
```

**Development:**
```bash
composer dev      # Runs all dev servers concurrently (Laravel server, queue worker, log viewer, Vite)
php artisan serve # Run Laravel dev server only
php artisan queue:listen  # Run queue worker only
php artisan pail  # Live log viewer
npm run dev       # Run Vite dev server with HMR
```

**Testing & Code Quality:**
```bash
composer test           # Clears config cache and runs PHPUnit
php artisan test        # Runs PHPUnit tests
./vendor/bin/pint       # Formats code (PSR-12 style)
```

**Database:**
```bash
php artisan migrate     # Run all migrations
php artisan tinker      # Interactive REPL for testing
```

**Production:**
```bash
npm run build           # Build production assets
```

**Docker:**
```bash
docker-compose up                                      # Starts Laravel (8081), PostgreSQL (5432), Redis (6379), Node (Vite on 5173)
docker compose exec -T laravel.test php artisan migrate  # Run migrations in Docker container
```

## Architecture & Key Patterns

### Multi-language System

The application uses a custom JSON-based translation system via the `Translatable` trait (`app/Support/Translatable.php`):

**Model Setup:**
```php
use App\Support\Translatable;

class Event extends Model {
    use Translatable;

    public array $translatable = ['title', 'slug', 'description', 'content'];
}
```

**Key Methods:**
- `getTranslation($field, $locale)` - Get translated value
- `setTranslation($field, $locale, $value)` - Set translation
- `hasTranslation($field, $locale)` - Check if translation exists

**Routing:**
- Public routes use locale prefix: `/{locale?}/events`, `/{locale?}/artists`
- Middleware: `SetLocale` and `SetLocaleFromRequest` handle locale detection
- Admin panel (`/admin`) uses user's preferred locale

### Filament Admin Panel Customizations

Filament is heavily customized for improved UX:

**Sticky Action Bars:**
- Custom JavaScript injects sticky actions at form bottoms
- Implemented via render hooks in `AdminPanelProvider.php`

**Form Anchor Navigation:**
- Sections use data attributes: `data-ep-section`, `data-ep-label`, `data-ep-icon`
- JavaScript generates anchor navigation for quick form section jumping
- Icons embedded directly (Heroicons paths)

**Custom Theme:**
- Tailwind-based theme: `resources/css/filament/admin/theme.css`
- Built with Vite: `npm run build` for production

**Custom SVG Icons:**
- ⚠️ **IMPORTANT**: Use `<x-svg-icon name="icon-name" />` NOT `<x-icon>`
- BladeUI Icons is installed as a Filament dependency and reserves `<x-icon>` namespace
- Custom icons located in `resources/views/components/icons/` directory
- Component: `resources/views/components/svg-icon.blade.php`
- Usage: `<x-svg-icon name="calendar" class="w-6 h-6 text-blue-500" />`
- See `resources/views/components/icons/README.md` for complete documentation
- Available icons: calendar, ticket, location, music, user, users, star, heart, venue, event, check, x, search, arrow-right, konvaseats

### Event Scheduling System

Events support three date modes:

1. **Single Day**: One date with start/end times
2. **Date Range**: Start date to end date
3. **Multi-day with Slots**: Custom JSON structure in `multi_slots` column
   ```json
   [
     {"date": "2024-03-15", "start_time": "19:00", "end_time": "22:00"},
     {"date": "2024-03-16", "start_time": "20:00", "end_time": "23:00"}
   ]
   ```

Events can be postponed or cancelled with `postponed_at`, `cancelled_at` timestamps.

### Email Template System

The application includes a comprehensive email template system with variable replacement and event triggers:

**Key Features:**
- **WYSIWYG Editor**: RichEditor with full HTML support for email bodies
- **Variable System**: 12+ predefined variables (first_name, last_name, email, public_name, verification_link, etc.)
- **Event Triggers**: 12 predefined triggers (registration_confirmation, welcome_email, password_reset, invoice_notification, etc.)
- **Email Logs**: Complete audit trail of all sent emails (read-only resource)
- **Variable Replacement**: Template processor with `{{variable_name}}` syntax

**Usage Example:**
```php
$template = EmailTemplate::where('event_trigger', 'registration_confirmation')->first();
$variables = ['first_name' => 'John', 'public_name' => 'My Company'];
$processed = $template->processTemplate($variables);
Mail::send([], [], function($m) use ($processed, $email) {
    $m->to($email)->subject($processed['subject'])->html($processed['body']);
});
EmailLog::create([...]);  // Log the sent email
```

**Resources:**
- `app/Models/EmailTemplate.php` - Template model with variable processing
- `app/Models/EmailLog.php` - Email sending audit trail
- `app/Filament/Resources/EmailTemplates/` - Admin CRUD interface
- `database/seeders/EmailTemplatesSeeder.php` - Example templates seeder
- `DOCS_EMAIL_USAGE_EXAMPLES.md` - Complete implementation guide

### Tenant Management & Onboarding

**Multi-Tenancy Features:**
- **Public Name**: Separate display name from legal company name
- **Owner Tracking**: Each tenant has an assigned owner user with password reset capability
- **Commission Rates**: Auto-calculated based on work method (1% exclusive, 2% mixed, 3% reseller)
- **Domain Management**: Interactive table for managing tenant domains with:
  - Add new domains functionality
  - Toggle active/suspended status
  - Set primary domain
  - Login as Admin (impersonation placeholder)
- **ANAF Integration**: Romanian company data verification via CUI lookup
- **Automatic Billing**: Next billing date auto-calculated from billing cycle

**Onboarding Process:**
1. Step 1: User details (name, email, password, public name)
2. Step 2: Company details + ANAF verification
3. Step 3: Domain configuration
4. Step 4: Work method selection (auto-sets commission rate and plan)

**Important Notes:**
- Onboarding creates user with role `tenant` (not `admin`)
- Country codes stored as ISO 2-letter codes (RO, DE, etc.)
- Plans are named: `1percent`, `2percent`, `3percent`

### Microservices System

The application includes a microservices/integrations management system:

**Fields:**
- `name`, `slug`, `description` - Basic information
- `icon_image` - Small icon for UI cards (64x64px or 128x128px)
- `public_image` - Larger image for public pages (800x600px+)
- `short_description` - Brief description for card displays
- `is_active` - Enable/disable status

**Activation levels:**
- Tenant: `tenant_microservices` pivot (per Tenant client)
- Marketplace: `marketplace_client_microservices` pivot (per Marketplace client like Ambilet)
- Some microservices (e.g. Facebook CAPI) are then configured per individual marketplace organizer via dedicated tables — see Facebook Conversions API section below.

### Facebook Conversions API (CAPI)

Server-side conversion tracking that bypasses adblockers, iOS 14.5+ ATT, and ITP cookie deletion. Designed as a 3-layer system for ~95-100% capture, with full deduplication via shared `event_id`.

**Architecture (3 layers):**
- **Layer A — Browser Pixel** (existing, will be enhanced in Etapa 5): `fbq()` script injected by `/tracking/organizer/{id}/scripts` for organizers with Meta Pixel toggle enabled. Configured via `tracking_integrations` table.
- **Layer B — First-party tracking endpoint bridge** (Etapa 4 - pending): `/marketplace-tracking/track` will dual-sink browser events to both internal DB and Meta CAPI as a backup path when the browser pixel is blocked.
- **Layer C — Server-side Purchase listener** (live since Etapa 3): `FacebookCapiOrderObserver` watches Order status transitions to paid/confirmed/completed and dispatches `SendFacebookCapiPurchaseJob` to fire a Purchase event directly from Laravel. Cannot be blocked by browser-side measures.

**Activation flow:**
1. Marketplace admin activates the `facebook-capi-integration` microservice for their marketplace_client (creates row in `marketplace_client_microservices`).
2. In each organizer's edit page (`/marketplace/organizers/{id}/edit?tab=bilete-termeni`), under **Pixeli organizator**, a new section **Facebook Conversions API (Server-Side)** appears (only when the microservice is active for that marketplace).
3. Organizer enters Pixel ID + Access Token (System User token from Meta Business Suite with `ads_management`) + optional Test Event Code. "Test conexiune cu Meta" button verifies credentials live.
4. On save, a row is created/updated in `facebook_capi_connections` with the access_token encrypted at rest (Laravel Crypt mutator).

**Database schema (5 tables):**
- `facebook_capi_connections` — one row per organizer with credentials, status (active/inactive), enabled_events list. Supports `tenant_id`, `marketplace_client_id`, `marketplace_organizer_id` (all nullable, exactly one populated per row).
- `facebook_capi_event_configs` — per-connection event triggers + field mappings (currently seeded for Purchase, Lead, CompleteRegistration via `setupDefaultEventConfigs`).
- `facebook_capi_events` — audit log per event sent. Includes `event_id` (deterministic for dedup), `fbtrace_id`, `events_received` count, status, error.
- `facebook_capi_batches` — log per batch send (when `sendEventBatch` is used).
- `facebook_capi_custom_audiences` — synced audience list for Custom Audiences feature.

**Key components:**
- `app/Services/Integrations/FacebookCapi/FacebookCapiService.php` — Graph API wrapper (v18). Methods: `sendEvent`, `sendEventBatch`, `testCredentials` (one-off verification), `getConnectionForOrganizer`, `createConnectionForOrganizer`. Handles SHA-256 hashing of PII (em, ph, fn, ln, ct, st, zp, country, db, ge) before send.
- `app/Models/Integrations/FacebookCapi/*` — 5 Eloquent models, all with explicit table names.
- `app/Jobs/SendFacebookCapiPurchaseJob.php` — Order-specific Purchase dispatcher. Loads Order with relations, builds user_data (incl. fbp/fbc from order meta), custom_data (value, currency, content_ids = ticket type ids), event_id = `purchase_{order_id}` for browser-server deduplication.
- `app/Jobs/SendFacebookCapiEventJob.php` — Generic dispatcher used by Layer B for AddToCart, InitiateCheckout, ViewContent, etc.
- `app/Observers/FacebookCapiOrderObserver.php` — Hooks Order status changes via `DB::afterCommit` (no Purchase fires if checkout transaction rolls back). Skips legacy/external imports.
- `app/Filament/Marketplace/Resources/OrganizerResource.php` — form section + `isFacebookCapiMicroserviceActive($mcId)` gating helper.
- `app/Filament/Marketplace/Resources/OrganizerResource/Pages/EditOrganizer.php` — `mutateFormDataBeforeFill` / `mutateFormDataBeforeSave` / `afterSave` sync via `syncFacebookCapiConnection`.

**Event ID convention (deduplication):**
Browser pixel and CAPI must send the same `event_id` for Meta to dedupe.
- Purchase: `purchase_{order_id}`
- Other events from frontend: pass through whatever `event_id` the browser tracking generates (UUID-based)

**Adding a new event type:**
1. Add the event name to `setupDefaultEventConfigs` in `FacebookCapiService` (or directly to `enabled_events` array of existing connections via DB).
2. For server-driven events (e.g. on a model lifecycle): create an Observer + Job following the Purchase pattern, dispatch via `DB::afterCommit`.
3. For browser-driven events (Layer B): the bridge in `/marketplace-tracking/track` controller (Etapa 4) dispatches `SendFacebookCapiEventJob` automatically by mapping the event name.

**Troubleshooting:**
- **Toggle disappears on reload after dezactivation**: expected — `enabled` is derived from `connection.status === 'active'`. Reactivate by toggling on + Save, OR `UPDATE facebook_capi_connections SET status='active' WHERE id=...`.
- **`Error while loading page` on edit organizer**: usually means `access_token` in DB is not a valid Laravel-encrypted string (e.g. partial save before the crypt-safe mutator was deployed). The mutator now falls back to raw value gracefully, but you can also clear the bad row: `DELETE FROM facebook_capi_connections WHERE id=...`.
- **Purchase events not arriving in Meta Events Manager**: check (1) connection `status='active'`, (2) `enabled_events` includes 'Purchase', (3) queue worker is running, (4) `facebook_capi_events` table for the row with status='failed' + error_message, (5) for Test Events: Test Event Code matches the one configured in Events Manager → Test Events.
- **APP_KEY rotation breaks all access_tokens**: encrypted tokens become unreadable. Either re-enter tokens manually for each organizer, or restore the previous APP_KEY.
- **Phantom Purchase to wrong Pixel ID after entering test data**: dezactivate connection until real credentials arrive: `UPDATE facebook_capi_connections SET status='inactive' WHERE marketplace_organizer_id=...` — observer exits early when `getConnectionForOrganizer` returns null/inactive.

**Verification commands:**
```bash
# Check connection state for an organizer
php artisan tinker --execute='dump(\App\Models\Integrations\FacebookCapi\FacebookCapiConnection::where("marketplace_organizer_id", 341)->first()?->only(["id","status","pixel_id","test_event_code","enabled_events"]));'

# Latest CAPI events sent (audit trail)
php artisan tinker --execute='dump(\App\Models\Integrations\FacebookCapi\FacebookCapiEvent::latest()->take(5)->get(["event_id","event_name","status","fbtrace_id","events_received","sent_at"])->toArray());'

# Verify token is encrypted at rest
php artisan tinker --execute='echo \DB::table("facebook_capi_connections")->where("marketplace_organizer_id", 341)->value("access_token") . PHP_EOL;'
```

**Security notes:**
- Access tokens are encrypted via Laravel Crypt + APP_KEY (mutator on `FacebookCapiConnection`). Mutator is idempotent (won't double-encrypt) and decryptor falls back gracefully if value is plaintext (defensive against partial writes / key rotation).
- PII (email, phone, name) is SHA-256 hashed before leaving the server (Meta requirement). `client_ip_address`, `client_user_agent`, `fbp`, `fbc` are passed through unhashed (Meta requirement).
- Test mode: when `test_event_code` is set, `test_mode` becomes true and all events go to Meta's Test Events sandbox instead of production.

### Seating Maps Module (NEW)

**IMPORTANT:** See **[SEATING_MODULE_README.md](epas/core/SEATING_MODULE_README.md)** for complete documentation.

Production-ready seating maps system with per-tenant support, 10-minute seat holds with automatic release, and dynamic pricing infrastructure.

**Key Features:**
- Interactive seating designer (Filament admin + optional tenant-side)
- Per-event seat inventory snapshots with versioning
- 10-minute seat hold TTL with Redis + DB fallback
- Atomic seat status updates with optimistic concurrency
- Dynamic pricing engine with pluggable strategies (time-based, velocity, threshold, custom)
- Public APIs with rate limiting and session management
- Real-time seat availability polling
- Standalone JavaScript widget for tenant websites

**Database Schema (11 tables):**
1. `seating_layouts` - Venue seating designs (draft/published, versioned)
2. `seating_sections` - Sections within layouts
3. `seating_rows` - Rows within sections
4. `seating_seats` - Individual seats with unique UIDs
5. `price_tiers` - Pricing tiers per tenant
6. `event_seating_layouts` - Per-event snapshots
7. `event_seats` - Per-event inventory with status (available/held/sold/blocked/disabled)
8. `seat_holds` - Temporary reservations with TTL
9. `dynamic_pricing_rules` - Pricing rules by scope/strategy
10. `dynamic_price_overrides` - Computed price overrides
11. `tenants.features` - Per-tenant feature flags (JSON)

**Key Services:**
- `FeatureFlag` - Per-tenant feature toggle resolution
- `GeometryStorage` - Storage abstraction for seating geometry
- `SeatHoldService` - Redis + DB hold management with automatic expiry
- `DynamicPricingEngine` - Interface for pricing strategies
- `SeatInventoryRepository` - Atomic seat status updates

**Configuration:**
- `config/seating.php` - 70+ settings (hold TTL, rate limits, Redis, canvas constraints, etc.)
- Environment variables in `.env.example` (see `SEATING_*` and `SEATING_DP_*`)

**Scheduled Jobs:**
- `seating:release-expired-holds` - Fallback cleanup when Redis disabled (runs every minute)

**Public APIs:**
- `GET /public/events/{event}/seating` - Load geometry and price tiers
- `GET /public/events/{event}/seats` - Query seat statuses with optional bbox
- `POST /public/seats/hold` - Hold seats for session (10min TTL)
- `DELETE /public/seats/hold` - Release held seats
- `POST /public/seats/confirm` - Atomic purchase confirmation

**Security:**
- All tables include `tenant_id` with `TenantScope` global scope
- Session-based authentication with signed cookies
- Rate limiting per route (configurable via config)
- CORS restricted to tenant domains only
- Price tampering prevention via snapshot hash validation

**Implementation Status:**
- ✅ Configuration & environment setup
- ✅ FeatureFlag service with caching
- ✅ Complete database schema (11 migrations)
- ⏳ Eloquent models with TenantScope (see README for templates)
- ⏳ Core services (GeometryStorage, SeatHoldService, DynamicPricingEngine)
- ⏳ Public API controllers with rate limiting
- ⏳ Filament admin resources (Seating Designer)
- ⏳ Frontend JavaScript widget (`EPAS.Seating`)
- ⏳ Feature tests for concurrency and hold expiry

**Deployment:**
```bash
# Run migrations
docker compose exec -T laravel.test php artisan migrate

# Enable for tenant
php artisan tinker
>>> $ff = app(\App\Services\FeatureFlag::class);
>>> $ff->setFeature($tenantId, 'seating.enabled', true);
```

### Filament 4 Specifics

**Important Type Hints:**
When using Filament 4 with Schema (not Form), use correct type hints:
```php
// CORRECT for Filament 4 Schema:
->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
    $set('field', $value);
})

// INCORRECT (old Forms API):
->afterStateUpdated(function ($state, \Filament\Forms\Set $set) { ... })
```

**Navigation Submenus:**
Create parent-child navigation using `$navigationParentItem`:
```php
// In child resource:
protected static ?string $navigationParentItem = 'Email Templates';
```
See `DOCS_FILAMENT_NAVIGATION.md` for complete navigation guide.

**Rendering Views in Forms:**
Use `HtmlString` to prevent HTML escaping:
```php
Forms\Components\Placeholder::make('custom_view')
    ->content(fn ($record) => new \Illuminate\Support\HtmlString(
        view('my.view', ['data' => $record])->render()
    ));
```

### Domain Organization

**Filament Resources** are grouped by domain in `app/Filament/Resources/`:
- `Artists/` - Artist management
- `Events/` - Event management (complex forms)
- `Venues/` - Venue management
- `Tickets/` - Ticket types and pricing
- `Orders/` - Customer orders
- `Billing/` - Invoices
- `Customers/` - Customer management
- `Taxonomies/` - Event types, genres, tags, artist types/genres
- `Tenants/` - Multi-tenant organization management
- `EmailTemplates/` - Email template management with WYSIWYG editor
- `EmailLogs/` - Email sending audit trail (read-only)
- `Microservices/` - Integrations/microservices management
- `Users/` - User management with roles (super-admin, admin, editor, tenant)

**Models** (`app/Models/`) use many-to-many relationships for taxonomies via pivot tables.

**Public Controllers** (`app/Http/Controllers/Public/`) handle frontend:
- Event listings and details
- Venue pages
- Artist profiles
- Comparison pages

**Admin Controllers** (`app/Http/Controllers/Admin/`):
- `DomainController` - Domain management (add, toggle active, login as admin)

**Onboarding:**
- `OnboardingController` - 4-step tenant registration with ANAF integration

### Authorization

- Role-based permissions via `spatie/laravel-permission`
- Policies in `app/Policies/` for fine-grained access control
- Activity logging via `spatie/laravel-activitylog` tracks all admin actions

### Code Organization

```
app/
├── Filament/Resources/       # Admin CRUD resources (grouped by domain)
├── Http/
│   ├── Controllers/
│   │   ├── Admin/            # Admin controllers (DomainController)
│   │   └── Public/           # Public-facing controllers
│   └── Middleware/           # Locale switching middleware
├── Models/                   # Eloquent models (20+ core entities)
│   ├── EmailTemplate.php     # Email templates with variable processing
│   ├── EmailLog.php          # Email sending audit trail
│   ├── Microservice.php      # Integrations/microservices
│   ├── Tenant.php            # Multi-tenant organizations
│   ├── Domain.php            # Tenant domains
│   └── ...                   # Events, Venues, Artists, etc.
├── Policies/                 # Authorization policies
├── Services/                 # Business logic services
│   ├── AnafService.php       # Romanian ANAF API integration
│   └── LocationService.php   # Countries, states, cities data
└── Support/                  # Custom traits (Translatable)

database/
├── migrations/               # 30+ migrations defining complete schema
├── factories/                # Model factories for testing
└── seeders/
    ├── EmailTemplatesSeeder.php  # Email template examples
    └── ...                       # Other seeders

resources/
├── css/filament/admin/       # Custom Filament theme
├── views/
│   ├── filament/
│   │   ├── forms/components/  # Custom form components (variables-selector)
│   │   └── resources/tenants/ # Tenant-specific views (domains-manager)
│   ├── components/            # Blade components (owner-info)
│   └── public/                # Public frontend templates
└── lang/{locale}/            # Translation files (en, ro, de, fr, es)

routes/
├── web.php                   # Public + admin routes with /{locale?} prefix
└── api.php                   # API v1 endpoints (/v1/public/*)
```

## Key Conventions

- **Code Style**: PSR-12 via Laravel Pint, 4 spaces, LF line endings
- **Models**: Singular names (Event, Venue, Artist)
- **Routes**: Named with dot notation (`public.events`, `public.artists.show`)
- **Database**: Eloquent ORM for all data access, no raw SQL
- **Testing**: PHPUnit with Feature and Unit test suites

## Important Routes

- Admin panel: `/admin`
- Public frontend: `/{locale}/` (e.g., `/en/`, `/ro/events`, `/de/artists`)
- API: `/v1/public/events` (minimal implementation, ready for expansion)

## Environment

The project runs on XAMPP (Windows) for local development, with Docker Compose available for containerized setup (PHP 8.4, PostgreSQL 16, Redis 7, Node 20).

Default database is SQLite (`database/database.sqlite`), switches to PostgreSQL when using Docker.
