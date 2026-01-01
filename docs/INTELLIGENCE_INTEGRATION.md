# Intelligence System Integration Guide

This document describes how to integrate the Intelligence features when merging to the core-main branch.

## Overview

The Intelligence system provides:
- **Recommendations** - Personalized event/artist recommendations
- **Next Best Action** - AI-driven action prioritization
- **Win-Back Campaigns** - Automated re-engagement
- **Alerts** - Real-time triggers for high-value actions
- **Lookalike Audiences** - Audience expansion for marketing
- **Demand Forecasting** - Event sellout prediction
- **Customer Journey** - Lifecycle stage tracking

---

## Files Created

### Backend Services (`app/Services/Tracking/`)

| File | Purpose |
|------|---------|
| `RecommendationService.php` | Hybrid content-based + collaborative recommendations |
| `NextBestActionService.php` | AI-driven action scoring and prioritization |
| `WinBackCampaignService.php` | Win-back candidate identification and offers |
| `AlertTriggerService.php` | Real-time event-based alert triggers |
| `LookalikeAudienceService.php` | Similar customer finding, ad platform export |
| `DemandForecastingService.php` | Event demand prediction and pricing recommendations |
| `CustomerJourneyService.php` | Lifecycle stage tracking and analytics |
| `PersonProfileService.php` | Consolidated profile data service |

### Jobs (`app/Jobs/Tracking/`)

| File | Purpose |
|------|---------|
| `CalculateEngagementMetricsJob.php` | Email fatigue & channel affinity calculation |
| `SendWinBackCampaignJob.php` | Automated win-back email sending |

### API Controllers (`app/Http/Controllers/Api/`)

| File | Purpose |
|------|---------|
| `TxIntelligenceController.php` | REST API for all intelligence services |
| `TxProfileController.php` | Person profile REST endpoints |

### Core Admin Panel (`app/Filament/Pages/`)

| File | Purpose |
|------|---------|
| `IntelligenceHub.php` | Main intelligence dashboard for platform admins |

### Core Admin Widgets (`app/Filament/Widgets/`)

| File | Purpose |
|------|---------|
| `IntelligenceOverview.php` | Stats overview widget for dashboard |

### Tenant Admin (Optional - Can Remove)

| File | Purpose | Note |
|------|---------|------|
| `app/Filament/Tenant/Pages/IntelligenceDashboard.php` | Tenant view | **REMOVE** - Only Core Admin |
| `app/Filament/Tenant/Pages/WinBackCampaigns.php` | Tenant campaigns | **REMOVE** - Only Core Admin |

### Frontend Components (`app/Livewire/Recommendations/`)

| File | Purpose |
|------|---------|
| `RecommendedEvents.php` | Livewire component for event recommendations |
| `CheckoutUpsell.php` | Livewire component for checkout cross-sell |

### Views

| Path | Purpose |
|------|---------|
| `resources/views/filament/pages/intelligence-hub.blade.php` | Core Admin hub view |
| `resources/views/livewire/recommendations/recommended-events.blade.php` | Recommendation widget view |
| `resources/views/livewire/recommendations/checkout-upsell.blade.php` | Upsell widget view |
| `resources/views/emails/winback.blade.php` | Win-back email template |

### Models (`app/Models/FeatureStore/`)

| File | Purpose |
|------|---------|
| `FsPersonChannelAffinity.php` | Channel performance tracking |
| `FsPersonAntiAffinityArtist.php` | Artist bounce signals |
| `FsPersonAntiAffinityGenre.php` | Genre bounce signals |
| `FsPersonPurchaseWindow.php` | Purchase timing preferences |
| `FsPersonActivityPattern.php` | Activity hour/day patterns |
| `FsPersonEmailMetrics.php` | Email fatigue scoring |

### Migrations

| File | Tables Created |
|------|----------------|
| `2025_12_31_110000_create_advanced_profiling_tables.php` | `fs_person_purchase_window`, `fs_person_activity_pattern`, `fs_person_email_metrics`, `fs_person_channel_affinity` |
| `2025_12_31_120000_create_anti_affinity_tables.php` | `fs_person_anti_affinity_artist`, `fs_person_anti_affinity_genre`, `fs_person_anti_affinity_event` |
| `2025_12_31_130000_create_intelligence_tables.php` | `tracking_alerts`, `customer_journey_transitions`, `winback_conversions`, `lookalike_audiences`, `lookalike_audience_members`, `demand_forecasts` + columns on `core_customers` |

---

## Integration Points

### 1. Register Alert Listener

**File:** `app/Providers/EventServiceProvider.php`

Add event listener registration:

```php
protected $listen = [
    // ... existing listeners

    // Add these for alert processing
    \App\Events\TrackingEventReceived::class => [
        \App\Listeners\ProcessTrackingAlerts::class,
    ],
    \App\Events\OrderCompleted::class => [
        \App\Listeners\ProcessTrackingAlerts::class,
    ],
];
```

### 2. Fire Alert Events from TxTrackingController

**File:** `app/Http/Controllers/Api/TxTrackingController.php`

After line 106 (where event is created), add:

```php
// After: TxEvent::createFromEnvelope($eventData);

// Process alerts in background
if (!empty($eventData['person_id'])) {
    dispatch(function () use ($eventData) {
        \App\Services\Tracking\AlertTriggerService::forTenant($eventData['tenant_id'])
            ->processEvent([
                'type' => $eventData['event_name'],
                'person_id' => $eventData['person_id'],
                'data' => $eventData['payload'] ?? [],
            ]);
    })->onQueue('tracking-low');
}
```

### 3. Add Widget to Core Dashboard

**File:** `app/Filament/Pages/CustomDashboard.php` (or your dashboard)

Add widget to `getWidgets()`:

```php
protected function getWidgets(): array
{
    return [
        // ... existing widgets
        \App\Filament\Widgets\IntelligenceOverview::class,
    ];
}
```

### 4. Register Livewire Components

**File:** `app/Providers/AppServiceProvider.php`

In `boot()` method:

```php
// Register Livewire components
\Livewire\Livewire::component('recommendations.recommended-events', \App\Livewire\Recommendations\RecommendedEvents::class);
\Livewire\Livewire::component('recommendations.checkout-upsell', \App\Livewire\Recommendations\CheckoutUpsell::class);
```

### 5. Add API Routes

Routes are already added in `routes/api.php` under the `/tx/intelligence` prefix. Ensure they're included:

```php
// In routes/api.php (already added)
Route::prefix('tx')->group(function () {
    // ... existing routes

    Route::prefix('intelligence')->middleware(['throttle:api'])->group(function () {
        // All intelligence endpoints
    });
});
```

### 6. Scheduler Entries

**File:** `routes/console.php`

Already added:
- `CalculateEngagementMetricsJob` - Weekly Sunday 04:30
- Win-back campaign dispatch - Weekly Tuesday 10:00

---

## Frontend Integration Code

### Show Recommendations on Event Detail Page

```blade
{{-- In your event detail view --}}
@auth
    <div class="mt-12">
        <livewire:recommendations.recommended-events
            :tenant-id="$event->tenant_id"
            :person-id="auth()->user()->customer?->id"
            :limit="4"
            title="You Might Also Like" />
    </div>
@endauth
```

### Add Upsells to Checkout Page

```blade
{{-- In your checkout view --}}
<div class="my-6">
    <livewire:recommendations.checkout-upsell
        :tenant-id="$order->tenant_id"
        :event-id="$order->event_id"
        :person-id="auth()->user()->customer?->id" />
</div>
```

### Fetch Recommendations via JavaScript (API)

```javascript
// For SPAs or AJAX-based frontends
async function getRecommendations(tenantId, personId) {
    const response = await fetch(`/api/tx/intelligence/${tenantId}/recommendations/${personId}?limit=6`);
    return await response.json();
}

// Get next best action
async function getNextBestAction(tenantId, personId) {
    const response = await fetch(`/api/tx/intelligence/${tenantId}/nba/${personId}`);
    return await response.json();
}
```

### Display Recommendations in Email Templates

```blade
{{-- In your email templates --}}
@if(!empty($recommendations))
    <h3>Events You Might Like</h3>
    @foreach($recommendations as $rec)
        @php $event = $rec['event']; @endphp
        <div class="event-card">
            <img src="{{ $event->poster_url }}" alt="{{ $event->name }}">
            <h4>{{ $event->name }}</h4>
            <p>{{ $rec['reasons'][0] ?? 'Recommended for you' }}</p>
            <a href="{{ $event->url }}">View Event</a>
        </div>
    @endforeach
@endif
```

---

## API Endpoints Reference

### Recommendations

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tx/intelligence/{tenant}/recommendations/{person}` | Get event recommendations |
| GET | `/api/tx/intelligence/{tenant}/recommendations/{person}/artists` | Get artist recommendations |
| GET | `/api/tx/intelligence/{tenant}/recommendations/{person}/cross-sell/{event}` | Get cross-sell suggestions |

### Next Best Action

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tx/intelligence/{tenant}/nba/{person}` | Get next best action |
| GET | `/api/tx/intelligence/{tenant}/nba/{person}/queue` | Get action queue |

