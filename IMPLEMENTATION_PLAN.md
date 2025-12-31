# Implementation Plan: Event Commerce + Data Intelligence Rail

## Overview

Acest document definește planul de implementare pentru sistemul de tracking și intelligence bazat pe Schema Registry v1.

---

## Faza 1: Tracking Foundations

### 1.1 Database Migrations

#### Migration 1: `tx_events` (Raw Events - Partitioned)

```
Tabel: tx_events
Partitioning: RANGE pe occurred_at (monthly)
Retention: 24 luni hot, archive după

Coloane:
- id: bigserial PRIMARY KEY
- event_id: uuid NOT NULL UNIQUE (client-generated)
- event_name: varchar(100) NOT NULL
- event_version: smallint NOT NULL DEFAULT 1
- occurred_at: timestamptz NOT NULL
- received_at: timestamptz NOT NULL DEFAULT NOW()
- tenant_id: bigint NOT NULL REFERENCES tenants(id)
- site_id: varchar(50)
- source_system: varchar(20) NOT NULL CHECK (source_system IN ('web','mobile','scanner','backend','payments','shop','wallet'))
- visitor_id: varchar(64) -- required for web/mobile
- session_id: varchar(64) -- required for web/mobile
- sequence_no: int
- person_id: bigint REFERENCES core_customers(id) -- set after stitch
- consent_snapshot: jsonb NOT NULL
- context: jsonb NOT NULL DEFAULT '{}'
- entities: jsonb NOT NULL DEFAULT '{}'
- payload: jsonb NOT NULL DEFAULT '{}'
- idempotency_key: varchar(255)
- prev_event_id: uuid

Indexuri:
- (tenant_id, event_name, occurred_at)
- (tenant_id, person_id, occurred_at) WHERE person_id IS NOT NULL
- (tenant_id, visitor_id, occurred_at)
- (tenant_id, occurred_at) USING BRIN
- (event_id) UNIQUE
- (idempotency_key) WHERE idempotency_key IS NOT NULL
- entities->>'event_entity_id', occurred_at
- entities->>'order_id', occurred_at
```

#### Migration 2: `tx_sessions` (Enhanced Sessions)

```
Tabel: tx_sessions
Extinde funcționalitatea core_sessions cu:

Coloane noi față de core_sessions:
- session_token: varchar(64) UNIQUE (tx_sid from client)
- sequence_count: int DEFAULT 0
- first_touch: jsonb -- {utm, click_ids, referrer, landing_page}
- consent_snapshot_initial: jsonb
- consent_snapshot_final: jsonb
- events_count: int DEFAULT 0
- engagement_active_ms: int DEFAULT 0
- engagement_total_ms: int DEFAULT 0

Sau: Migrare pentru adăugare coloane la core_sessions
```

#### Migration 3: `tx_identity_links` (Identity Stitching)

```
Tabel: tx_identity_links
- id: bigserial PRIMARY KEY
- tenant_id: bigint NOT NULL REFERENCES tenants(id)
- visitor_id: varchar(64) NOT NULL
- person_id: bigint NOT NULL REFERENCES core_customers(id)
- confidence: decimal(3,2) NOT NULL DEFAULT 1.0
- linked_at: timestamptz NOT NULL DEFAULT NOW()
- link_source: varchar(50) NOT NULL -- 'order_completed', 'login', 'manual'
- order_id: bigint REFERENCES orders(id)
- metadata: jsonb

Indexuri:
- (tenant_id, visitor_id)
- (tenant_id, person_id)
- UNIQUE (tenant_id, visitor_id, person_id)
```

#### Migration 4: Feature Store Tables

```
Tabel: fs_person_daily
- id: bigserial
- tenant_id: bigint NOT NULL
- person_id: bigint NOT NULL REFERENCES core_customers(id)
- date: date NOT NULL
- views_count: int DEFAULT 0
- carts_count: int DEFAULT 0
- checkouts_count: int DEFAULT 0
- purchases_count: int DEFAULT 0
- attendance_count: int DEFAULT 0
- gross_amount: decimal(12,2) DEFAULT 0
- net_amount: decimal(12,2) DEFAULT 0
- avg_order_value: decimal(10,2)
- avg_decision_time_ms: int
- discount_usage_rate: decimal(5,4)
- affiliate_rate: decimal(5,4)
- currency: varchar(3)
UNIQUE (tenant_id, person_id, date)

Tabel: fs_person_affinity_artist
- id: bigserial
- tenant_id: bigint NOT NULL
- person_id: bigint NOT NULL REFERENCES core_customers(id)
- artist_id: bigint NOT NULL REFERENCES artists(id)
- affinity_score: decimal(8,4) NOT NULL -- recency-weighted
- views_count: int DEFAULT 0
- purchases_count: int DEFAULT 0
- attendance_count: int DEFAULT 0
- last_interaction_at: timestamptz
- updated_at: timestamptz DEFAULT NOW()
UNIQUE (tenant_id, person_id, artist_id)

Tabel: fs_person_affinity_genre
- id: bigserial
- tenant_id: bigint NOT NULL
- person_id: bigint NOT NULL REFERENCES core_customers(id)
- genre: varchar(100) NOT NULL
- affinity_score: decimal(8,4) NOT NULL
- views_count: int DEFAULT 0
- purchases_count: int DEFAULT 0
- attendance_count: int DEFAULT 0
- last_interaction_at: timestamptz
- updated_at: timestamptz DEFAULT NOW()
UNIQUE (tenant_id, person_id, genre)

Tabel: fs_person_ticket_pref
- id: bigserial
- tenant_id: bigint NOT NULL
- person_id: bigint NOT NULL REFERENCES core_customers(id)
- ticket_category: varchar(50) NOT NULL -- GA, VIP, EarlyBird, etc.
- purchases_count: int DEFAULT 0
- avg_price: decimal(10,2)
- preference_score: decimal(5,4)
- price_band: varchar(20) -- low, mid, high, premium
- updated_at: timestamptz DEFAULT NOW()
UNIQUE (tenant_id, person_id, ticket_category)

Tabel: fs_event_funnel_hourly
- id: bigserial
- tenant_id: bigint NOT NULL
- event_entity_id: bigint NOT NULL REFERENCES events(id)
- hour: timestamptz NOT NULL
- page_views: int DEFAULT 0
- ticket_selections: int DEFAULT 0
- add_to_carts: int DEFAULT 0
- checkout_starts: int DEFAULT 0
- payment_attempts: int DEFAULT 0
- orders_completed: int DEFAULT 0
- revenue_gross: decimal(12,2) DEFAULT 0
- avg_time_to_cart_ms: int
- avg_time_to_checkout_ms: int
- avg_checkout_duration_ms: int
UNIQUE (tenant_id, event_entity_id, hour)
```

