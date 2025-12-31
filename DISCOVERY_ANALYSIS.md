# Discovery Analysis: Event Commerce + Data Intelligence Rail

## Executive Summary

Branch-ul `core-main` conține deja o infrastructură substanțială pentru tracking, consent management, și multi-tenancy. Acest document mapează ce există versus ce este descris în planul propus.

---

## 1. Entități Existente vs. Planificate

### 1.1 Core Entities (EXISTĂ)

| Entity | Tabel | Status | Note |
|--------|-------|--------|------|
| Tenant | `tenants` | ✅ Complet | Multi-tenancy robust cu settings JSON, features, billing |
| Event (muzical) | `events` | ✅ Complet | Include venue_id, artists, ticket_types, translatables |
| Artist | `artists` | ✅ Complet | Cu genres, types, social stats, KPIs |
| Venue | `venues` | ✅ Complet | Cu facilities, venue_types, capacity |
| TicketType | `ticket_types` | ✅ Complet | Cu pricing (price_cents, sale_price_cents), quota |
| Order | `orders` | ✅ Complet | Cu customer_id, promo_code, status, marketplace fields |
| Customer | `customers` | ✅ Complet | Cu points, referral_code, multi-tenant support |
| Ticket | `tickets` | ✅ Complet | Cu ticket_type_id, order_id, status |

### 1.2 Tracking Infrastructure (EXISTĂ PARȚIAL)

| Component | Tabel/Model | Status | Gap Analysis |
|-----------|-------------|--------|--------------|
| CoreCustomer | `core_customers` | ✅ Există | Platform-wide customer cu PII encryption, RFM scores, attribution |
| CoreCustomerEvent | `core_customer_events` | ✅ Există | Event tracking cu tipuri limitate |
| CoreSession | `core_sessions` | ✅ Există | Session tracking de bază |
| CookieConsent | `cookie_consents` | ✅ Există | Consent GDPR complet cu history |
| AnalyticsEvent | `analytics_events` | ✅ Există | Tracking simplu (page_view, purchase, check_in, cart_add) |
| TrackingIntegration | `tracking_integrations` | ✅ Există | GA4, GTM, Meta, TikTok pixels |

### 1.3 Platform/Audience Infrastructure (EXISTĂ)

| Component | Tabel | Status | Note |
|-----------|-------|--------|------|
| PlatformAdAccount | `platform_ad_accounts` | ✅ Există | Google, Meta, TikTok, LinkedIn |
| PlatformAudience | `platform_audiences` | ✅ Există | Audience builder cu segment_rules, lookalikes |
| PlatformAudienceMember | `platform_audience_members` | ✅ Există | Membership cu sync_status |
| PlatformConversion | `platform_conversions` | ✅ Există | Conversion tracking pentru CAPI |

### 1.4 Ce LIPSEȘTE pentru Planul Propus

| Component | Status | Acțiune Necesară |
|-----------|--------|------------------|
| `tx_events` (raw events partitioned) | ❌ Nu există | Nou tabel pentru volum mare cu partitioning |
| `tx_identity_links` (visitor → person) | ❌ Nu există | Nou tabel pentru identity stitching |
| `tx_sessions` (enhanced) | ⚠️ Parțial | `core_sessions` există dar lipsesc first_touch details |
| `tx_consents` (audit trail) | ✅ Există | `cookie_consent_history` deja prezent |
| Feature Store (fs_*) | ❌ Nu există | Tabele noi pentru agregări și preferințe |
| Schema Registry | ❌ Nu există | Event schema validation |
| Sequencing (sequence_no, prev_event_id) | ❌ Nu există | Add la event tracking |
| page_engagement (active_ms) | ❌ Nu există | JS SDK enhancement |

---

## 2. Mapping Evenimente Existente vs. Planificate

### 2.1 Evenimente Web

