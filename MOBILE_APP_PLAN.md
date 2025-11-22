# Mobile App Plan: Tenant Event Management & Ticket Validation

## Overview

A hybrid mobile app (WebView + native components) serving two user types:

1. **Tenant Clients (Customers)**: Login to view their orders, tickets, and upcoming events
2. **Tenant Admins/Editors**: Manage events, validate tickets offline, and view real-time reports

The app provides a unified experience with role-based access to different features.

---

## Architecture Decision: Hybrid WebView Approach

### Why Hybrid (WebView + Native)

| Component | Approach | Reason |
|-----------|----------|--------|
| Login, Dashboard, Events, Orders | WebView | Reuse existing Filament/Livewire admin UI |
| QR Scanner | Native | Better camera performance & offline support |
| Offline Storage | Native SQLite | IndexedDB insufficient for offline validation |
| Push Notifications | Native | Required for real-time alerts |
| Background Sync | Native | Sync validated tickets when online |

### Tech Stack Recommendation

**Option A: React Native + WebView** (Recommended)
- `react-native-webview` for admin panels
- `react-native-camera` for QR scanning
- `react-native-sqlite-storage` for offline data
- Shared JavaScript codebase

**Option B: Flutter + WebView**
- `webview_flutter` package
- `mobile_scanner` for QR
- `sqflite` for offline storage

**Option C: Capacitor (Ionic)**
- Wrap existing Laravel views
- Native plugins for camera/storage
- Fastest development time

---

## Feature Breakdown

### 1. Dual Authentication System

The app supports two distinct user types with different login flows.

#### Login Screen UI:

```
┌─────────────────────────────────┐
│                                 │
│         [App Logo]              │
│                                 │
│   ┌─────────────────────────┐   │
│   │ Email                   │   │
│   └─────────────────────────┘   │
│   ┌─────────────────────────┐   │
│   │ Password                │   │
│   └─────────────────────────┘   │
│                                 │
│   [ Login ]                     │
│                                 │
│   Forgot password?              │
│   Don't have an account? Sign up│
│                                 │
│   ─────────── or ───────────    │
│                                 │
│   [ Login as Tenant Admin ]     │
│                                 │
└─────────────────────────────────┘
```

#### A. Customer Login (Default)

For customers who purchased tickets on any tenant website.

**Flow:**
1. Customer enters email + password
2. App authenticates against `/api/tenant-client/auth/login`
3. Returns customer token + primary tenant info
4. Customer sees their dashboard with orders/tickets

**Existing API Used:**
```php
POST /api/tenant-client/auth/login
POST /api/tenant-client/auth/register
POST /api/tenant-client/auth/forgot-password
GET /api/tenant-client/auth/me
```

#### B. Tenant Admin Login ("Login as Tenant Admin" button)

For tenant owners, admins, and editors to manage their events.

**Flow:**
1. User taps "Login as Tenant Admin"
2. Opens admin login form with three fields:
   - Website URL (tenant domain, e.g., `events.mycompany.com`)
   - Email
   - Password
3. App resolves tenant from domain, authenticates user
4. Validates user has admin/editor role for that tenant
5. Returns admin token with elevated permissions
6. User sees admin dashboard with full management features

**New Admin Login Form UI:**
```
┌─────────────────────────────────┐
│                                 │
│   ← Back                        │
│                                 │
│   Tenant Admin Login            │
│                                 │
│   ┌─────────────────────────┐   │
│   │ Your website URL        │   │
│   │ e.g., events.company.com│   │
│   └─────────────────────────┘   │
│   ┌─────────────────────────┐   │
│   │ Email                   │   │
│   └─────────────────────────┘   │
│   ┌─────────────────────────┐   │
│   │ Password                │   │
│   └─────────────────────────┘   │
│                                 │
│   [ Login to Admin ]            │
│                                 │
└─────────────────────────────────┘
```