### 1.2 Schema Validation Service

```
File: app/Services/Tracking/SchemaValidator.php

Funcționalități:
- Încarcă schema_registry_v1.yaml
- Validează envelope (required fields)
- Validează payload per event_name
- Validează entities required per event
- Returnează erori specifice pentru debugging
```

### 1.3 API Endpoint Enhancement

```
File: app/Http/Controllers/Api/TxTrackingController.php

Endpoints:
POST /api/tx/events (single)
POST /api/tx/events/batch (batch, max 100)

Features:
- Schema validation per event
- Idempotency check (idempotency_key)
- Consent scope validation
- Queue to Redis for async processing
- Return processed count + errors
```

### 1.4 Event Processing Job

```
File: app/Jobs/ProcessTxEvents.php

Flow:
1. Dequeue events from Redis
2. Bulk insert to tx_events
3. Update tx_sessions (sequence_count, events_count)
4. Trigger identity stitching if order_completed + consent
5. Queue for feature store update (async)
```

---

## Faza 2: JavaScript SDK (tx-sdk.js)

### 2.1 Core Module

```javascript
// tx-sdk.js

class TxTracker {
  constructor(config) {
    this.tenantId = config.tenantId;
    this.siteId = config.siteId || null;
    this.apiEndpoint = config.apiEndpoint || '/api/tx/events/batch';
    this.debug = config.debug || false;

    // Initialize identifiers
    this.visitorId = this._getOrCreateVisitorId();
    this.sessionId = this._getOrCreateSessionId();
    this.sequenceNo = this._getSequenceNo();

    // First touch
    this._captureFirstTouch();

    // Consent
    this.consentSnapshot = this._getConsentSnapshot();

    // Event queue
    this.eventQueue = [];
    this.flushInterval = config.flushInterval || 5000;

    // Engagement tracking
    this._initEngagement();

    // Auto flush
    this._startAutoFlush();
  }

  // Visitor ID (persistent 365 days)
  _getOrCreateVisitorId() {
    let vid = localStorage.getItem('tx_vid');
    if (!vid) {
      vid = crypto.randomUUID();
      localStorage.setItem('tx_vid', vid);
    }
    return vid;
  }

  // Session ID (rolling 30 min)
  _getOrCreateSessionId() {
    const SESSION_TIMEOUT = 30 * 60 * 1000; // 30 min
    const stored = localStorage.getItem('tx_session');

    if (stored) {
      const { sid, lastActivity } = JSON.parse(stored);
      if (Date.now() - lastActivity < SESSION_TIMEOUT) {
        this._updateSessionActivity();
        return sid;
      }
    }

    // New session
    const sid = crypto.randomUUID();
    localStorage.setItem('tx_session', JSON.stringify({
      sid,
      lastActivity: Date.now(),
      seq: 0
    }));
    localStorage.setItem('tx_seq', '0');
    return sid;
  }

  _updateSessionActivity() {
    const stored = JSON.parse(localStorage.getItem('tx_session'));
    stored.lastActivity = Date.now();
    localStorage.setItem('tx_session', JSON.stringify(stored));
  }

  _getSequenceNo() {
    const seq = parseInt(localStorage.getItem('tx_seq') || '0');
    localStorage.setItem('tx_seq', String(seq + 1));
    return seq;
  }

  _captureFirstTouch() {
    if (localStorage.getItem('tx_first_touch')) return;

    const params = new URLSearchParams(window.location.search);
    const firstTouch = {
      referrer: document.referrer,
      landing_page: window.location.pathname,
      utm: {
        source: params.get('utm_source'),
        medium: params.get('utm_medium'),
        campaign: params.get('utm_campaign'),
        content: params.get('utm_content'),
        term: params.get('utm_term')
      },
      click_ids: {
        gclid: params.get('gclid'),
        fbclid: params.get('fbclid'),
        ttclid: params.get('ttclid')
      },
      captured_at: new Date().toISOString()
    };

    localStorage.setItem('tx_first_touch', JSON.stringify(firstTouch));
  }

  _getConsentSnapshot() {
    // Integrate with existing CookieConsent API
    const consent = JSON.parse(localStorage.getItem('tx_consent') || '{}');
    return {
      analytics: consent.analytics || false,
      marketing: consent.marketing || false,
      data_processing: consent.data_processing || false,
      captured_at: consent.captured_at || null,
      copy_hash: consent.copy_hash || null
    };
  }

  // Engagement tracking
  _initEngagement() {
    this.engagementStart = Date.now();
    this.activeTime = 0;
    this.isVisible = !document.hidden;
    this.isFocused = document.hasFocus();
    this.visibilityChanges = 0;
    this.focusChanges = 0;
    this.maxScrollPct = 0;
    this.lastActiveCheck = Date.now();

    // Visibility
    document.addEventListener('visibilitychange', () => {
      this.visibilityChanges++;
      if (document.hidden) {
        this._updateActiveTime();
      } else {
        this.lastActiveCheck = Date.now();
      }
      this.isVisible = !document.hidden;
    });

    // Focus
    window.addEventListener('focus', () => {
      this.focusChanges++;
      this.isFocused = true;
      this.lastActiveCheck = Date.now();
    });

    window.addEventListener('blur', () => {
      this.focusChanges++;
      this._updateActiveTime();
      this.isFocused = false;
    });

    // Scroll
    window.addEventListener('scroll', () => {
      const scrollPct = Math.round(
        (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100
      );
      this.maxScrollPct = Math.max(this.maxScrollPct, scrollPct);
    });

    // Page exit
    window.addEventListener('pagehide', () => this._sendPageEngagement());
    window.addEventListener('beforeunload', () => this._sendPageEngagement());
  }

  _updateActiveTime() {
    if (this.isVisible && this.isFocused) {
      this.activeTime += Date.now() - this.lastActiveCheck;
    }
    this.lastActiveCheck = Date.now();
  }

  _sendPageEngagement() {
    this._updateActiveTime();
    this.track('page_engagement', {
      active_ms: this.activeTime,
      total_ms: Date.now() - this.engagementStart,
      scroll_max_pct: this.maxScrollPct,
      visibility_changes: this.visibilityChanges,
      focus_changes: this.focusChanges
    }, { sendImmediately: true });
  }

  // Build event envelope
  _buildEnvelope(eventName, payload, entities = {}) {
    const firstTouch = JSON.parse(localStorage.getItem('tx_first_touch') || '{}');
    const params = new URLSearchParams(window.location.search);

    return {
      event_id: crypto.randomUUID(),
      event_name: eventName,
      event_version: 1,
      occurred_at: new Date().toISOString(),
      tenant_id: this.tenantId,
      site_id: this.siteId,
      source_system: 'web',
      visitor_id: this.visitorId,
      session_id: this.sessionId,
      sequence_no: this._getSequenceNo(),
      consent_snapshot: this.consentSnapshot,
      context: {
        page: {
          url: window.location.href,
          path: window.location.pathname,
          title: document.title,
          page_type: this._detectPageType()
        },
        referrer: document.referrer,
        utm: {
          source: params.get('utm_source') || firstTouch.utm?.source,
          medium: params.get('utm_medium') || firstTouch.utm?.medium,
          campaign: params.get('utm_campaign') || firstTouch.utm?.campaign,
          content: params.get('utm_content') || firstTouch.utm?.content,
          term: params.get('utm_term') || firstTouch.utm?.term
        },
        click_ids: {
          gclid: params.get('gclid') || firstTouch.click_ids?.gclid,
          fbclid: params.get('fbclid') || firstTouch.click_ids?.fbclid,
          ttclid: params.get('ttclid') || firstTouch.click_ids?.ttclid
        },
        device: {
          device_type: this._getDeviceType(),
          os: navigator.platform,
          browser: navigator.userAgent
        }
      },
      entities,
      payload,
      debug: this.debug ? { sdk_version: '1.0.0' } : undefined
    };
  }

  _detectPageType() {
    const path = window.location.pathname;
    if (path === '/' || path === '/home') return 'home';
    if (path.includes('/events') && !path.includes('/events/')) return 'listing';
    if (path.match(/\/events?\/[\w-]+/)) return 'event';
    if (path.includes('/checkout')) return 'checkout';
    if (path.includes('/artist')) return 'artist';
    if (path.includes('/venue')) return 'venue';
    if (path.includes('/shop') || path.includes('/product')) return 'shop';
    if (path.includes('/account')) return 'account';
    return 'other';
  }

  _getDeviceType() {
    const ua = navigator.userAgent;
    if (/tablet|ipad/i.test(ua)) return 'tablet';
    if (/mobile|iphone|android/i.test(ua)) return 'mobile';
    return 'desktop';
  }

  // Public API
  track(eventName, payload = {}, options = {}) {
    const { entities = {}, sendImmediately = false } = options;

    // Check consent for non-necessary events
    const eventConfig = this._getEventConfig(eventName);
    if (eventConfig?.scope === 'analytics' && !this.consentSnapshot.analytics) {
      if (this.debug) console.log('[TX] Blocked by consent:', eventName);
      return;
    }
    if (eventConfig?.scope === 'marketing' && !this.consentSnapshot.marketing) {
      if (this.debug) console.log('[TX] Blocked by consent:', eventName);
      return;
    }

    const event = this._buildEnvelope(eventName, payload, entities);

    if (sendImmediately) {
      this._sendEvents([event]);
    } else {
      this.eventQueue.push(event);
    }

    this._updateSessionActivity();

    return event.event_id;
  }

  // Convenience methods
  pageView(pageType, isFirstTouch = false) {
    return this.track('page_view', {
      page_type: pageType || this._detectPageType(),
      is_first_touch: isFirstTouch,
      navigation_type: this._getNavigationType()
    });
  }

  eventView(eventEntityId, priceFrom, currency, availability) {
    return this.track('event_view', {
      price_from: priceFrom,
      currency,
      availability_snapshot: availability
    }, { entities: { event_entity_id: eventEntityId } });
  }

  addToCart(cartId, eventEntityId, items, cartValue, currency) {
    return this.track('add_to_cart', {
      items,
      cart_value: cartValue,
      currency
    }, { entities: { cart_id: cartId, event_entity_id: eventEntityId } });
  }

  checkoutStarted(cartId, cartValue, currency, isAuthenticated) {
    return this.track('checkout_started', {
      cart_value: cartValue,
      currency,
      login_state: isAuthenticated ? 'authenticated' : 'guest'
    }, { entities: { cart_id: cartId } });
  }

  checkoutStepCompleted(cartId, stepName, stepDurationMs, errors = []) {
    return this.track('checkout_step_completed', {
      step_name: stepName,
      step_duration_ms: stepDurationMs,
      validation_errors: errors
    }, { entities: { cart_id: cartId } });
  }

  // Update consent
  setConsent(consent) {
    this.consentSnapshot = {
      ...this.consentSnapshot,
      ...consent,
      captured_at: new Date().toISOString()
    };
    localStorage.setItem('tx_consent', JSON.stringify(this.consentSnapshot));
  }

  // Flush queue
  async flush() {
    if (this.eventQueue.length === 0) return;

    const events = [...this.eventQueue];
    this.eventQueue = [];

    await this._sendEvents(events);
  }

  async _sendEvents(events) {
    try {
      const response = await fetch(this.apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ events }),
        keepalive: true
      });

      if (!response.ok) {
        console.error('[TX] Failed to send events:', response.status);
        // Re-queue failed events
        this.eventQueue.unshift(...events);
      }
    } catch (error) {
      console.error('[TX] Error sending events:', error);
      this.eventQueue.unshift(...events);
    }
  }

  _startAutoFlush() {
    setInterval(() => this.flush(), this.flushInterval);
  }

  _getNavigationType() {
    const nav = performance.getEntriesByType('navigation')[0];
    return nav?.type || 'unknown';
  }

  _getEventConfig(eventName) {
    // Simplified - in production, load from schema
    const analyticsEvents = ['page_view', 'page_engagement', 'event_view', 'add_to_cart', 'checkout_started'];
    const marketingEvents = ['pixel_event_enqueued'];

    if (analyticsEvents.includes(eventName)) return { scope: 'analytics' };
    if (marketingEvents.includes(eventName)) return { scope: 'marketing' };
    return { scope: 'necessary' };
  }
}

// Export
window.TxTracker = TxTracker;
```