| Eveniment Planificat | Status Curent | Implementare |
|---------------------|---------------|--------------|
| page_view | ✅ Există | `PlatformTrackingController::trackEvents()` |
| page_engagement (active_ms) | ❌ Nu există | JS SDK nou necesar |
| search_performed | ❌ Nu există | De implementat |
| event_card_impression | ❌ Nu există | De implementat |
| event_card_click | ❌ Nu există | De implementat |
| event_view | ⚠️ Parțial | `view_item` există, redenumire |
| ticket_type_selected | ❌ Nu există | De implementat |
| add_to_cart | ✅ Există | `trackAddToCart()` |
| remove_from_cart | ❌ Nu există | De implementat |
| cart_view | ❌ Nu există | De implementat |
| checkout_started | ✅ Există | `begin_checkout` |
| checkout_step_viewed/completed | ❌ Nu există | De implementat cu step_duration_ms |
| beneficiary_added/updated/removed | ❌ Nu există | De implementat |
| discount_code_applied/rejected | ❌ Nu există | De implementat |
| affiliate_attribution_captured | ⚠️ Parțial | `affiliate_clicks` există |
| payment_method_selected | ❌ Nu există | De implementat |
| payment_attempted | ❌ Nu există | De implementat |
| payment_succeeded/failed | ⚠️ Parțial | order status updates |
| order_completed | ✅ Există | `purchase` event |

### 2.2 Evenimente Scanner (Mobile)

| Eveniment | Status | Note |
|-----------|--------|------|
| scanner_session_started/ended | ❌ Nu există | Scanner React nu are tracking |
| ticket_scan_attempted | ❌ Nu există | De implementat |
| ticket_scan_result | ❌ Nu există | De implementat |
| entry_granted/denied | ❌ Nu există | De implementat |
| capacity_snapshot | ❌ Nu există | De implementat |

### 2.3 Evenimente Shop

| Eveniment | Status | Note |
|-----------|--------|------|
| product_view | ⚠️ Parțial | Shop module există, tracking nu |
| add_to_cart_shop | ❌ Nu există | Shop uses ShopCartController |
| checkout_started_shop | ❌ Nu există | ShopCheckoutController există |
| order_completed_shop | ⚠️ Parțial | ShopOrderCompleted event |
| shipment_created/delivered | ❌ Nu există | De implementat |

### 2.4 Module Speciale

| Modul | Status Tracking | Note |
|-------|-----------------|------|
| Discount Codes | ⚠️ Events Laravel | PromoCodeUsed, PromoCodeCreated etc. |
| Affiliates | ✅ Tracking | affiliate_clicks, affiliate_conversions |
| Gamification | ⚠️ Events Laravel | points_earned via PointsTransaction |
| Wallet | ⚠️ Model only | WalletPass, WalletPassUpdate models |

---

## 3. Schema Eveniment (Envelope) - Gap Analysis

### 3.1 Câmpuri Obligatorii din Plan vs. Existente

| Câmp | core_customer_events | tx_events (necesar) |
|------|---------------------|---------------------|
| event_id (uuid) | ✅ id (serial) | Trebuie adăugat UUID |
| event_name | ✅ event_type | OK |
| event_version | ❌ Nu există | Trebuie adăugat |
| occurred_at | ✅ occurred_at | OK |
| tenant_id | ✅ tenant_id | OK |
| site_id | ❌ Nu există | Trebuie adăugat (pentru multi-site) |
| source_system | ❌ Nu există | Trebuie adăugat (web/mobile/scanner/backend) |
| visitor_id | ✅ visitor_id | OK |
| session_id | ✅ session_id | OK (dar e FK nu string) |
| sequence_no | ❌ Nu există | Trebuie adăugat |
| consent_state | ❌ Nu există | Trebuie adăugat (snapshot) |
| context (jsonb) | ⚠️ Parțial | utm_, referrer există separat |
| entities (jsonb) | ⚠️ Parțial | event_id, order_id există separat |
| payload (jsonb) | ✅ event_data | OK |
| idempotency_key | ❌ Nu există | Trebuie adăugat |

---

## 4. Identitate & Stitching - Gap Analysis

### 4.1 Identificatori Client (localStorage)

