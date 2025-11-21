# Analytics Dashboard Microservice

Real-time analytics, custom dashboards, and report generation for event ticketing.

## Features
- Custom dashboard builder with draggable widgets
- Real-time metrics (active visitors, live sales)
- Multiple widget types (charts, metrics, tables, maps)
- Scheduled report generation (PDF, Excel, CSV)
- Event tracking for user behavior
- Aggregated metrics caching

## Database Tables
- `analytics_dashboards` - Custom dashboard configurations
- `analytics_widgets` - Dashboard widget definitions
- `analytics_reports` - Saved report templates
- `analytics_metrics` - Cached aggregated data
- `analytics_events` - Raw event tracking

## API Endpoints
- `GET /api/analytics/summary` - Dashboard summary
- `GET /api/analytics/realtime` - Real-time metrics
- `POST /api/analytics/dashboards` - Create dashboard
- `GET /api/analytics/dashboards` - List dashboards
- `POST /api/analytics/dashboards/{id}/widgets` - Add widget
- `GET /api/analytics/widgets/{id}/data` - Get widget data
- `POST /api/analytics/track` - Track user event
- `POST /api/analytics/reports` - Create report
- `POST /api/analytics/reports/{id}/generate` - Generate report
- `GET /api/analytics/sales` - Sales data

## Widget Types
- **chart** - Line, bar, pie charts
- **metric** - Single value with change indicator
- **table** - Tabular data with sorting
- **map** - Geographic visualization

## Data Sources
- `sales` - Order and revenue data
- `attendance` - Ticket and check-in data
- `revenue` - Financial metrics
- `tickets` - Ticket type distribution

## Usage
```php
$service = app(AnalyticsService::class);

// Get real-time metrics
$metrics = $service->getRealTimeMetrics($tenantId);

// Track user event
$service->trackEvent($tenantId, 'purchase', ['order_id' => 123]);

// Create dashboard
$dashboard = $service->createDashboard([
    'tenant_id' => $tenantId,
    'user_id' => $userId,
    'name' => 'Sales Overview',
]);
```