### 2.2 Integration Points

```javascript
// Usage in tenant client app

// Initialize
const tx = new TxTracker({
  tenantId: 'tenant_123',
  siteId: 'main',
  apiEndpoint: 'https://api.example.com/api/tx/events/batch',
  debug: true
});

// Auto page view on load
document.addEventListener('DOMContentLoaded', () => {
  tx.pageView();
});

// Event view
tx.eventView('event_456', 50.00, 'EUR', { vip: 10, ga: 100 });

// Add to cart
tx.addToCart('cart_789', 'event_456', [
  { ticket_type_id: 'tt_1', qty: 2, price: 50.00 }
], 100.00, 'EUR');

// Checkout
tx.checkoutStarted('cart_789', 100.00, 'EUR', false);

// Consent update
tx.setConsent({ analytics: true, marketing: true });
```

---

## Faza 3: Server-Side Events & Identity Stitching

### 3.1 Order Completed Event Emission

```php
// app/Listeners/EmitOrderCompletedEvent.php

class EmitOrderCompletedEvent
{
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        // Build tx_event
        $txEvent = [
            'event_id' => Str::uuid(),
            'event_name' => 'order_completed',
            'event_version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'tenant_id' => $order->tenant_id,
            'source_system' => 'backend',
            'visitor_id' => $order->visitor_id,
            'session_id' => $order->session_id,
            'person_id' => $order->customer?->core_customer_id,
            'consent_snapshot' => $order->consent_snapshot ?? [],
            'entities' => [
                'order_id' => (string) $order->id,
                'event_entity_id' => (string) $order->event_id,
            ],
            'payload' => [
                'gross_amount' => $order->total,
                'net_amount' => $order->subtotal,
                'fees_amount' => $order->fees,
                'discount_total' => $order->discount_amount,
                'currency' => $order->currency,
                'items' => $order->items->map(fn($i) => [
                    'ticket_type_id' => $i->ticket_type_id,
                    'qty' => $i->quantity,
                    'price' => $i->unit_price,
                ])->toArray(),
                'affiliate_id' => $order->affiliate_id,
                'channel' => 'web',
                'consent_data_processing' => $order->consent_data_processing,
                'consent_marketing' => $order->consent_marketing,
            ],
            'idempotency_key' => "order_completed_{$order->id}",
        ];

        // Insert to tx_events
        TxEvent::create($txEvent);

        // Identity stitching
        if ($order->consent_data_processing && $order->visitor_id) {
            $this->stitchIdentity($order);
        }
    }

    protected function stitchIdentity(Order $order): void
    {
        $customer = $order->customer;
        if (!$customer?->core_customer_id) return;

        TxIdentityLink::updateOrCreate(
            [
                'tenant_id' => $order->tenant_id,
                'visitor_id' => $order->visitor_id,
                'person_id' => $customer->core_customer_id,
            ],
            [
                'confidence' => 1.0,
                'link_source' => 'order_completed',
                'order_id' => $order->id,
                'linked_at' => now(),
            ]
        );

        // Backfill person_id on historical events
        TxEvent::where('tenant_id', $order->tenant_id)
            ->where('visitor_id', $order->visitor_id)
            ->whereNull('person_id')
            ->update(['person_id' => $customer->core_customer_id]);
    }
}
```