| Identificator | Status | Implementare Curentă |
|---------------|--------|---------------------|
| tx_vid (visitor_id) | ⚠️ Parțial | Trimis de client, nu gestionat explicit |
| tx_sid (session_id) | ⚠️ Parțial | Trimis de client |
| tx_seq (sequence counter) | ❌ Nu există | De implementat în JS SDK |
| tx_first_touch | ❌ Nu există | De implementat |
| tx_consent | ✅ Există | cookie_consents tabel |

### 4.2 Identity Stitching

| Component | Status | Note |
|-----------|--------|------|
| visitor_id → person_id link | ❌ Nu există | Trebuie tx_identity_links |
| Link la order_completed | ⚠️ Parțial | Order are customer_id dar nu visitor_id |
| Consent validation | ✅ Există | CookieConsent model complet |

**Gap Critic**: `customers` table are `visitor_id` (adăugat recent via migration `2025_12_11_100000_add_visitor_id_to_core_customers.php`) dar nu există tabel de link pentru stitching istoric.

---

## 5. Feature Store - Gap Analysis (TOT LIPSEȘTE)

| Tabel Planificat | Status | Descripție |
|------------------|--------|------------|
| fs_person_daily | ❌ | Agregări zilnice per person |
| fs_person_affinity_artist | ❌ | Scor afinitate per artist |
| fs_person_affinity_genre | ❌ | Scor afinitate per gen |
| fs_person_ticket_pref | ❌ | Preferințe ticket category/price |
| fs_event_funnel_hourly | ❌ | Funnel metrics pe eveniment |
| fs_event_attendance | ❌ | Attendance facts |

---

## 6. Audience Builder - Gap Analysis

### 6.1 Ce Există

- `PlatformAudience` model cu:
  - `segment_rules` (JSON filters)
  - `audience_type` (all_customers, purchasers, high_value, etc.)
  - `getCustomersQuery()` pentru filtrare
  - Lookalike support

- `CoreCustomer` model cu:
  - RFM scores (rfm_recency_score, rfm_frequency_score, rfm_monetary_score)
  - customer_segment (VIP, At Risk, etc.)
  - engagement_score, churn_risk_score
  - purchase_likelihood_score

### 6.2 Ce Lipsește pentru Plan

| Component | Status | Note |
|-----------|--------|------|
| Propensity scoring composit | ❌ | Formula din plan nu e implementată |
| POST /api/v1/audience/estimate | ❌ | Endpoint lipsă |
| POST /api/v1/audience/build | ❌ | Endpoint lipsă |
| expected_buyers, expected_gmv | ❌ | Nu se calculează |
| Artist/genre affinity în query | ❌ | Nu există fs_person_affinity_* |

---

## 7. JS SDK - Gap Analysis

### 7.1 Ce Există

**PlatformTrackingController** acceptă:
```json
{
  "tenantId": "...",
  "userData": { "visitorId": "...", "sessionId": "..." },
  "deviceInfo": { "deviceType": "...", "browser": "..." },
  "events": [
    { "eventType": "page_view", "timestamp": 123, "pageUrl": "..." }
  ]
}
```

**Client-side** (marketplace-clients/ambilet): Basic app fără tracking SDK integrat.

### 7.2 Ce Lipsește

| Feature | Status | Note |
|---------|--------|------|
| Session management (30min rolling) | ❌ | Client trimite dar nu gestionează corect |
| Sequence numbering | ❌ | Nu există sequence_no |
| Engagement tracking (active_ms) | ❌ | Nu există visibility/focus tracking |
| First touch capture | ❌ | UTMs/click IDs nu sunt persistate în localStorage |
| Consent gating | ⚠️ Parțial | CookieConsent API există, integrare JS lipsește |
| Auto page_view | ⚠️ Parțial | Trebuie îmbunătățit |
| page_engagement la exit | ❌ | Nu există |

---

## 8. Servicii & Controllers Existente

### 8.1 Controllers Relevante

| Controller | Responsabilitate |
|------------|------------------|
| `PlatformTrackingController` | Primire evenimente tracking |
| `TrackingController` | Config & consent management |
| `CheckoutController` | Checkout flow (gamification, affiliates) |
| `CookieConsentController` | GDPR consent API |
| `AffiliateController` | Affiliate tracking |

### 8.2 Services Relevante