**New API Endpoints Needed:**
```php
// Customer auth extensions
POST /api/mobile/customer/login         // Enhanced with device registration
POST /api/mobile/customer/refresh       // Token refresh
POST /api/mobile/customer/device        // Register device for push
DELETE /api/mobile/customer/device      // Unregister device

// Admin auth (new)
POST /api/mobile/admin/login            // Tenant admin login
{
    "domain": "events.company.com",
    "email": "admin@company.com",
    "password": "secret"
}
// Returns: token, user, tenant, permissions

POST /api/mobile/admin/refresh          // Admin token refresh
GET /api/mobile/admin/me                // Current admin user + tenant
```

**Backend Implementation:**
```php
class MobileAdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Resolve tenant from domain
        $tenant = Tenant::where('domain', $request->domain)
            ->orWhereHas('domains', fn($q) => $q->where('domain', $request->domain))
            ->first();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Authenticate user
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Check user has access to this tenant (admin, editor, or tenant owner)
        if (!$this->userCanAccessTenant($user, $tenant)) {
            return response()->json(['error' => 'No access to this tenant'], 403);
        }

        // Generate token with tenant scope
        $token = $user->createToken('mobile-admin', [
            'tenant_id' => $tenant->id,
            'role' => $this->getUserRoleForTenant($user, $tenant),
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
            'tenant' => new TenantResource($tenant),
            'permissions' => $this->getPermissions($user, $tenant),
        ]);
    }

    private function userCanAccessTenant(User $user, Tenant $tenant): bool
    {
        // Tenant owner
        if ($user->tenant_id === $tenant->id) return true;

        // Super admin
        if ($user->hasRole('super-admin')) return true;

        // Admin or editor for this tenant
        return $user->hasAnyRole(['admin', 'editor']) &&
               $user->tenants->contains($tenant->id);
    }
}
```