### 3.2 Payment Webhook Events

```php
// app/Http/Controllers/Webhooks/PaymentWebhookController.php

public function handleStripeWebhook(Request $request): Response
{
    $payload = $request->all();
    $event = $payload['type'];

    switch ($event) {
        case 'payment_intent.succeeded':
            $this->emitPaymentSucceeded($payload);
            break;
        case 'payment_intent.payment_failed':
            $this->emitPaymentFailed($payload);
            break;
        case 'charge.refunded':
            $this->emitRefundCompleted($payload);
            break;
        case 'charge.dispute.created':
            $this->emitChargebackOpened($payload);
            break;
    }

    return response()->json(['received' => true]);
}

protected function emitPaymentSucceeded(array $payload): void
{
    $orderId = $payload['data']['object']['metadata']['order_id'] ?? null;
    if (!$orderId) return;

    $order = Order::find($orderId);
    if (!$order) return;

    TxEvent::create([
        'event_id' => Str::uuid(),
        'event_name' => 'payment_succeeded',
        'event_version' => 1,
        'occurred_at' => now()->toIso8601String(),
        'tenant_id' => $order->tenant_id,
        'source_system' => 'payments',
        'visitor_id' => $order->visitor_id,
        'session_id' => $order->session_id,
        'person_id' => $order->customer?->core_customer_id,
        'consent_snapshot' => ['analytics' => true, 'marketing' => false, 'data_processing' => true],
        'entities' => ['order_id' => (string) $order->id],
        'payload' => [
            'provider' => 'stripe',
            'provider_tx_id' => $payload['data']['object']['id'],
            'amount' => $payload['data']['object']['amount'] / 100,
            'currency' => strtoupper($payload['data']['object']['currency']),
            'latency_ms' => null,
        ],
        'idempotency_key' => "payment_succeeded_{$payload['id']}",
    ]);
}
```

