# Mobile App Plan: Tenant Event Management & Ticket Validation

## Overview

A hybrid mobile app (WebView + native components) for tenants to manage events, validate tickets offline, and view real-time reports.

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

### 1. Authentication & Tenant Access

**Implementation:**
- Use existing `/api/tenant-client/auth/login` endpoint
- Store JWT/token in secure device storage (Keychain/Keystore)
- Add biometric unlock (Face ID / Fingerprint)
- Session refresh mechanism

**New API Endpoints Needed:**
```php
POST /api/mobile/auth/login          // Enhanced with device registration
POST /api/mobile/auth/refresh        // Token refresh
POST /api/mobile/auth/device         // Register device for push
DELETE /api/mobile/auth/device       // Unregister device
```

**Security:**
- Implement token refresh (current tokens don't expire)
- Device-bound tokens
- Remote logout capability

---

### 2. Events Dashboard

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

### 3. Tickets & Orders Management

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

### 4. QR Ticket Scanning & Validation

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

### 5. Offline Mode

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

### 6. Real-Time Reports & Analytics

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

### Phase 1: Foundation (Core MVP)
1. Project setup (React Native recommended)
2. Authentication flow with secure storage
3. WebView integration for admin panels
4. Basic navigation structure

**Deliverables:**
- Login/logout
- Events list (WebView)
- Orders list (WebView)
- Basic app shell

### Phase 2: Native Scanner
1. Camera permission handling
2. QR code scanner implementation
3. Online validation flow
4. Scan history view

**Deliverables:**
- QR scanner screen
- Validation feedback UI
- Sound/haptic feedback
- Scan history

### Phase 3: Offline Capability
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

### Phase 4: Real-Time Reports
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

### Phase 5: Polish & Release
1. Performance optimization
2. Error handling & recovery
3. App store preparation
4. Documentation

---

## API Additions Summary

### New Mobile API Routes

```php
// routes/api.php additions

Route::prefix('mobile')->middleware(['auth:sanctum', 'tenant'])->group(function () {

    // Auth extensions
    Route::post('/auth/refresh', [MobileAuthController::class, 'refresh']);
    Route::post('/auth/device', [MobileAuthController::class, 'registerDevice']);
    Route::delete('/auth/device', [MobileAuthController::class, 'unregisterDevice']);

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
| Phase 1 | Foundation | Medium |
| Phase 2 | Native Scanner | Medium |
| Phase 3 | Offline Mode | High |
| Phase 4 | Reports | Medium |
| Phase 5 | Polish | Low |

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