### Win-Back

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tx/intelligence/{tenant}/winback/candidates` | Get win-back candidates |
| GET | `/api/tx/intelligence/{tenant}/winback/stats` | Get win-back statistics |
| POST | `/api/tx/intelligence/{tenant}/winback/contacted` | Mark as contacted |

### Lookalike Audiences

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/tx/intelligence/{tenant}/lookalikes` | Find lookalike audience |
| GET | `/api/tx/intelligence/{tenant}/lookalikes/high-value` | Lookalikes from high-value |
| GET | `/api/tx/intelligence/{tenant}/lookalikes/event/{event}` | Lookalikes from event |
| POST | `/api/tx/intelligence/{tenant}/lookalikes/export` | Export for ad platforms |

### Demand Forecasting

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tx/intelligence/{tenant}/forecast/event/{event}` | Forecast single event |
| GET | `/api/tx/intelligence/{tenant}/forecast/upcoming` | Forecast all upcoming |
| GET | `/api/tx/intelligence/{tenant}/forecast/pricing/{event}` | Pricing recommendations |

### Customer Journey

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tx/intelligence/{tenant}/journey/{person}` | Get journey stage |
| GET | `/api/tx/intelligence/{tenant}/journey/{person}/full` | Get full journey |
| GET | `/api/tx/intelligence/{tenant}/journey/analytics` | Get journey analytics |

---

## Tenant Panel Access (REMOVED)

The Intelligence features are **Core Admin only**. The following Tenant panel files have been removed to restrict access:

- ~~`app/Filament/Tenant/Pages/IntelligenceDashboard.php`~~ (deleted)
- ~~`app/Filament/Tenant/Pages/WinBackCampaigns.php`~~ (deleted)
- ~~`resources/views/filament/tenant/pages/intelligence-dashboard.blade.php`~~ (deleted)
- ~~`resources/views/filament/tenant/pages/winback-campaigns.blade.php`~~ (deleted)

> **Note:** Only platform administrators can access Intelligence features via the Core Admin panel.

---

## Database Tables Created

After running migrations:

```sql
-- Feature Store tables
CREATE TABLE fs_person_purchase_window ...
CREATE TABLE fs_person_activity_pattern ...
CREATE TABLE fs_person_email_metrics ...
CREATE TABLE fs_person_channel_affinity ...
CREATE TABLE fs_person_anti_affinity_artist ...
CREATE TABLE fs_person_anti_affinity_genre ...
CREATE TABLE fs_person_anti_affinity_event ...

-- Intelligence tables
CREATE TABLE tracking_alerts ...
CREATE TABLE customer_journey_transitions ...
CREATE TABLE winback_conversions ...
CREATE TABLE lookalike_audiences ...
CREATE TABLE lookalike_audience_members ...
CREATE TABLE demand_forecasts ...

-- Columns added to core_customers
ALTER TABLE core_customers ADD journey_stage VARCHAR(30) ...
ALTER TABLE core_customers ADD journey_stage_updated_at TIMESTAMP ...
ALTER TABLE core_customers ADD last_winback_at TIMESTAMP ...
ALTER TABLE core_customers ADD last_winback_tier VARCHAR(30) ...
ALTER TABLE core_customers ADD last_winback_campaign_id VARCHAR(50) ...
ALTER TABLE core_customers ADD last_winback_converted_at TIMESTAMP ...
```

---

## Testing the Integration

### 1. Test Recommendations API

```bash
curl -X GET "http://localhost/api/tx/intelligence/1/recommendations/100?limit=5"
```

### 2. Test Win-Back Identification

```bash
curl -X GET "http://localhost/api/tx/intelligence/1/winback/candidates"
```

### 3. Test Demand Forecast

```bash
curl -X GET "http://localhost/api/tx/intelligence/1/forecast/event/42"
```

### 4. Test Next Best Action

```bash
curl -X GET "http://localhost/api/tx/intelligence/1/nba/100"
```

---

## Queue Configuration

Ensure these queues are running:

```bash
php artisan queue:work --queue=tracking,tracking-low,emails,webhooks
```

Or in supervisor:

```ini
[program:laravel-tracking]
command=php /path/to/artisan queue:work --queue=tracking,tracking-low --sleep=3 --tries=3

[program:laravel-emails]
command=php /path/to/artisan queue:work --queue=emails --sleep=3 --tries=3
```

---

## Checklist for Merge

- [ ] Run migrations: `php artisan migrate`
- [ ] Register Livewire components in AppServiceProvider
- [ ] Add IntelligenceOverview widget to Core dashboard
- [ ] Add alert listener to EventServiceProvider
- [ ] Verify scheduler entries in routes/console.php
- [ ] Start queue workers for new queues
- [x] Remove Tenant panel pages (Core-only access) ✓
- [ ] Test API endpoints
- [ ] Test Core Admin Intelligence Hub page