---

## Faza 4: Scanner Events

### 4.1 React Native SDK Module

```typescript
// scanner/src/tracking/TxScannerTracker.ts

interface ScannerSession {
  sessionId: string;
  eventEntityId: string;
  venueId: string;
  staffId: string;
  gateId: string;
  deviceId: string;
  startedAt: string;
  isOffline: boolean;
}

class TxScannerTracker {
  private apiEndpoint: string;
  private eventQueue: any[] = [];
  private currentSession: ScannerSession | null = null;

  constructor(config: { apiEndpoint: string }) {
    this.apiEndpoint = config.apiEndpoint;
  }

  startSession(params: {
    eventEntityId: string;
    venueId: string;
    staffId: string;
    gateId: string;
    deviceId: string;
    appVersion: string;
    isOffline: boolean;
  }) {
    this.currentSession = {
      sessionId: this.generateUUID(),
      eventEntityId: params.eventEntityId,
      venueId: params.venueId,
      staffId: params.staffId,
      gateId: params.gateId,
      deviceId: params.deviceId,
      startedAt: new Date().toISOString(),
      isOffline: params.isOffline,
    };

    this.track('scanner_session_started', {
      scanner_session_id: this.currentSession.sessionId,
      staff_id: params.staffId,
      gate_id: params.gateId,
      device_id: params.deviceId,
      app_version: params.appVersion,
      offline_mode: params.isOffline,
    }, {
      event_entity_id: params.eventEntityId,
      venue_id: params.venueId,
    });

    return this.currentSession.sessionId;
  }

  trackScanAttempt(ticketId: string, scanType: 'entry' | 'exit' | 'reentry', qrHash: string) {
    if (!this.currentSession) throw new Error('No active session');

    return this.track('ticket_scan_attempted', {
      scanner_session_id: this.currentSession.sessionId,
      gate_id: this.currentSession.gateId,
      scan_type: scanType,
      qr_payload_hash: qrHash,
    }, {
      event_entity_id: this.currentSession.eventEntityId,
      ticket_id: ticketId,
    });
  }

  trackScanResult(
    ticketId: string,
    scanType: 'entry' | 'exit' | 'reentry',
    result: 'valid' | 'invalid' | 'already_used' | 'wrong_gate' | 'wrong_day' | 'refunded' | 'unknown',
    reasonCode: string,
    latencyMs: number,
    offlineResolution: boolean
  ) {
    if (!this.currentSession) throw new Error('No active session');

    return this.track('ticket_scan_result', {
      scanner_session_id: this.currentSession.sessionId,
      gate_id: this.currentSession.gateId,
      scan_type: scanType,
      result,
      reason_code: reasonCode,
      latency_ms: latencyMs,
      offline_resolution: offlineResolution,
    }, {
      event_entity_id: this.currentSession.eventEntityId,
      ticket_id: ticketId,
    });
  }

  trackEntryGranted(ticketId: string, beneficiaryId?: string) {
    if (!this.currentSession) throw new Error('No active session');

    return this.track('entry_granted', {
      gate_id: this.currentSession.gateId,
      entry_time: new Date().toISOString(),
      beneficiary_id: beneficiaryId,
    }, {
      event_entity_id: this.currentSession.eventEntityId,
      ticket_id: ticketId,
    });
  }

  trackCapacitySnapshot(currentInside: number, totalEntries: number, totalDenied: number, ratePerMinute: number) {
    if (!this.currentSession) throw new Error('No active session');

    return this.track('capacity_snapshot', {
      gate_id: this.currentSession.gateId,
      current_inside: currentInside,
      total_entries: totalEntries,
      total_denied: totalDenied,
      rate_per_minute: ratePerMinute,
    }, {
      event_entity_id: this.currentSession.eventEntityId,
      venue_id: this.currentSession.venueId,
    });
  }

  endSession(totals: object) {
    if (!this.currentSession) return;

    this.track('scanner_session_ended', {
      scanner_session_id: this.currentSession.sessionId,
      totals,
    }, {
      event_entity_id: this.currentSession.eventEntityId,
    });

    this.currentSession = null;
    this.flush();
  }

  private track(eventName: string, payload: object, entities: object) {
    const event = {
      event_id: this.generateUUID(),
      event_name: eventName,
      event_version: 1,
      occurred_at: new Date().toISOString(),
      source_system: 'scanner',
      consent_snapshot: { analytics: true, marketing: false, data_processing: true },
      entities,
      payload,
    };

    this.eventQueue.push(event);

    // Auto-flush every 10 events or send critical events immediately
    if (this.eventQueue.length >= 10 || ['entry_granted', 'entry_denied'].includes(eventName)) {
      this.flush();
    }

    return event.event_id;
  }

  async flush() {
    if (this.eventQueue.length === 0) return;

    const events = [...this.eventQueue];
    this.eventQueue = [];

    try {
      await fetch(this.apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ events }),
      });
    } catch (error) {
      // Store offline for later sync
      await this.storeOffline(events);
    }
  }

  private async storeOffline(events: any[]) {
    // Use AsyncStorage for React Native
    const stored = JSON.parse(await AsyncStorage.getItem('tx_offline_events') || '[]');
    stored.push(...events);
    await AsyncStorage.setItem('tx_offline_events', JSON.stringify(stored));
  }

  async syncOfflineEvents() {
    const stored = JSON.parse(await AsyncStorage.getItem('tx_offline_events') || '[]');
    if (stored.length === 0) return;

    try {
      await fetch(this.apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ events: stored }),
      });
      await AsyncStorage.removeItem('tx_offline_events');
    } catch (error) {
      console.error('Failed to sync offline events:', error);
    }
  }

  private generateUUID(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }
}

export default TxScannerTracker;
```