| Service | Responsabilitate |
|---------|------------------|
| `PlatformTrackingService` | Event processing, session/customer management |
| `ConsentServiceInterface` | Consent abstraction |
| `TrackingScriptInjector` | Pixel injection |
| `AffiliateTrackingService` | Affiliate attribution |
| `GamificationService` | Points management |

---

## 9. Recomandări pentru Implementare

### Faza 0 - Discovery (COMPLETĂ)

✅ Identificat tabelele existente
✅ Mapare IDs & naming
✅ Înțeles flow-ul checkout
✅ Înțeles injectare JS în HTML tenant

### Faza 1 - Tracking Foundations (NECESAR)

1. **Migrări noi**:
   - `tx_events` (partitioned by month)
   - `tx_sessions` (enhanced cu first_touch)
   - `tx_identity_links` (visitor_id → person_id)
   - Adaugă `sequence_no`, `event_version`, `source_system` la events

2. **API collect** - Extinde `PlatformTrackingController`:
   - Support batch events
   - Validare schema per event_name
   - Idempotency via key

3. **Schema registry** - Config file cu event definitions

### Faza 2 - SDK Enhancement (NECESAR)

1. **Nou SDK JS** (`tx-sdk.js`):
   - `tx_vid` persistent (localStorage)
   - `tx_sid` rolling (30min inactivity)
   - `tx_seq` counter
   - `tx_first_touch` capture
   - `page_engagement` cu active_ms
   - Consent banner hooks

### Faza 3 - Server-side Truth (NECESAR)

1. **Webhook handlers** pentru payment providers
2. **order_completed** event emission
3. **Identity stitching** la order confirmation

### Faza 4 - Feature Store (NECESAR)

1. **Jobs Laravel** pentru agregări:
   - Daily person metrics
   - Funnel timing calculations
   - Affinity scores (recency-weighted)

2. **Tabele fs_***:
   - person_daily
   - person_affinity_artist
   - person_affinity_genre
   - person_ticket_pref

### Faza 5 - Audience Builder (NECESARĂ EXTINDERE)

1. **Endpoints noi**:
   - `POST /api/v1/audience/estimate`
   - `POST /api/v1/audience/build`

2. **Propensity scoring**:
   - Formula compozită din plan
   - Integrare cu fs_* tables

---

## 10. Diagrame Entități

### 10.1 Flow Actual (Simplificat)

```
[Tenant Website]
    ↓ POST /api/tracking/events
[PlatformTrackingController]
    ↓
[PlatformTrackingService]
    ↓ Creates
[core_customer_events] ← [core_sessions] ← [core_customers]
    ↓
[Dual Tracking]
    ├→ [platform_conversions] → [Platform Ad Accounts (Core)]
    └→ [Tenant Ad Accounts] via CAPI services
```

### 10.2 Flow Planificat (Adăugări)

```
[Tenant Website + TX SDK]
    ↓ visitorId, sessionId, sequenceNo, active_ms
    ↓ POST /api/tracking/events (batch, schema-validated)
[Enhanced PlatformTrackingController]
    ↓
[tx_events (partitioned)] ← [tx_sessions] ← [tx_identity_links] ← [core_customers/person]
    ↓ Jobs (daily/hourly)
[Feature Store: fs_person_*, fs_event_*]
    ↓
[Audience Builder API]
    ↓
[propensity scores, expected_buyers, expected_gmv]
```

---

## 11. Concluzie

**Ce avem**: O fundație solidă cu:
- Multi-tenancy complet
- Tracking de bază funcțional
- Consent management GDPR
- Platform audience/conversion infrastructure
- Integrări ad platforms (Google, Meta, TikTok, LinkedIn)

**Ce lipsește**:
- Event schema standardizat cu versioning
- Sequencing & engagement tracking în SDK
- Identity stitching explicit (tx_identity_links)
- Feature store pentru agregări și preferințe
- Audience estimation endpoints
- Scanner event tracking

**Efort estimat**: Planul este realizabil, dar necesită 5-7 faze de dezvoltare. Infrastructura existentă reduce semnificativ timpul necesar.