**Security:**
- Implement token refresh (current tokens don't expire)
- Device-bound tokens
- Remote logout capability
- Role-based permissions in token claims
- Biometric unlock (Face ID / Fingerprint) after initial login

---

### 2. Customer Dashboard (Tenant Clients)

For customers who purchased tickets.

**Features:**
- View all orders across all tenant websites
- View tickets with QR codes
- See upcoming events for their tickets
- Download tickets to Apple Wallet / Google Pay
- Order history and receipts

**Screens:**
1. **My Tickets** - Active/upcoming tickets with event info
2. **My Orders** - Order history with status
3. **Event Details** - Event info for purchased tickets
4. **Ticket Detail** - QR code, seat info, download to wallet

**API Endpoints (existing + enhanced):**
```php
GET /api/mobile/customer/tickets        // All customer tickets
GET /api/mobile/customer/orders         // Order history
GET /api/mobile/customer/orders/{id}    // Order detail with tickets
GET /api/mobile/customer/events         // Events for customer's tickets
POST /api/mobile/customer/wallet/{ticketId}  // Generate wallet pass
```

**Implementation:**
- Use existing `/api/tenant-client/auth/me` to get customer data
- Extend with mobile-optimized endpoints
- Support multiple tenants (customer may have bought from different events)

---

### 3. Admin Events Dashboard

**Data to Display:**
- Event list with status indicators (live, upcoming, past)
- Quick stats: tickets sold, revenue, check-ins
- Search and filter capabilities

**Implementation:**
- WebView loading existing Filament pages OR
- Native screens consuming `/api/tenant-client/admin/events`

**New API Endpoints Needed:**
```php
GET /api/mobile/events                    // Optimized list with stats
GET /api/mobile/events/{id}/summary       // Quick stats for single event
GET /api/mobile/events/{id}/performances  // For multi-performance events
```

---

### 4. Admin Tickets & Orders Management

**Data to Display:**
- Order list with status, customer, total
- Order details with line items
- Individual ticket details with QR code
- Ticket status (valid, used, void)

**Implementation:**
- Extend existing admin API
- Add ticket-specific endpoints

**New API Endpoints Needed:**
```php
GET /api/mobile/orders                    // Paginated orders list
GET /api/mobile/orders/{id}               // Order with tickets
GET /api/mobile/tickets/{code}            // Single ticket lookup
PATCH /api/mobile/tickets/{code}/void     // Void a ticket
GET /api/mobile/events/{id}/tickets       // All tickets for event
```

---

### 5. QR Ticket Scanning & Validation (Admin Only)

**This is the core feature requiring native implementation.**

#### Current System Analysis:
- QR Format: `INV:{invite_code}:{ticket_ref}:{checksum}`
- Validation: `InviteTrackingService::trackCheckIn()`
- Checksum: HMAC-SHA256 with APP_KEY

#### Native Scanner Implementation:

```javascript
// Pseudo-code for native scanner
async function validateTicket(qrData) {
  // 1. Parse QR code
  const { inviteCode, ticketRef, checksum } = parseQR(qrData);

  // 2. Check local cache first (for offline)
  const localTicket = await localDB.findTicket(inviteCode);

  if (localTicket) {
    // 3a. Offline validation
    if (localTicket.status === 'used') {
      return { valid: false, reason: 'ALREADY_USED', offlineValidated: true };
    }
    if (localTicket.status === 'void') {
      return { valid: false, reason: 'VOIDED', offlineValidated: true };
    }

    // Mark as used locally
    await localDB.markUsed(inviteCode, timestamp, gateRef);
    await syncQueue.add({ type: 'CHECK_IN', data: { inviteCode, timestamp, gateRef }});

    return { valid: true, ticket: localTicket, offlineValidated: true };
  }

  // 3b. Online validation
  if (isOnline()) {
    const result = await api.post('/api/mobile/scan/validate', {
      qr_data: qrData,
      gate_ref: currentGate
    });

    // Cache the ticket for future offline use
    await localDB.cacheTicket(result.ticket);
    return result;
  }

  // 3c. No connection and not in cache
  return { valid: false, reason: 'NOT_CACHED_OFFLINE' };
}
```

#### New API Endpoints Needed:
```php
POST /api/mobile/scan/validate            // Validate and check-in
POST /api/mobile/scan/lookup              // Lookup without check-in
POST /api/mobile/scan/batch-sync          // Sync offline validations
GET /api/mobile/scan/download/{eventId}   // Download tickets for offline
```

#### Backend Service Updates:

**New: MobileScanController.php**
```php
class MobileScanController extends Controller
{
    public function validate(Request $request)
    {
        $qrData = $request->input('qr_data');
        $gateRef = $request->input('gate_ref');

        $service = app(InviteTrackingService::class);
        $result = $service->trackCheckIn($qrData, $gateRef);

        return response()->json([
            'valid' => $result['success'],
            'message' => $result['message'],
            'ticket' => $this->formatTicketForCache($result),
        ]);
    }

    public function downloadEventTickets(Event $event)
    {
        // Return all valid tickets for offline caching
        $tickets = $event->tickets()
            ->with(['ticketType', 'order.customer'])
            ->where('status', 'valid')
            ->get()
            ->map(fn($t) => [
                'code' => $t->code,
                'qr_data' => $t->qr_data,
                'status' => $t->status,
                'type' => $t->ticketType->name,
                'customer' => $t->order->customer_email,
                'seat' => $t->seat_label,
            ]);

        return response()->json(['tickets' => $tickets]);
    }

    public function batchSync(Request $request)
    {
        // Process offline check-ins
        $checkIns = $request->input('check_ins');

        foreach ($checkIns as $checkIn) {
            // Update invite/ticket status
            // Handle conflicts (already checked in on another device)
        }

        return response()->json(['synced' => count($checkIns)]);
    }
}
```

---

### 6. Offline Mode (Admin Only)

#### Local Database Schema (SQLite):

```sql
-- Cached tickets for offline validation
CREATE TABLE cached_tickets (
    id TEXT PRIMARY KEY,
    event_id TEXT NOT NULL,
    invite_code TEXT UNIQUE NOT NULL,
    ticket_ref TEXT,
    qr_data TEXT,
    status TEXT DEFAULT 'valid',  -- valid, used, void
    customer_email TEXT,
    customer_name TEXT,
    ticket_type TEXT,
    seat_label TEXT,
    checked_in_at TEXT,
    checked_in_by TEXT,
    gate_ref TEXT,
    synced INTEGER DEFAULT 0,
    cached_at TEXT,
    updated_at TEXT
);

-- Offline check-ins pending sync
CREATE TABLE pending_sync (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,  -- CHECK_IN, VOID
    invite_code TEXT NOT NULL,
    timestamp TEXT NOT NULL,
    gate_ref TEXT,
    device_id TEXT,
    created_at TEXT
);

-- Cached events for offline viewing
CREATE TABLE cached_events (
    id TEXT PRIMARY KEY,
    data TEXT NOT NULL,  -- JSON blob
    cached_at TEXT
);
```

#### Sync Strategy:

1. **Pre-event Download**: Download all tickets before event starts
2. **Incremental Updates**: Sync new tickets/changes periodically
3. **Check-in Queue**: Queue offline check-ins for batch sync
4. **Conflict Resolution**: Server timestamp wins, notify user of conflicts

#### Offline Indicators:
- Visual indicator when offline
- Show "pending sync" count
- Last sync timestamp
- Manual sync button

---

### 7. Real-Time Reports & Analytics (Admin Only)

#### Dashboard Metrics:

**Event Overview:**
- Total tickets sold vs capacity
- Revenue (total, today)
- Check-in rate (checked-in / sold)
- Check-ins by hour chart

**Live Attendance:**
- Current check-in count
- Check-ins per minute (sparkline)
- Gate breakdown
- Peak times

**Sales Analytics:**
- Ticket type breakdown
- Revenue by ticket type
- Sales timeline
- Promo code usage

#### New API Endpoints Needed:
```php
GET /api/mobile/reports/event/{id}/summary    // Key metrics
GET /api/mobile/reports/event/{id}/live       // Real-time attendance
GET /api/mobile/reports/event/{id}/sales      // Sales breakdown
GET /api/mobile/reports/event/{id}/checkins   // Check-in timeline
GET /api/mobile/reports/tenant/overview       // Tenant-wide stats
```

#### Real-Time Updates:

Since WebSockets aren't implemented, use **polling with smart intervals**:

```javascript
// Adaptive polling based on event status
function getPollingInterval(event) {
  if (event.isLive) return 5000;      // 5 seconds during event
  if (event.isToday) return 30000;    // 30 seconds on event day
  return 60000;                        // 1 minute otherwise
}

// Incremental updates to reduce bandwidth
GET /api/mobile/reports/event/{id}/live?since={timestamp}
```

**Future Enhancement**: Add Laravel WebSockets for true real-time:
```php
// Broadcasting check-ins
event(new TicketCheckedIn($ticket));
```

---

## Implementation Phases

### Phase 1: Foundation & Dual Auth (Core MVP)
1. Project setup (React Native recommended)
2. Dual login system (customer + admin)
3. Secure token storage
4. Basic navigation structure
5. Role-based screen routing

**Deliverables:**
- Customer login form (default)
- "Login as Tenant Admin" flow with domain input
- Basic app shell with role detection
- Logout functionality

### Phase 2: Customer Dashboard
1. Customer tickets list
2. Customer orders list
3. Ticket detail with QR code
4. Event details for tickets
5. Apple Wallet / Google Pay integration

**Deliverables:**
- My Tickets screen
- My Orders screen
- Ticket detail with QR display
- Wallet pass download
- Pull-to-refresh

### Phase 3: Admin Dashboard & Events
1. WebView integration for admin panels OR
2. Native admin event list
3. Quick stats for events
4. Orders management

**Deliverables:**
- Admin events list with stats
- Orders list (WebView or native)
- Event summary view

### Phase 4: Native QR Scanner (Admin)
1. Camera permission handling
2. QR code scanner implementation
3. Online validation flow
4. Scan history view

**Deliverables:**
- QR scanner screen
- Validation feedback UI
- Sound/haptic feedback
- Scan history

### Phase 5: Offline Capability (Admin)
1. SQLite database setup
2. Ticket download mechanism
3. Offline validation logic
4. Sync queue implementation
5. Conflict resolution

**Deliverables:**
- Offline scanning works
- Background sync
- Sync status indicators
- Conflict handling

### Phase 6: Real-Time Reports (Admin)
1. Reports API endpoints
2. Dashboard UI components
3. Charts and visualizations
4. Polling mechanism
5. Push notifications for alerts

**Deliverables:**
- Live attendance dashboard
- Sales reports
- Check-in analytics
- Alert notifications

### Phase 7: Polish & Release
1. Performance optimization
2. Error handling & recovery
3. Biometric unlock
4. App store preparation
5. Documentation

---

## API Additions Summary

### New Mobile API Routes

```php
// routes/api.php additions

Route::prefix('mobile')->group(function () {

    // ═══════════════════════════════════════════════════════════════
    // CUSTOMER (Tenant Client) Routes
    // ═══════════════════════════════════════════════════════════════

    Route::prefix('customer')->group(function () {
        // Auth (public)
        Route::post('/login', [MobileCustomerAuthController::class, 'login']);
        Route::post('/register', [MobileCustomerAuthController::class, 'register']);
        Route::post('/forgot-password', [MobileCustomerAuthController::class, 'forgotPassword']);

        // Authenticated customer routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/refresh', [MobileCustomerAuthController::class, 'refresh']);
            Route::post('/device', [MobileCustomerAuthController::class, 'registerDevice']);
            Route::delete('/device', [MobileCustomerAuthController::class, 'unregisterDevice']);
            Route::get('/me', [MobileCustomerAuthController::class, 'me']);
            Route::post('/logout', [MobileCustomerAuthController::class, 'logout']);

            // Customer dashboard
            Route::get('/tickets', [MobileCustomerController::class, 'tickets']);
            Route::get('/tickets/{code}', [MobileCustomerController::class, 'ticketDetail']);
            Route::get('/orders', [MobileCustomerController::class, 'orders']);
            Route::get('/orders/{id}', [MobileCustomerController::class, 'orderDetail']);
            Route::get('/events', [MobileCustomerController::class, 'events']);
            Route::post('/wallet/{ticketId}', [MobileCustomerController::class, 'generateWalletPass']);
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // ADMIN (Tenant Admin/Editor) Routes
    // ═══════════════════════════════════════════════════════════════

    Route::prefix('admin')->group(function () {
        // Auth (public)
        Route::post('/login', [MobileAdminAuthController::class, 'login']);

        // Authenticated admin routes
        Route::middleware(['auth:sanctum', 'tenant.admin'])->group(function () {
            Route::post('/refresh', [MobileAdminAuthController::class, 'refresh']);
            Route::post('/device', [MobileAdminAuthController::class, 'registerDevice']);
            Route::delete('/device', [MobileAdminAuthController::class, 'unregisterDevice']);
            Route::get('/me', [MobileAdminAuthController::class, 'me']);
            Route::post('/logout', [MobileAdminAuthController::class, 'logout']);

            // Events
            Route::get('/events', [MobileEventController::class, 'index']);
            Route::get('/events/{event}/summary', [MobileEventController::class, 'summary']);
            Route::get('/events/{event}/performances', [MobileEventController::class, 'performances']);

            // Orders & Tickets
            Route::get('/orders', [MobileOrderController::class, 'index']);
            Route::get('/orders/{order}', [MobileOrderController::class, 'show']);
            Route::get('/tickets/{code}', [MobileTicketController::class, 'show']);
            Route::patch('/tickets/{code}/void', [MobileTicketController::class, 'void']);
            Route::get('/events/{event}/tickets', [MobileTicketController::class, 'forEvent']);

            // Scanning
            Route::post('/scan/validate', [MobileScanController::class, 'validate']);
            Route::post('/scan/lookup', [MobileScanController::class, 'lookup']);
            Route::post('/scan/batch-sync', [MobileScanController::class, 'batchSync']);
            Route::get('/scan/download/{event}', [MobileScanController::class, 'downloadEventTickets']);

            // Reports
            Route::get('/reports/event/{event}/summary', [MobileReportController::class, 'eventSummary']);
            Route::get('/reports/event/{event}/live', [MobileReportController::class, 'liveAttendance']);
            Route::get('/reports/event/{event}/sales', [MobileReportController::class, 'salesBreakdown']);
            Route::get('/reports/event/{event}/checkins', [MobileReportController::class, 'checkInTimeline']);
            Route::get('/reports/tenant/overview', [MobileReportController::class, 'tenantOverview']);
        });
    });
});
```

---

## Database Migrations Needed

```php
// Add QR data to tickets table
Schema::table('tickets', function (Blueprint $table) {
    $table->string('qr_data')->nullable()->after('code');
    $table->string('qr_checksum', 8)->nullable()->after('qr_data');
    $table->timestamp('checked_in_at')->nullable();
    $table->string('checked_in_gate')->nullable();
    $table->string('checked_in_device')->nullable();
});

// Device registration for push notifications
Schema::create('mobile_devices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
    $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');
    $table->string('device_token');
    $table->string('platform'); // ios, android
    $table->string('device_name')->nullable();
    $table->timestamp('last_active_at')->nullable();
    $table->timestamps();
});

// Offline sync tracking
Schema::create('mobile_sync_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained();
    $table->string('device_id');
    $table->string('action'); // DOWNLOAD, SYNC_CHECKINS
    $table->integer('record_count');
    $table->json('meta')->nullable();
    $table->timestamps();
});
```

---

## Security Considerations

1. **Token Security**: Store in iOS Keychain / Android Keystore
2. **Certificate Pinning**: Pin SSL certificates in production
3. **Offline Data Encryption**: Encrypt SQLite database
4. **Checksum Validation**: Validate QR checksums client-side for offline
5. **Device Binding**: Tokens bound to specific devices
6. **Remote Wipe**: Ability to clear app data remotely
7. **Audit Trail**: Log all scan attempts with device ID

---

## UI/UX Considerations

### Scanner Screen
- Large viewfinder with guides
- Instant feedback (sound + haptic + visual)
- Recent scans list below
- Manual code entry fallback

### Offline Mode
- Clear visual indicator (offline badge)
- Pending sync count
- Last sync time
- "Sync Now" button

### Reports
- Pull-to-refresh
- Skeleton loading states
- Offline: show cached data with timestamp
- Charts: use `react-native-charts-wrapper` or `victory-native`

---

## Testing Strategy

1. **Unit Tests**: Business logic, validation rules
2. **Integration Tests**: API endpoints
3. **E2E Tests**: Full flows with Detox/Appium
4. **Offline Tests**: Airplane mode scenarios
5. **Sync Tests**: Conflict resolution
6. **Performance Tests**: Large ticket datasets

---

## Estimated Effort

| Phase | Description | Complexity |
|-------|-------------|------------|
| Phase 1 | Foundation & Dual Auth | Medium |
| Phase 2 | Customer Dashboard | Medium |
| Phase 3 | Admin Dashboard & Events | Medium |
| Phase 4 | Native QR Scanner | Medium |
| Phase 5 | Offline Mode | High |
| Phase 6 | Reports | Medium |
| Phase 7 | Polish | Low |

---

## Open Questions

1. **Push Notifications**: Use Firebase FCM or APNs directly?
2. **WebView vs Native**: Which screens should be fully native?
3. **Multi-device**: Should one user scan from multiple devices simultaneously?
4. **Ticket Transfer**: Support QR code sharing/transfer?
5. **Analytics Provider**: Use existing AnalyticsEvent model or third-party?

---

## Next Steps

1. Confirm tech stack choice (React Native recommended)
2. Set up mobile project structure
3. Implement authentication flow
4. Create backend API endpoints
5. Build scanner prototype
6. Test offline validation logic