---

## Faza 5: Feature Store Jobs

### 5.1 Daily Person Aggregation

```php
// app/Jobs/AggregatePersonDaily.php

class AggregatePersonDaily implements ShouldQueue
{
    public function handle(): void
    {
        $yesterday = now()->subDay()->toDateString();

        // Get all persons with events yesterday
        $persons = DB::table('tx_events')
            ->whereNotNull('person_id')
            ->whereDate('occurred_at', $yesterday)
            ->select('tenant_id', 'person_id')
            ->distinct()
            ->get();

        foreach ($persons as $person) {
            $this->aggregatePerson($person->tenant_id, $person->person_id, $yesterday);
        }
    }

    protected function aggregatePerson(int $tenantId, int $personId, string $date): void
    {
        $events = TxEvent::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->whereDate('occurred_at', $date)
            ->get();

        $metrics = [
            'views_count' => $events->where('event_name', 'event_view')->count(),
            'carts_count' => $events->where('event_name', 'add_to_cart')->count(),
            'checkouts_count' => $events->where('event_name', 'checkout_started')->count(),
            'purchases_count' => $events->where('event_name', 'order_completed')->count(),
            'attendance_count' => $events->where('event_name', 'entry_granted')->count(),
        ];

        $orders = $events->where('event_name', 'order_completed');
        $metrics['gross_amount'] = $orders->sum('payload.gross_amount');
        $metrics['net_amount'] = $orders->sum('payload.net_amount');

        if ($metrics['purchases_count'] > 0) {
            $metrics['avg_order_value'] = $metrics['gross_amount'] / $metrics['purchases_count'];
        }

        // Calculate decision time (event_view -> order_completed)
        $metrics['avg_decision_time_ms'] = $this->calculateAvgDecisionTime($events);

        FsPersonDaily::updateOrCreate(
            ['tenant_id' => $tenantId, 'person_id' => $personId, 'date' => $date],
            $metrics
        );
    }

    protected function calculateAvgDecisionTime(Collection $events): ?int
    {
        $views = $events->where('event_name', 'event_view')->keyBy('entities.event_entity_id');
        $purchases = $events->where('event_name', 'order_completed');

        $times = [];
        foreach ($purchases as $purchase) {
            $eventId = $purchase->entities['event_entity_id'] ?? null;
            if ($eventId && isset($views[$eventId])) {
                $viewTime = Carbon::parse($views[$eventId]->occurred_at);
                $purchaseTime = Carbon::parse($purchase->occurred_at);
                $times[] = $purchaseTime->diffInMilliseconds($viewTime);
            }
        }

        return count($times) > 0 ? array_sum($times) / count($times) : null;
    }
}
```

### 5.2 Affinity Score Calculation

