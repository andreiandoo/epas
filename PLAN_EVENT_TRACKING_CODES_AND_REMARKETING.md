# Plan: Event Tracking Codes per Organizator + Remarketing Automatizat

## Cuprins
1. [Analiza Stare Curenta](#analiza-stare-curenta)
2. [Partea 1: Tracking Codes per Organizator/Eveniment](#partea-1-tracking-codes-per-organizatoreveniment)
3. [Partea 2: Automatizare Conversii si Remarketing](#partea-2-automatizare-conversii-si-remarketing)
4. [Flowul End-to-End](#flowul-end-to-end)
5. [Fisiere de Creat/Modificat](#fisiere-de-creatmodificat)
6. [Prioritate de Implementare](#prioritate-de-implementare)

---

## Analiza Stare Curenta

### Ce exista deja in core-main:
- `tracking_integrations` - legat doar de `tenant_id` si `marketplace_client_id`, NU la nivel de organizator/eveniment
- `PlatformTrackingService` cu dual tracking (tenant + platform)
- Servicii integrate: `FacebookCapiService`, `GoogleAdsService`, `TikTokAdsService`, `LinkedInAdsService`
- `platform_ad_accounts` - conturile publicitare ale platformei (Tixello)
- `platform_conversions` - conversii trimise la conturile platformei
- `platform_audiences` + `platform_audience_members` - infrastructura de audience sync
- Event tracking bus: `pageview`, `view_item`, `add_to_cart`, `begin_checkout`, `purchase`
- GDPR consent management complet (opt-in by default)
- Provideri: GA4, GTM, Meta Pixel, TikTok Pixel cu script injection si CSP nonce

### Ce lipseste:
- Tracking codes la nivel de organizator si eveniment individual
- Sistem de fallback (event-specific -> organizer default -> marketplace)
- Automated campaign creation si management
- Budget allocation si optimization engine
- Audience building automat (retargeting, lookalike, cart abandoners)
- Server-side conversion tracking per organizator (CAPI)
- Dashboard organizator pentru campanii si performanta

---

## Partea 1: Tracking Codes per Organizator/Eveniment

### 1.1 Migration: `event_tracking_codes`

```sql
event_tracking_codes
  id                          BIGINT PRIMARY KEY
  marketplace_client_id       BIGINT FK -> marketplace_clients
  marketplace_organizer_id    BIGINT FK -> marketplace_organizers
  marketplace_event_id        BIGINT FK -> marketplace_events (NULLABLE)
                              -- null = cod default al organizatorului pt toate evenimentele
  provider                    ENUM('meta', 'google_ads', 'ga4', 'gtm', 'tiktok')
  enabled                     BOOLEAN DEFAULT true
  settings                    JSON
    -- pixel_id / measurement_id / container_id
    -- conversion_events: array (ce evenimente sa trackuiasca)
    -- advanced_matching: boolean (Enhanced Matching pt FB)
    -- access_token: encrypted (pt CAPI Facebook, Google Ads offline)
    -- custom_params: object (parametri custom per provider)
  created_at, updated_at

  UNIQUE(marketplace_organizer_id, marketplace_event_id, provider)
  INDEX(marketplace_event_id, provider, enabled)
  INDEX(marketplace_organizer_id, enabled)
```

### 1.2 Logica de Fallback (Resolving)

Cand se incarca pagina unui eveniment:
1. Cauta coduri specifice evenimentului (`marketplace_event_id = $eventId`) -> le injecteaza
2. Daca nu gaseste pt un provider, cauta coduri default ale organizatorului (`marketplace_event_id = NULL`) -> le injecteaza
3. Codurile marketplace client-ului (`tracking_integrations`) se injecteaza mereu (Tixello marketplace tracking)

Rezultat: pe pagina evenimentului pot exista simultan:
- Pixelul FB al organizatorului (specific evenimentului SAU default)
- Pixelul GA4 al organizatorului
- Pixelul TikTok al organizatorului
- Pixelul FB al marketplace-ului (Tixello)
- Pixelul GA4 al marketplace-ului (Tixello)

### 1.3 Model: `EventTrackingCode`

```php
class EventTrackingCode extends Model
{
    protected $fillable = [
        'marketplace_client_id', 'marketplace_organizer_id',
        'marketplace_event_id', 'provider', 'enabled', 'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    // Relationships
    public function organizer(): BelongsTo;
    public function event(): BelongsTo;
    public function marketplaceClient(): BelongsTo;

    // Scopes
    public function scopeForEvent($query, $eventId);
    public function scopeForOrganizer($query, $organizerId);
    public function scopeDefaultForOrganizer($query, $organizerId); // event_id IS NULL
    public function scopeEnabled($query);
    public function scopeProvider($query, string $provider);

    // Methods
    public function getProviderId(): ?string;
    public function getConversionEvents(): array;
    public function isEventSpecific(): bool;
    public function hasAccessToken(): bool;
    public function getAccessToken(): ?string;
}
```

### 1.4 Actualizare Modele Existente

**MarketplaceOrganizer:**
```php
public function trackingCodes(): HasMany
{
    return $this->hasMany(EventTrackingCode::class, 'marketplace_organizer_id');
}

public function defaultTrackingCodes(): HasMany
{
    return $this->hasMany(EventTrackingCode::class, 'marketplace_organizer_id')
        ->whereNull('marketplace_event_id');
}
```

**MarketplaceEvent:**
```php
public function trackingCodes(): HasMany
{
    return $this->hasMany(EventTrackingCode::class, 'marketplace_event_id');
}
```

### 1.5 Service: `EventTrackingCodeResolver`

```php
class EventTrackingCodeResolver
{
    /**
     * Resolve tracking codes for an event page.
     * Priority: event-specific > organizer-default > marketplace-client
     * Returns one code per provider (highest priority wins)
     */
    public function resolveForEvent(MarketplaceEvent $event): Collection;

    /**
     * Resolve tracking codes for organizer profile page.
     */
    public function resolveForOrganizer(MarketplaceOrganizer $organizer): Collection;

    /**
     * Generate HTML script injection for resolved codes.
     */
    public function getInjectionScripts(Collection $codes, ?string $nonce = null): array;
    // Returns ['head' => '...scripts...', 'body' => '...scripts...']

    /**
     * Get all active codes for server-side conversion tracking.
     * Used by ConversionTrackingService to send CAPI events.
     */
    public function getServerSideCodes(MarketplaceEvent $event): Collection;
}
```

### 1.6 Actualizare `TrackingScriptInjector`

Modificari necesare:
- Suport multi-pixel per pagina (mai multi pixeli FB, GA4, etc. simultan)
- Detectie tip pagina (event page, checkout, thank-you) pentru a injecta codurile corecte
- Parametru `marketplace_event_id` optional pentru a rezolva codurile organizatorului

### 1.7 API Endpoints

```
POST   /api/marketplace-client/organizer/tracking-codes          -> create
GET    /api/marketplace-client/organizer/tracking-codes          -> list all
GET    /api/marketplace-client/organizer/tracking-codes/{id}     -> get one
PUT    /api/marketplace-client/organizer/tracking-codes/{id}     -> update
DELETE /api/marketplace-client/organizer/tracking-codes/{id}     -> delete
GET    /api/marketplace-client/events/{slug}/tracking-config     -> resolve codes for event page
```

### 1.8 Filament UI: `OrganizerTrackingCodeResource`

In panelul Marketplace (organizer dashboard):
- Lista tracking codes cu filtre per provider si event
- Form:
  - Select provider: Meta Pixel, GA4, GTM, Google Ads, TikTok
  - Input pixel/measurement ID
  - Select event (optional - dropdown cu evenimentele organizatorului)
  - Toggle enabled
  - Configurari avansate: conversion events, enhanced matching, access token
- Info box cu instructiuni per provider (unde gasesti Pixel ID, etc.)

### 1.9 Frontend: Multi-Pixel Injection

Pe pagina de eveniment din marketplace:
1. API call la `/events/{slug}/tracking-config` -> primeste codurile resolved
2. Injectare scripturi pt fiecare provider (cu consent check GDPR)
3. Fire tracking events la toti pixelii:
   - `PageView` (la load)
   - `ViewContent` / `view_item` (pe pagina evenimentului)
   - `AddToCart` / `add_to_cart` (la adaugare in cos)
   - `InitiateCheckout` / `begin_checkout` (la checkout)
   - `Purchase` / `purchase` (la confirmare comanda)

---

## Partea 2: Automatizare Conversii si Remarketing

### 2.1 Migration: `ad_campaign_budgets`

```sql
ad_campaign_budgets
  id                          BIGINT PRIMARY KEY
  marketplace_client_id       BIGINT FK
  marketplace_organizer_id    BIGINT FK
  marketplace_event_id        BIGINT FK (NULLABLE) -- buget per eveniment sau general
  total_budget                DECIMAL(12,2)
  spent_budget                DECIMAL(12,2) DEFAULT 0
  currency                    VARCHAR(3) DEFAULT 'EUR'
  daily_budget_limit          DECIMAL(10,2) NULLABLE
  status                      ENUM('active','paused','exhausted','cancelled')
  allocation_strategy         ENUM('equal_split','performance_based','manual')
  platform_allocations        JSON
    -- meta: {percentage: 40, daily_limit: 50}
    -- google: {percentage: 40, daily_limit: 50}
    -- tiktok: {percentage: 20, daily_limit: 25}
  payment_reference           VARCHAR(255) NULLABLE -- referinta transfer bancar
  paid_at                     TIMESTAMP NULLABLE
  started_at                  TIMESTAMP NULLABLE
  ends_at                     TIMESTAMP NULLABLE
  notes                       TEXT NULLABLE
  created_at, updated_at

  INDEX(marketplace_organizer_id, status)
  INDEX(marketplace_event_id, status)
```

### 2.2 Migration: `automated_campaigns`

```sql
automated_campaigns
  id                          BIGINT PRIMARY KEY
  ad_campaign_budget_id       BIGINT FK
  marketplace_client_id       BIGINT FK
  marketplace_organizer_id    BIGINT FK
  marketplace_event_id        BIGINT FK
  platform                    ENUM('meta','google_ads','tiktok')
  campaign_type               ENUM('prospecting','retargeting','lookalike','dynamic_remarketing')
  external_campaign_id        VARCHAR(255) NULLABLE -- ID pe platforma publicitara
  external_adset_id           VARCHAR(255) NULLABLE
  external_ad_id              VARCHAR(255) NULLABLE
  name                        VARCHAR(255)
  status                      ENUM('draft','pending_review','active','paused','completed','failed')
  objective                   ENUM('conversions','traffic','awareness')
  targeting                   JSON
    -- age_min, age_max, genders
    -- locations: [{country, region, city, radius_km}]
    -- interests: [array]
    -- custom_audiences: [IDs]
    -- lookalike_audiences: [IDs]
    -- excluded_audiences: [IDs]
  creative                    JSON
    -- headline, description, cta_type
    -- image_url, video_url
    -- landing_page_url
    -- variants: [{headline, image_url, ...}] -- pt A/B testing
  budget_allocated            DECIMAL(10,2)
  budget_spent                DECIMAL(10,2) DEFAULT 0
  daily_budget                DECIMAL(10,2)
  bid_strategy                ENUM('lowest_cost','cost_cap','bid_cap')
  bid_amount                  DECIMAL(10,2) NULLABLE
  performance_metrics         JSON
    -- impressions, clicks, ctr
    -- conversions, conversion_rate, cpa
    -- spend, roas, revenue_generated
    -- reach, frequency
    -- last_updated_at
  optimization_log            JSON -- array de decizii automate
  started_at                  TIMESTAMP NULLABLE
  ends_at                     TIMESTAMP NULLABLE
  created_at, updated_at

  INDEX(ad_campaign_budget_id, status)
  INDEX(marketplace_event_id, platform, status)
  INDEX(status, platform)
```

### 2.3 Migration: `remarketing_audiences`

```sql
remarketing_audiences
  id                          BIGINT PRIMARY KEY
  marketplace_client_id       BIGINT FK
  marketplace_organizer_id    BIGINT FK
  marketplace_event_id        BIGINT FK (NULLABLE)
  platform                    ENUM('meta','google_ads','tiktok')
  audience_type               ENUM('website_visitors','ticket_buyers','cart_abandoners',
                                   'event_viewers','lookalike','custom_segment')
  external_audience_id        VARCHAR(255) NULLABLE -- ID pe platforma
  name                        VARCHAR(255)
  description                 TEXT NULLABLE
  criteria                    JSON
    -- source_events: ['view_item','add_to_cart','purchase']
    -- time_window_days: 30
    -- min_actions: 1
    -- exclude_purchasers: true/false
    -- lookalike_source_id: (audienta sursa pt lookalike)
    -- lookalike_percentage: 1-10
    -- custom_filters: {segment, rfm, engagement_score_min, etc.}
  member_count                INTEGER DEFAULT 0
  status                      ENUM('building','ready','syncing','active','expired')
  auto_refresh                BOOLEAN DEFAULT true
  refresh_frequency           ENUM('daily','weekly')
  last_synced_at              TIMESTAMP NULLABLE
  expires_at                  TIMESTAMP NULLABLE
  created_at, updated_at

  INDEX(marketplace_organizer_id, platform, status)
  INDEX(marketplace_event_id, audience_type)
  UNIQUE(platform, external_audience_id)
```

### 2.4 Service: `AutomatedCampaignService`

```php
class AutomatedCampaignService
{
    // === Campaign Lifecycle ===

    /**
     * Creeaza automat suita de campanii pt un eveniment:
     * 1. Prospecting (audienta rece, interese similare genului evenimentului)
     * 2. Retargeting (vizitatori pagina eveniment care n-au cumparat)
     * 3. Lookalike (bazata pe cumparatorii existenti ai organizatorului)
     * 4. Dynamic remarketing (cart abandoners cu produsul specific)
     */
    public function createCampaignSuite(
        MarketplaceEvent $event,
        AdCampaignBudget $budget
    ): Collection;

    /**
     * Publica campania pe platforma (FB Ads API, Google Ads API, TikTok Ads API)
     */
    public function publishCampaign(AutomatedCampaign $campaign): void;

    public function pauseCampaign(AutomatedCampaign $campaign): void;
    public function resumeCampaign(AutomatedCampaign $campaign): void;

    // === Budget Management ===

    /**
     * Distribuie bugetul intre platforme si campanii
     * Strategii: equal_split (egal), performance_based (ROAS), manual
     */
    public function allocateBudget(AdCampaignBudget $budget): array;

    /**
     * Realocare dinamica bazata pe performanta
     * Muta buget de la campanii cu CPA mare la cele cu ROAS bun
     */
    public function reallocateBudget(AdCampaignBudget $budget): void;

    // === Performance Optimization (cron hourly) ===

    /**
     * Ciclul de optimizare:
     * 1. Fetch performance metrics de la API-urile platformelor
     * 2. Calculeaza ROAS, CPA, CTR per campanie
     * 3. Pausa campanii cu CPA > 2x target_cpa
     * 4. Creste buget la campanii cu ROAS > 2x target
     * 5. A/B test creative-uri (rotatie dupa 48h, pastreaza winner)
     * 6. Log optimization decisions in optimization_log
     */
    public function optimizeCampaigns(): void;

    // === Audience Refresh (cron daily) ===

    /**
     * 1. Actualizeaza audientele de retargeting cu vizitatori noi
     * 2. Exclude cumparatorii din audienta de retargeting
     * 3. Actualizeaza lookalike cu cumparatori noi ca seed
     * 4. Sync cu platformele (FB Custom Audiences API, Google Customer Match)
     */
    public function refreshAudiences(): void;
}
```

### 2.5 Service: `ConversionTrackingService`

Bridge intre tracking existent si campaniile automate:

```php
class ConversionTrackingService
{
    /**
     * Trimite conversie server-side la platformele organizatorului:
     * - Facebook CAPI: event_name=Purchase, value, content_ids, user_data(hashed)
     * - Google Ads: Enhanced Conversions sau Offline Conversion Import
     * - TikTok Events API: CompletePayment event
     * + Trimite si la conturile Tixello (dual tracking existent)
     * + Actualizeaza metrics pe AutomatedCampaign daca conversia e atribuita
     */
    public function trackConversionForOrganizer(Order $order): void;

    /**
     * Server-side ViewContent la platformele organizatorului
     */
    public function trackEventView(MarketplaceEvent $event, array $visitorData): void;

    /**
     * Server-side AddToCart
     */
    public function trackAddToCart(MarketplaceEvent $event, array $cartData): void;

    /**
     * Atribuie conversia la campania corecta via click_id, UTM, session data
     */
    public function attributeConversion(Order $order): ?AutomatedCampaign;
}
```

### 2.6 Service: `AudienceBuilderService`

```php
class AudienceBuilderService
{
    /**
     * Audienta retargeting: vizitatori pagina eveniment minus cumparatori
     * Sync via hashed emails/phones la platforma
     */
    public function buildRetargetingAudience(
        MarketplaceEvent $event,
        string $platform
    ): RemarketingAudience;

    /**
     * Audienta cumparatori: baza pentru lookalike
     */
    public function buildPurchaserAudience(
        MarketplaceOrganizer $organizer,
        string $platform
    ): RemarketingAudience;

    /**
     * Creeaza lookalike pe platforma (FB 1-10%, Google Similar)
     */
    public function buildLookalikeAudience(
        RemarketingAudience $source,
        string $platform,
        int $percentage = 1
    ): RemarketingAudience;

    /**
     * Cart abandoners: adaugat in cos dar nu a cumparat
     */
    public function buildCartAbandonerAudience(
        MarketplaceEvent $event,
        string $platform
    ): RemarketingAudience;

    /**
     * Sync zilnic: adauga membri noi, sterge expirate
     */
    public function syncAllAudiences(): void;
}
```

### 2.7 Jobs (Queue / Cron)

```php
// La fiecare ora
OptimizeCampaignsJob
  -> Fetch metrics de la API-urile platformelor
  -> Realocare buget bazat pe performanta
  -> Pause/resume campanii

// La fiecare 30 minute
SyncCampaignMetricsJob
  -> Fetch impressions, clicks, spend, conversions
  -> Actualizeaza performance_metrics pe AutomatedCampaign
  -> Actualizeaza spent_budget pe AdCampaignBudget

// Zilnic la 03:00
RefreshAudiencesJob
  -> Sync audienta cu platformele
  -> Adauga vizitatori noi
  -> Exclude cumparatori din retargeting

// La fiecare 5 minute
ProcessServerSideConversionsJob
  -> Batch send conversions via CAPI
  -> Retry failed conversions
  -> Log API responses

// Zilnic la 06:00
RebalanceBudgetJob
  -> Verifica bugetele ramase
  -> Ajusteaza daily spend pt a nu depasi bugetul total
  -> Notifica organizatorii cand bugetul e < 20%
  -> Pausa campanii cand bugetul e epuizat
```

### 2.8 Filament Dashboard

**Marketplace Admin (Tixello):**
- Dashboard cu toate campaniile active across organizatori
- Metrici agregate: total spend, total conversions, avg ROAS, avg CPA
- Buget overview per organizator
- Manual override: pause, resume, ajustare buget
- Optimization logs viewer
- Campaign approval queue (review inainte de publicare)

**Organizator Dashboard:**
- Card rezumat: buget total / cheltuit / ramas
- Tabel campanii cu status si metrici (impressions, clicks, conversions, ROAS)
- Grafic performanta in timp
- Lista audienta construita (dimensiune, status sync)
- Raport simplu: "Ai investit X EUR, ai generat Y vanzari (Z bilete), ROAS = W"

---

## Flowul End-to-End

```
1. Organizatorul creeaza eveniment pe Tixello Marketplace
   |
2. Organizatorul adauga codurile de tracking (pixeli FB/Google/TikTok)
   - la nivel de eveniment SAU ca default pt toate evenimentele
   |
3. Organizatorul transfera buget publicitar catre Tixello
   -> Se creeaza AdCampaignBudget cu status=active
   |
4. Tixello creeaza automat suita de campanii:
   a) PROSPECTING: audienta rece bazata pe interese (muzica, concerte, etc.)
   b) RETARGETING: vizitatori pagina eveniment care n-au cumparat
   c) LOOKALIKE: 1-3% bazata pe cumparatorii existenti
   d) CART ABANDONERS: oameni care au adaugat in cos dar n-au finalizat
   |
5. Campaniile se activeaza pe FB/Google/TikTok
   -> Folosind contul Tixello (sau contul organizatorului daca are access token)
   -> Bugetul se distribuie dupa strategie (equal_split sau performance_based)
   |
6. Vizitatorii ajung pe pagina evenimentului:
   - Se injecteaza pixelii organizatorului + pixelul Tixello
   - Se trackuiesc: view, add_to_cart, checkout, purchase
   - Conversiile se trimit server-side via CAPI (nu depind de cookies/consent browser)
   |
7. La fiecare ora, sistemul optimizeaza automat:
   - Campanii cu CPA > 2x target -> se scade bugetul sau se opresc
   - Campanii cu ROAS bun -> se creste bugetul
   - Creative-uri slabe -> se inlocuiesc (A/B test automat)
   - Audientele se actualizeaza cu vizitatori noi
   |
8. Organizatorul vede in dashboard:
   - Buget cheltuit vs. ramas
   - Conversii atribuite campaniilor Tixello
   - ROAS (Return on Ad Spend)
   - Audienta construita
   - Recomandari: "Mareste bugetul cu 20% - ROAS-ul e de 4.2x"
```

---

## Fisiere de Creat/Modificat

### Migrari noi (3):
1. `database/migrations/2026_02_08_100000_create_event_tracking_codes_table.php`
2. `database/migrations/2026_02_08_100001_create_ad_campaign_budgets_table.php`
3. `database/migrations/2026_02_08_100002_create_automated_campaigns_and_audiences_tables.php`

### Modele noi (4):
1. `app/Models/EventTrackingCode.php`
2. `app/Models/AdCampaignBudget.php`
3. `app/Models/AutomatedCampaign.php`
4. `app/Models/RemarketingAudience.php`

### Services noi (4):
1. `app/Services/Tracking/EventTrackingCodeResolver.php`
2. `app/Services/Campaigns/AutomatedCampaignService.php`
3. `app/Services/Campaigns/ConversionTrackingService.php`
4. `app/Services/Campaigns/AudienceBuilderService.php`

### Modificari fisiere existente (5):
1. `app/Models/MarketplaceOrganizer.php` -> relatie trackingCodes, defaultTrackingCodes
2. `app/Models/MarketplaceEvent.php` -> relatie trackingCodes
3. `app/Services/Tracking/TrackingScriptInjector.php` -> suport multi-pixel per event
4. `app/Services/Platform/PlatformTrackingService.php` -> integrare cu organizer tracking
5. `routes/api.php` -> rute noi pt tracking codes si campaigns

### Jobs noi (5):
1. `app/Jobs/Campaigns/OptimizeCampaignsJob.php`
2. `app/Jobs/Campaigns/RefreshAudiencesJob.php`
3. `app/Jobs/Campaigns/SyncCampaignMetricsJob.php`
4. `app/Jobs/Campaigns/ProcessServerSideConversionsJob.php`
5. `app/Jobs/Campaigns/RebalanceBudgetJob.php`

### Filament Resources noi (3):
1. `app/Filament/Marketplace/Resources/EventTrackingCodeResource.php`
2. `app/Filament/Marketplace/Resources/AdCampaignBudgetResource.php`
3. `app/Filament/Marketplace/Resources/AutomatedCampaignResource.php`

### Controllers noi (2):
1. `app/Http/Controllers/Api/MarketplaceClient/Organizer/TrackingCodeController.php`
2. `app/Http/Controllers/Api/MarketplaceClient/Organizer/CampaignController.php`

**Total: ~30 fisiere (11 noi, 5 modificate, 5 jobs, 3 Filament, 2 controllere, 3 migrari)**

---

## Prioritate de Implementare

| Faza | Ce se implementeaza | Dependente |
|------|---------------------|------------|
| **Faza 1** | Migration + Model `EventTrackingCode` + relatii pe Organizer/Event | - |
| **Faza 2** | `EventTrackingCodeResolver` + actualizare `TrackingScriptInjector` | Faza 1 |
| **Faza 3** | API Endpoints + Filament Resource pt tracking codes | Faza 1 |
| **Faza 4** | `ConversionTrackingService` - server-side CAPI per organizator | Faza 2 |
| **Faza 5** | Migrations + Models pt budgets, campaigns, audiences | - |
| **Faza 6** | `AudienceBuilderService` | Faza 4, 5 |
| **Faza 7** | `AutomatedCampaignService` | Faza 5, 6 |
| **Faza 8** | Jobs (cron: optimization, metrics sync, audience refresh) | Faza 7 |
| **Faza 9** | Filament dashboards pt campanii (admin + organizator) | Faza 7 |
| **Faza 10** | Testing, edge cases, monitoring, alerting | Toate |

### MVP (Minimum Viable Product) = Fazele 1-4
Cu fazele 1-4 implementate, organizatorii pot:
- Adauga pixelii lor pe fiecare eveniment
- Conversiile se trimit automat server-side la platformele lor
- Tixello primeste si el conversiile (dual tracking)

### Full Product = Fazele 1-10
Cu toate fazele, Tixello devine o platforma de marketing automatizat completa.

---

## Consideratii Tehnice

### Securitate
- Access tokens criptate in baza de date (Laravel `encrypted` cast)
- Rate limiting pe API-urile platformelor (FB: 200 calls/hour, Google: 15k/day)
- Validare pixel IDs (format corect per provider)
- Separare stricta intre datele organizatorilor (tenant isolation)

### Performanta
- Server-side conversions in batch (queue, nu sincron)
- Cache tracking config per event (Redis, 5 min TTL)
- Audience sync in background jobs (nu blocheaza request-uri)
- Metrics fetch asincron (nu in request-ul organizatorului)

### GDPR
- Conversiile server-side respecta consimtamantul utilizatorului
- Hashing PII (email, phone) inainte de trimitere la platforme
- Organizatorul raspunde de consimtamant pe site-ul sau (pixeli client-side)
- Tixello raspunde de CAPI (server-side) - baza legala: interes legitim / contract

### Scalabilitate
- Jobs distribuite pe multiple workers
- Partitionare pe marketplace_client_id pt query-uri mari
- Limita de campanii active per organizator (previne abuse)
- Monitoring spend vs. buget cu alerting automat