```php
// app/Jobs/CalculateAffinityScores.php

class CalculateAffinityScores implements ShouldQueue
{
    protected const WEIGHTS = [
        'event_view' => 1,
        'add_to_cart' => 3,
        'checkout_started' => 5,
        'order_completed' => 10,
        'entry_granted' => 12,
    ];

    protected const DECAY_DAYS = 60; // τ for recency decay

    public function handle(): void
    {
        $persons = DB::table('tx_events')
            ->whereNotNull('person_id')
            ->where('occurred_at', '>=', now()->subDays(90))
            ->select('tenant_id', 'person_id')
            ->distinct()
            ->get();

        foreach ($persons as $person) {
            $this->calculateArtistAffinity($person->tenant_id, $person->person_id);
            $this->calculateGenreAffinity($person->tenant_id, $person->person_id);
        }
    }

    protected function calculateArtistAffinity(int $tenantId, int $personId): void
    {
        $events = TxEvent::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->whereIn('event_name', array_keys(self::WEIGHTS))
            ->whereNotNull('entities->event_entity_id')
            ->where('occurred_at', '>=', now()->subDays(90))
            ->get();

        $artistScores = [];

        foreach ($events as $event) {
            $eventEntityId = $event->entities['event_entity_id'];
            $eventEntity = Event::with('artists')->find($eventEntityId);

            if (!$eventEntity) continue;

            $weight = self::WEIGHTS[$event->event_name] ?? 0;
            $daysSince = Carbon::parse($event->occurred_at)->diffInDays(now());
            $decayedWeight = $weight * exp(-$daysSince / self::DECAY_DAYS);

            foreach ($eventEntity->artists as $artist) {
                if (!isset($artistScores[$artist->id])) {
                    $artistScores[$artist->id] = [
                        'score' => 0,
                        'views' => 0,
                        'purchases' => 0,
                        'attendance' => 0,
                        'last_interaction' => null,
                    ];
                }

                $artistScores[$artist->id]['score'] += $decayedWeight;

                if ($event->event_name === 'event_view') $artistScores[$artist->id]['views']++;
                if ($event->event_name === 'order_completed') $artistScores[$artist->id]['purchases']++;
                if ($event->event_name === 'entry_granted') $artistScores[$artist->id]['attendance']++;

                $eventTime = Carbon::parse($event->occurred_at);
                if (!$artistScores[$artist->id]['last_interaction'] ||
                    $eventTime->gt($artistScores[$artist->id]['last_interaction'])) {
                    $artistScores[$artist->id]['last_interaction'] = $eventTime;
                }
            }
        }

        // Upsert artist affinities
        foreach ($artistScores as $artistId => $data) {
            FsPersonAffinityArtist::updateOrCreate(
                ['tenant_id' => $tenantId, 'person_id' => $personId, 'artist_id' => $artistId],
                [
                    'affinity_score' => $data['score'],
                    'views_count' => $data['views'],
                    'purchases_count' => $data['purchases'],
                    'attendance_count' => $data['attendance'],
                    'last_interaction_at' => $data['last_interaction'],
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function calculateGenreAffinity(int $tenantId, int $personId): void
    {
        // Similar logic but aggregate by genre from events->artists->genres
        // Implementation follows same pattern as artist affinity
    }
}
```

---

## Faza 6: Audience Builder API

### 6.1 Audience Estimation Endpoint

```php
// app/Http/Controllers/Api/V1/AudienceController.php

class AudienceController extends Controller
{
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'event_hypothesis' => 'required|array',
            'event_hypothesis.artist_ids' => 'array',
            'event_hypothesis.genres' => 'array',
            'event_hypothesis.city' => 'string',
            'event_hypothesis.venue_id' => 'string',
            'event_hypothesis.price_from' => 'numeric',
            'event_hypothesis.price_to' => 'numeric',
            'event_hypothesis.ticket_category' => 'string',
        ]);

        $tenantId = $validated['tenant_id'];
        $hypothesis = $validated['event_hypothesis'];

        // Build propensity query
        $query = CoreCustomer::where('marketing_consent', true)
            ->whereJsonContains('tenant_ids', $tenantId);

        // Get eligible persons with affinity scores
        $persons = $query->get();

        $results = [];
        foreach ($persons as $person) {
            $propensity = $this->calculatePropensity($person, $hypothesis, $tenantId);
            if ($propensity > 0.1) { // Threshold
                $results[] = [
                    'person_id' => $person->id,
                    'propensity' => $propensity,
                    'expected_value' => $propensity * ($hypothesis['price_from'] ?? 50),
                ];
            }
        }

        $eligibleCount = count($results);
        $expectedBuyers = array_sum(array_column($results, 'propensity'));
        $expectedGmv = array_sum(array_column($results, 'expected_value'));

        // Score distribution
        $distribution = [
            '0.0-0.2' => count(array_filter($results, fn($r) => $r['propensity'] < 0.2)),
            '0.2-0.4' => count(array_filter($results, fn($r) => $r['propensity'] >= 0.2 && $r['propensity'] < 0.4)),
            '0.4-0.6' => count(array_filter($results, fn($r) => $r['propensity'] >= 0.4 && $r['propensity'] < 0.6)),
            '0.6-0.8' => count(array_filter($results, fn($r) => $r['propensity'] >= 0.6 && $r['propensity'] < 0.8)),
            '0.8-1.0' => count(array_filter($results, fn($r) => $r['propensity'] >= 0.8)),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'eligible_users' => $eligibleCount,
                'expected_buyers' => round($expectedBuyers, 1),
                'expected_gmv' => round($expectedGmv, 2),
                'currency' => 'EUR',
                'distribution' => $distribution,
            ],
        ]);
    }

    protected function calculatePropensity(CoreCustomer $person, array $hypothesis, int $tenantId): float
    {
        // Coefficients (tune based on data)
        $a = 0.3; // genre_affinity
        $b = 0.3; // artist_affinity
        $c = 0.15; // city_affinity
        $d = 0.1; // price_fit
        $e = 0.1; // recency
        $f = 0.1; // attendance_rate
        $g = 0.05; // discount_dependency_penalty

        // Genre affinity
        $genreAffinity = 0;
        if (!empty($hypothesis['genres'])) {
            $affinities = FsPersonAffinityGenre::where('person_id', $person->id)
                ->whereIn('genre', $hypothesis['genres'])
                ->avg('affinity_score') ?? 0;
            $genreAffinity = min($affinities / 100, 1); // Normalize
        }

        // Artist affinity
        $artistAffinity = 0;
        if (!empty($hypothesis['artist_ids'])) {
            $affinities = FsPersonAffinityArtist::where('person_id', $person->id)
                ->whereIn('artist_id', $hypothesis['artist_ids'])
                ->avg('affinity_score') ?? 0;
            $artistAffinity = min($affinities / 100, 1);
        }

        // City affinity (from demographics)
        $cityAffinity = 0;
        if (!empty($hypothesis['city']) && $person->city === $hypothesis['city']) {
            $cityAffinity = 1;
        }

        // Price fit
        $priceFit = 1;
        if (!empty($hypothesis['price_from'])) {
            $avgSpend = $person->average_order_value ?? 50;
            $targetPrice = ($hypothesis['price_from'] + ($hypothesis['price_to'] ?? $hypothesis['price_from'])) / 2;
            $priceFit = 1 - min(abs($avgSpend - $targetPrice) / 100, 1);
        }

        // Recency (days since last purchase)
        $recency = 0;
        if ($person->last_purchase_at) {
            $daysSince = Carbon::parse($person->last_purchase_at)->diffInDays(now());
            $recency = exp(-$daysSince / 90); // 90-day decay
        }

        // Attendance rate
        $attendanceRate = $person->total_events_attended / max($person->total_orders, 1);
        $attendanceRate = min($attendanceRate, 1);

        // Discount dependency (penalize heavy discount users)
        $discountPenalty = 0;
        // Could calculate from order history

        $score = $a * $genreAffinity
               + $b * $artistAffinity
               + $c * $cityAffinity
               + $d * $priceFit
               + $e * $recency
               + $f * $attendanceRate
               - $g * $discountPenalty;

        // Sigmoid to convert to probability
        return 1 / (1 + exp(-$score * 4)); // Scale factor 4 for reasonable spread
    }

    public function build(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'event_hypothesis' => 'required|array',
            'min_propensity' => 'numeric|min:0|max:1',
        ]);

        // Create audience with filters
        $audience = TxAudience::create([
            'tenant_id' => $validated['tenant_id'],
            'name' => $validated['name'],
            'definition' => $validated['event_hypothesis'],
            'created_by' => auth()->id(),
        ]);

        // Populate members
        $estimation = $this->estimate($request);
        // ... populate tx_audience_members from estimation results

        return response()->json([
            'success' => true,
            'data' => [
                'audience_id' => $audience->id,
                'member_count' => $audience->members()->count(),
            ],
        ]);
    }
}
```

---

## Summary: Development Phases

| Fază | Componente | Efort Estimat |
|------|------------|---------------|
| 1 | Migrations (tx_events, tx_sessions, tx_identity_links, fs_*), SchemaValidator, API endpoints | 3-4 zile |
| 2 | JS SDK (tx-sdk.js) cu session mgmt, engagement, consent | 2-3 zile |
| 3 | Server-side events (order_completed, payment webhooks), identity stitching | 2 zile |
| 4 | Scanner SDK (React Native) | 2 zile |
| 5 | Feature Store jobs (daily aggregation, affinity scores) | 2-3 zile |
| 6 | Audience Builder API (estimate, build) | 2 zile |
| **Total** | | **13-16 zile** |

---

## Files to Create/Modify

### New Files
- `config/tracking/schema_registry_v1.yaml` ✅
- `database/migrations/2025_01_XX_create_tx_events_table.php`
- `database/migrations/2025_01_XX_create_tx_identity_links_table.php`
- `database/migrations/2025_01_XX_create_feature_store_tables.php`
- `app/Models/Tracking/TxEvent.php`
- `app/Models/Tracking/TxSession.php`
- `app/Models/Tracking/TxIdentityLink.php`
- `app/Models/FeatureStore/FsPersonDaily.php`
- `app/Models/FeatureStore/FsPersonAffinityArtist.php`
- `app/Models/FeatureStore/FsPersonAffinityGenre.php`
- `app/Models/FeatureStore/FsPersonTicketPref.php`
- `app/Services/Tracking/SchemaValidator.php`
- `app/Services/Tracking/TxEventProcessor.php`
- `app/Http/Controllers/Api/TxTrackingController.php`
- `app/Http/Controllers/Api/V1/AudienceController.php`
- `app/Jobs/ProcessTxEvents.php`
- `app/Jobs/AggregatePersonDaily.php`
- `app/Jobs/CalculateAffinityScores.php`
- `app/Listeners/EmitOrderCompletedEvent.php`
- `public/js/tx-sdk.js` (or npm package)
- `scanner/src/tracking/TxScannerTracker.ts`

### Modified Files
- `routes/api.php` - Add new routes
- `app/Providers/EventServiceProvider.php` - Register listeners
- `app/Console/Kernel.php` - Schedule jobs
