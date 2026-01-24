<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PublicApi\SeatingController;
use App\Http\Controllers\Api\PublicDataController;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\PlatformTrackingController;
use App\Http\Controllers\Api\MarketplaceTrackingController;
use App\Http\Controllers\Api\TicketTemplateController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\InsuranceController;
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\EFacturaController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\MicroservicesController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\FeatureFlagController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TenantClient\DomainVerificationController;
use App\Http\Controllers\Api\TenantClient\PackageController;
use App\Http\Controllers\Api\TenantClient\BootstrapController;
use App\Http\Controllers\Api\TenantClient\EventsController;
use App\Http\Controllers\Api\TenantClient\AuthController;
use App\Http\Controllers\Api\TenantClient\AccountController;
use App\Http\Controllers\Api\TenantClient\CartController;
use App\Http\Controllers\Api\TenantClient\CheckoutController;
use App\Http\Controllers\Api\TenantClient\AdminController;
use App\Http\Controllers\Api\TenantClient\ThemeController;
use App\Http\Controllers\Api\TenantClient\PagesController;
use App\Http\Controllers\Api\TenantClient\CookieConsentController;
use App\Http\Controllers\Api\TenantClient\BlogController;
use App\Http\Controllers\Api\TenantClient\ShopProductController;
use App\Http\Controllers\Api\TenantClient\ShopCartController;
use App\Http\Controllers\Api\TenantClient\ShopOrderController;
use App\Http\Controllers\Api\TenantClient\ShopReviewController;
use App\Http\Controllers\Api\TenantClient\ShopEventProductController;
use App\Http\Controllers\Api\TenantClient\ShopCheckoutController;
use App\Http\Controllers\Api\TenantClient\ShopWishlistController;
use App\Http\Controllers\Api\TenantClient\ShopStockAlertController;
use App\Http\Controllers\Api\TenantClient\GamificationController;
use App\Http\Controllers\Api\TenantClient\AffiliateController as TenantClientAffiliateController;
use App\Http\Controllers\Api\TenantClient\TaxController;
use App\Http\Controllers\Api\DocSearchController;
use App\Http\Controllers\Api\TenantClientController;

/*
|--------------------------------------------------------------------------
| Tenant Client Public API (for tenant domains)
|--------------------------------------------------------------------------
|
| Public endpoints called by tenant SPA - no auth required
| CORS enabled for tenant domains
|
*/

Route::prefix('tenant-client')->middleware(['throttle:120,1', 'tenant.client.cors'])->group(function () {
    // Handle OPTIONS preflight requests
    Route::options('/{any}', fn () => response('', 200))
        ->where('any', '.*')
        ->name('api.tenant-client-public.options');

    Route::get('/config', [TenantClientController::class, 'config'])
        ->name('api.tenant-client-public.config');

    Route::get('/events', [TenantClientController::class, 'events'])
        ->name('api.tenant-client-public.events');

    Route::get('/events/featured', [TenantClientController::class, 'featuredEvents'])
        ->name('api.tenant-client-public.events.featured');

    Route::get('/events/past', [EventsController::class, 'pastEvents'])
        ->name('api.tenant-client-public.events.past');

    Route::get('/events/{slug}', [TenantClientController::class, 'event'])
        ->name('api.tenant-client-public.event');

    Route::get('/categories', [TenantClientController::class, 'categories'])
        ->name('api.tenant-client-public.categories');

    Route::get('/pages/terms', [TenantClientController::class, 'terms'])
        ->name('api.tenant-client-public.pages.terms');

    Route::get('/pages/privacy', [TenantClientController::class, 'privacy'])
        ->name('api.tenant-client-public.pages.privacy');

    Route::get('/pages/{slug}', [TenantClientController::class, 'page'])
        ->name('api.tenant-client-public.pages.show');

    // Theme API
    Route::get('/theme', [ThemeController::class, 'show'])
        ->name('api.tenant-client-public.theme');
    Route::get('/theme/fonts', [ThemeController::class, 'fonts'])
        ->name('api.tenant-client-public.theme.fonts');

    // Pages API (page builder)
    Route::get('/builder/pages', [PagesController::class, 'index'])
        ->name('api.tenant-client-public.builder.pages');
    Route::get('/builder/pages/{slug}', [PagesController::class, 'show'])
        ->name('api.tenant-client-public.builder.pages.show');
    Route::get('/builder/blocks', [PagesController::class, 'blocks'])
        ->name('api.tenant-client-public.builder.blocks');
});

/*
|--------------------------------------------------------------------------
| Documentation Search API (Public - no auth required)
|--------------------------------------------------------------------------
*/

Route::prefix('docs')->group(function () {
    Route::get('/autocomplete', [DocSearchController::class, 'autocomplete'])->name('api.docs.autocomplete');
    Route::get('/search', [DocSearchController::class, 'search'])->name('api.docs.search');
    Route::get('/suggestions', [DocSearchController::class, 'suggestions'])->name('api.docs.suggestions');
});

/*
|--------------------------------------------------------------------------
| Public Data API Routes (for WordPress/external integrations)
|--------------------------------------------------------------------------
|
| Secured with API key authentication
|
*/

Route::prefix('v1/public')->middleware(['api.key'])->group(function () {
    Route::get('/data', [PublicDataController::class, 'data'])->name('api.public.data');
    Route::get('/stats', [PublicDataController::class, 'stats'])->name('api.public.stats');
    Route::get('/venues', [PublicDataController::class, 'venues'])->name('api.public.venues');
    Route::get('/venues/{slug}', [PublicDataController::class, 'venue'])->name('api.public.venue');
    Route::get('/venue-types', [PublicDataController::class, 'venueTypes'])->name('api.public.venue-types');
    Route::get('/artists', [PublicDataController::class, 'artists'])->name('api.public.artists');
    Route::get('/artists/{slug}', [PublicDataController::class, 'artist'])->name('api.public.artist');
    Route::get('/artists/{slug}/stats', [PublicDataController::class, 'artistStats'])->name('api.public.artist.stats');
    Route::get('/artists/{slug}/youtube', [PublicDataController::class, 'artistYoutubeStats'])->name('api.public.artist.youtube');
    Route::get('/artists/{slug}/spotify', [PublicDataController::class, 'artistSpotifyStats'])->name('api.public.artist.spotify');
    Route::get('/tenants', [PublicDataController::class, 'tenants'])->name('api.public.tenants');
    Route::get('/tenants/{slug}', [PublicDataController::class, 'tenant'])->name('api.public.tenant');
    Route::get('/events', [PublicDataController::class, 'events'])->name('api.public.events');
    Route::get('/events/{slug}', [PublicDataController::class, 'event'])->name('api.public.event');
});

/*
|--------------------------------------------------------------------------
| Seating API Routes - Public
|--------------------------------------------------------------------------
|
| Public-facing seating APIs with rate limiting and session management
|
*/

Route::prefix('public')->middleware(['seating.session'])->group(function () {

    // Get seating layout and metadata for an event
    Route::get('/events/{eventId}/seating', [SeatingController::class, 'getSeating'])
        ->name('api.seating.get-layout')
        ->middleware('throttle:seating_query');

    // Get seat availability and pricing
    Route::get('/events/{eventId}/seats', [SeatingController::class, 'getSeats'])
        ->name('api.seating.get-seats')
        ->middleware('throttle:seating_query');

    // Hold seats for session (10 minute TTL)
    Route::post('/seats/hold', [SeatingController::class, 'holdSeats'])
        ->name('api.seating.hold')
        ->middleware('throttle:seating_hold');

    // Release held seats
    Route::delete('/seats/hold', [SeatingController::class, 'releaseSeats'])
        ->name('api.seating.release')
        ->middleware('throttle:seating_release');

    // Confirm purchase (mark as sold)
    Route::post('/seats/confirm', [SeatingController::class, 'confirmPurchase'])
        ->name('api.seating.confirm')
        ->middleware('throttle:seating_confirm');

    // Get session's current holds
    Route::get('/seats/holds', [SeatingController::class, 'getSessionHolds'])
        ->name('api.seating.get-holds')
        ->middleware('throttle:seating_query');
});

/*
|--------------------------------------------------------------------------
| Affiliate Tracking API Routes
|--------------------------------------------------------------------------
|
| API endpoints for affiliate tracking and management
|
*/

Route::prefix('affiliates')->middleware(['throttle:api'])->group(function () {
    // Tenant admin endpoints
    Route::post('/', [AffiliateController::class, 'store'])
        ->name('api.affiliates.create');

    Route::get('/{id}/dashboard', [AffiliateController::class, 'dashboard'])
        ->name('api.affiliates.dashboard');

    Route::get('/{id}/export', [AffiliateController::class, 'export'])
        ->name('api.affiliates.export');

    // Affiliate self-service endpoint
    Route::get('/me', [AffiliateController::class, 'me'])
        ->name('api.affiliates.me');

    // Tracking endpoints
    Route::post('/track-click', [AffiliateController::class, 'trackClick'])
        ->name('api.affiliates.track-click');

    Route::post('/attribute-order', [AffiliateController::class, 'attributeOrder'])
        ->name('api.affiliates.attribute-order');

    Route::post('/confirm-order', [AffiliateController::class, 'confirmOrder'])
        ->name('api.affiliates.confirm-order');

    Route::post('/approve-order', [AffiliateController::class, 'approveOrder'])
        ->name('api.affiliates.approve-order');

    Route::post('/reverse-order', [AffiliateController::class, 'reverseOrder'])
        ->name('api.affiliates.reverse-order');
});

/*
|--------------------------------------------------------------------------
| Tracking & Pixels API Routes
|--------------------------------------------------------------------------
|
| API endpoints for tracking configuration and consent management
|
*/

Route::prefix('tracking')->middleware(['throttle:api', 'tenant.client.cors'])->group(function () {
    // Get tracking configuration
    Route::get('/config', [TrackingController::class, 'getConfig'])
        ->name('api.tracking.config');

    // Consent management
    Route::get('/consent', [TrackingController::class, 'getConsent'])
        ->name('api.tracking.consent.get');

    Route::post('/consent', [TrackingController::class, 'updateConsent'])
        ->name('api.tracking.consent.update');

    Route::post('/consent/revoke', [TrackingController::class, 'revokeConsent'])
        ->name('api.tracking.consent.revoke');

    // Admin: Manage integrations
    Route::post('/integrations', [TrackingController::class, 'storeIntegration'])
        ->name('api.tracking.integrations.store');

    // Debug: Preview script injection
    Route::get('/debug/preview', [TrackingController::class, 'debugPreview'])
        ->name('api.tracking.debug.preview');

    // Platform real-time tracking events (from tenant websites)
    Route::post('/events', [PlatformTrackingController::class, 'trackEvents'])
        ->name('api.tracking.events')
        ->withoutMiddleware(['throttle:api'])
        ->middleware('throttle:300,1'); // Higher rate limit for tracking

    // Real-time analytics
    Route::get('/realtime', [PlatformTrackingController::class, 'getRealTimeStats'])
        ->name('api.tracking.realtime');

    Route::get('/active-visitors', [PlatformTrackingController::class, 'getActiveVisitors'])
        ->name('api.tracking.active-visitors');

    Route::get('/events/stream', [PlatformTrackingController::class, 'getEventStream'])
        ->name('api.tracking.events.stream');

    Route::get('/customers/insights', [PlatformTrackingController::class, 'getCustomerInsights'])
        ->name('api.tracking.customers.insights');
});

/*
|--------------------------------------------------------------------------
| Marketplace Tracking API Routes
|--------------------------------------------------------------------------
|
| Public API endpoints for marketplace event tracking.
| No authentication required - uses marketplace_client_id for identification.
| CORS enabled for marketplace client domains.
|
*/

Route::prefix('marketplace-tracking')->middleware(['throttle:300,1', 'marketplace.auth'])->group(function () {
    // Track single event
    Route::post('/track', [MarketplaceTrackingController::class, 'track'])
        ->name('api.marketplace-tracking.track');

    // Track multiple events in batch
    Route::post('/batch', [MarketplaceTrackingController::class, 'trackBatch'])
        ->name('api.marketplace-tracking.batch');

    // Tracking pixel (for email opens, etc.)
    Route::get('/pixel', [MarketplaceTrackingController::class, 'pixel'])
        ->name('api.marketplace-tracking.pixel');
});

/*
|--------------------------------------------------------------------------
| Ticket Template API Routes
|--------------------------------------------------------------------------
|
| API endpoints for ticket template customizer (WYSIWYG editor)
|
*/

Route::prefix('tickets/templates')->middleware(['throttle:api'])->group(function () {
    // Get available variables and sample data
    Route::get('/variables', [TicketTemplateController::class, 'getVariables'])
        ->name('api.tickets.templates.variables');

    // Validate template JSON
    Route::post('/validate', [TicketTemplateController::class, 'validate'])
        ->name('api.tickets.templates.validate');

    // Generate preview image
    Route::post('/preview', [TicketTemplateController::class, 'preview'])
        ->name('api.tickets.templates.preview');

    // Get preset dimensions
    Route::get('/presets', [TicketTemplateController::class, 'getPresets'])
        ->name('api.tickets.templates.presets');

    // List templates for a tenant
    Route::get('/', [TicketTemplateController::class, 'index'])
        ->name('api.tickets.templates.index');

    // Create a new template
    Route::post('/', [TicketTemplateController::class, 'store'])
        ->name('api.tickets.templates.store');

    // Get a specific template
    Route::get('/{id}', [TicketTemplateController::class, 'show'])
        ->name('api.tickets.templates.show');

    // Update a template
    Route::put('/{id}', [TicketTemplateController::class, 'update'])
        ->name('api.tickets.templates.update');

    // Delete a template
    Route::delete('/{id}', [TicketTemplateController::class, 'destroy'])
        ->name('api.tickets.templates.destroy');

    // Set template as default
    Route::post('/{id}/set-default', [TicketTemplateController::class, 'setDefault'])
        ->name('api.tickets.templates.set-default');

    // Create a new version of a template
    Route::post('/{id}/create-version', [TicketTemplateController::class, 'createVersion'])
        ->name('api.tickets.templates.create-version');
});

/*
|--------------------------------------------------------------------------
| Invitations API Routes
|--------------------------------------------------------------------------
|
| API endpoints for invitation management (zero-value tickets)
|
*/

Route::prefix('inv')->middleware(['throttle:api'])->group(function () {
    // Batch management
    Route::post('/batch', [InviteController::class, 'createBatch'])
        ->name('api.inv.batch.create');

    Route::post('/batch/import', [InviteController::class, 'importRecipients'])
        ->name('api.inv.batch.import');

    Route::post('/batch/render', [InviteController::class, 'renderBatch'])
        ->name('api.inv.batch.render');

    Route::get('/batch/{id}/export', [InviteController::class, 'exportBatch'])
        ->name('api.inv.batch.export');

    Route::get('/batch/{id}/download-zip', [InviteController::class, 'downloadBatchZip'])
        ->name('api.inv.batch.download-zip');

    // Email sending
    Route::post('/send', [InviteController::class, 'sendEmails'])
        ->name('api.inv.send');

    // Individual invite management
    Route::get('/{id}', [InviteController::class, 'getInvite'])
        ->name('api.inv.get');

    Route::post('/{id}/void', [InviteController::class, 'voidInvite'])
        ->name('api.inv.void');

    Route::post('/{id}/resend', [InviteController::class, 'resend'])
        ->name('api.inv.resend');

    // Download (signed URL - public access)
    Route::get('/{id}/download', [InviteController::class, 'download'])
        ->name('api.inv.download')
        ->middleware(['signed']);

    // Tracking webhooks
    Route::post('/webhook/open', [InviteController::class, 'trackOpen'])
        ->name('api.inv.webhook.open');
});

/*
|--------------------------------------------------------------------------
| Ticket Insurance API Routes
|--------------------------------------------------------------------------
|
| API endpoints for ticket insurance (optional checkout add-on)
|
*/

Route::prefix('ti')->middleware(['throttle:api'])->group(function () {
    // Quote endpoint
    Route::get('/quote', [InsuranceController::class, 'quote'])
        ->name('api.ti.quote');

    // Policy issuance
    Route::post('/issue', [InsuranceController::class, 'issue'])
        ->name('api.ti.issue');

    // Policy sync with provider
    Route::post('/sync', [InsuranceController::class, 'sync'])
        ->name('api.ti.sync');

    // List policies
    Route::get('/policies', [InsuranceController::class, 'listPolicies'])
        ->name('api.ti.policies');

    // Statistics
    Route::get('/stats', [InsuranceController::class, 'stats'])
        ->name('api.ti.stats');

    // Void policy
    Route::post('/{id}/void', [InsuranceController::class, 'void'])
        ->name('api.ti.void');

    // Refund policy
    Route::post('/{id}/refund', [InsuranceController::class, 'refund'])
        ->name('api.ti.refund');
});

/*
|--------------------------------------------------------------------------
| Accounting Connectors API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('acc')->middleware(['throttle:api'])->group(function () {
    Route::post('/connect', [AccountingController::class, 'connect'])
        ->name('api.acc.connect');

    Route::post('/map', [AccountingController::class, 'map'])
        ->name('api.acc.map');

    Route::post('/issue', [AccountingController::class, 'issue'])
        ->name('api.acc.issue');

    Route::post('/credit', [AccountingController::class, 'credit'])
        ->name('api.acc.credit');
});

/*
|--------------------------------------------------------------------------
| eFactura (RO) API Routes
|--------------------------------------------------------------------------
|
| API endpoints for automatic submission of invoices to ANAF SPV
|
*/

Route::prefix('efactura')->middleware(['throttle:api'])->group(function () {
    // Queue invoice for submission
    Route::post('/submit', [EFacturaController::class, 'submit'])
        ->name('api.efactura.submit');

    // Manual retry
    Route::post('/retry', [EFacturaController::class, 'retry'])
        ->name('api.efactura.retry');

    // Poll status
    Route::post('/poll', [EFacturaController::class, 'poll'])
        ->name('api.efactura.poll');

    // Get status
    Route::get('/status/{queueId}', [EFacturaController::class, 'status'])
        ->name('api.efactura.status');

    // Download receipt
    Route::get('/download/{queueId}', [EFacturaController::class, 'download'])
        ->name('api.efactura.download');

    // Statistics
    Route::get('/stats/{tenantId}', [EFacturaController::class, 'stats'])
        ->name('api.efactura.stats');

    // Queue list
    Route::get('/queue/{tenantId}', [EFacturaController::class, 'queueList'])
        ->name('api.efactura.queue');
});

/*
|--------------------------------------------------------------------------
| WhatsApp Notifications API Routes
|--------------------------------------------------------------------------
|
| API endpoints for WhatsApp messaging: confirmations, reminders, promos
|
*/

Route::prefix('wa')->middleware(['throttle:api'])->group(function () {
    // Template management
    Route::post('/templates', [WhatsAppController::class, 'storeTemplate'])
        ->name('api.wa.templates.store');

    // Send messages
    Route::post('/send/confirm', [WhatsAppController::class, 'sendConfirmation'])
        ->name('api.wa.send.confirm');

    Route::post('/schedule/reminders', [WhatsAppController::class, 'scheduleReminders'])
        ->name('api.wa.schedule.reminders');

    Route::post('/send/promo', [WhatsAppController::class, 'sendPromo'])
        ->name('api.wa.send.promo');

    // Webhook (with signature verification)
    Route::post('/webhook', [WhatsAppController::class, 'webhook'])
        ->name('api.wa.webhook')
        ->middleware('webhook.verify:twilio')
        ->withoutMiddleware(['throttle:api']); // Webhooks should not be throttled

    // Opt-in management
    Route::post('/optin', [WhatsAppController::class, 'manageOptIn'])
        ->name('api.wa.optin');

    // Statistics and lists
    Route::get('/stats/{tenantId}', [WhatsAppController::class, 'stats'])
        ->name('api.wa.stats');

    Route::get('/messages/{tenantId}', [WhatsAppController::class, 'listMessages'])
        ->name('api.wa.messages');

    Route::get('/schedules/{tenantId}', [WhatsAppController::class, 'listSchedules'])
        ->name('api.wa.schedules');
});

/*
|--------------------------------------------------------------------------
| Microservices Management API Routes
|--------------------------------------------------------------------------
|
| API endpoints for managing microservices, webhooks, feature flags, and notifications
|
*/

Route::prefix('microservices')->middleware(['throttle:api'])->group(function () {
    // List all available microservices
    Route::get('/', [MicroservicesController::class, 'index'])
        ->name('api.microservices.index');
    
    // Get tenant's active microservices
    Route::get('/tenant/{tenantId}', [MicroservicesController::class, 'tenantMicroservices'])
        ->name('api.microservices.tenant');
    
    // Activate microservice
    Route::post('/activate', [MicroservicesController::class, 'activate'])
        ->name('api.microservices.activate');
    
    // Deactivate microservice
    Route::post('/tenant/{tenantId}/{microserviceSlug}/deactivate', [MicroservicesController::class, 'deactivate'])
        ->name('api.microservices.deactivate');
});

/*
|--------------------------------------------------------------------------
| Webhooks Management API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('webhooks')->middleware(['throttle:api'])->group(function () {
    Route::get('/{tenantId}', [WebhookController::class, 'index'])
        ->name('api.webhooks.index');
    
    Route::post('/{tenantId}', [WebhookController::class, 'store'])
        ->name('api.webhooks.store');
    
    Route::put('/{tenantId}/{webhookId}', [WebhookController::class, 'update'])
        ->name('api.webhooks.update');
    
    Route::delete('/{tenantId}/{webhookId}', [WebhookController::class, 'destroy'])
        ->name('api.webhooks.destroy');
    
    Route::get('/{tenantId}/{webhookId}/deliveries', [WebhookController::class, 'deliveries'])
        ->name('api.webhooks.deliveries');
    
    Route::post('/{tenantId}/deliveries/{deliveryId}/retry', [WebhookController::class, 'retryDelivery'])
        ->name('api.webhooks.retry');
});

/*
|--------------------------------------------------------------------------
| Feature Flags API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('feature-flags')->middleware(['throttle:api'])->group(function () {
    Route::get('/', [FeatureFlagController::class, 'index'])
        ->name('api.feature-flags.index');
    
    Route::post('/', [FeatureFlagController::class, 'store'])
        ->name('api.feature-flags.store');
    
    Route::put('/{featureKey}', [FeatureFlagController::class, 'update'])
        ->name('api.feature-flags.update');
    
    Route::post('/{featureKey}/enable', [FeatureFlagController::class, 'enable'])
        ->name('api.feature-flags.enable');
    
    Route::post('/{featureKey}/disable', [FeatureFlagController::class, 'disable'])
        ->name('api.feature-flags.disable');
    
    Route::get('/{featureKey}/check', [FeatureFlagController::class, 'check'])
        ->name('api.feature-flags.check');
    
    Route::post('/{featureKey}/tenant/{tenantId}/enable', [FeatureFlagController::class, 'enableForTenant'])
        ->name('api.feature-flags.enable-tenant');
    
    Route::post('/{featureKey}/tenant/{tenantId}/disable', [FeatureFlagController::class, 'disableForTenant'])
        ->name('api.feature-flags.disable-tenant');
});

/*
|--------------------------------------------------------------------------
| Notifications API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('notifications')->middleware(['throttle:api'])->group(function () {
    Route::get('/{tenantId}', [NotificationController::class, 'index'])
        ->name('api.notifications.index');
    
    Route::post('/{tenantId}/{notificationId}/read', [NotificationController::class, 'markAsRead'])
        ->name('api.notifications.mark-read');
    
    Route::post('/{tenantId}/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('api.notifications.mark-all-read');
    
    Route::delete('/{tenantId}/{notificationId}', [NotificationController::class, 'destroy'])
        ->name('api.notifications.destroy');
});

/*
|--------------------------------------------------------------------------
| Promo Codes API Routes
|--------------------------------------------------------------------------
|
| API endpoints for managing promo/voucher codes
|
*/

Route::prefix('promo-codes')->middleware(['throttle:api'])->group(function () {
    // Tenant admin endpoints
    Route::get('/{tenantId}', [App\Http\Controllers\Api\PromoCodeController::class, 'index'])
        ->name('api.promo-codes.index');

    Route::post('/{tenantId}', [App\Http\Controllers\Api\PromoCodeController::class, 'store'])
        ->name('api.promo-codes.store');

    Route::get('/{id}/show', [App\Http\Controllers\Api\PromoCodeController::class, 'show'])
        ->name('api.promo-codes.show');

    Route::put('/{id}', [App\Http\Controllers\Api\PromoCodeController::class, 'update'])
        ->name('api.promo-codes.update');

    Route::post('/{id}/deactivate', [App\Http\Controllers\Api\PromoCodeController::class, 'deactivate'])
        ->name('api.promo-codes.deactivate');

    Route::delete('/{id}', [App\Http\Controllers\Api\PromoCodeController::class, 'destroy'])
        ->name('api.promo-codes.destroy');

    Route::get('/{id}/stats', [App\Http\Controllers\Api\PromoCodeController::class, 'stats'])
        ->name('api.promo-codes.stats');

    Route::post('/{id}/duplicate', [App\Http\Controllers\Api\PromoCodeController::class, 'duplicate'])
        ->name('api.promo-codes.duplicate');

    // Usage history and analytics
    Route::get('/{id}/usage-history', [App\Http\Controllers\Api\PromoCodeController::class, 'usageHistory'])
        ->name('api.promo-codes.usage-history');

    Route::get('/{id}/fraud-detection', [App\Http\Controllers\Api\PromoCodeController::class, 'fraudDetection'])
        ->name('api.promo-codes.fraud-detection');

    Route::get('/{id}/usage-timeline', [App\Http\Controllers\Api\PromoCodeController::class, 'usageTimeline'])
        ->name('api.promo-codes.usage-timeline');

    // Export/Import
    Route::get('/{tenantId}/export', [App\Http\Controllers\Api\PromoCodeController::class, 'export'])
        ->name('api.promo-codes.export');

    Route::post('/{tenantId}/import', [App\Http\Controllers\Api\PromoCodeController::class, 'import'])
        ->name('api.promo-codes.import');

    Route::get('/{id}/export-usage', [App\Http\Controllers\Api\PromoCodeController::class, 'exportUsage'])
        ->name('api.promo-codes.export-usage');

    // Bulk operations
    Route::post('/{tenantId}/bulk-create', [App\Http\Controllers\Api\PromoCodeController::class, 'bulkCreate'])
        ->name('api.promo-codes.bulk-create');

    Route::post('/bulk-activate', [App\Http\Controllers\Api\PromoCodeController::class, 'bulkActivate'])
        ->name('api.promo-codes.bulk-activate');

    Route::post('/bulk-deactivate', [App\Http\Controllers\Api\PromoCodeController::class, 'bulkDeactivate'])
        ->name('api.promo-codes.bulk-deactivate');

    Route::post('/bulk-delete', [App\Http\Controllers\Api\PromoCodeController::class, 'bulkDelete'])
        ->name('api.promo-codes.bulk-delete');

    // Public endpoint for validating codes
    Route::post('/{tenantId}/validate', [App\Http\Controllers\Api\PromoCodeController::class, 'validate'])
        ->name('api.promo-codes.validate')
        ->withoutMiddleware(['throttle:api'])
        ->middleware('throttle:60,1'); // 60 requests per minute
});

/*
|--------------------------------------------------------------------------
| Health Check API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', [App\Http\Controllers\Api\HealthController::class, 'index'])
    ->name('api.health')
    ->withoutMiddleware(['throttle:api']);

Route::get('/ping', [App\Http\Controllers\Api\HealthController::class, 'ping'])
    ->name('api.ping')
    ->withoutMiddleware(['throttle:api']);

/*
|--------------------------------------------------------------------------
| Tenant API Routes (v1)
|--------------------------------------------------------------------------
|
| Authenticated tenant API endpoints with API key authentication
|
*/

Route::prefix('v1/tenant')->middleware(['api.tenant', 'throttle:api'])->group(function () {
    // API Key Management
    Route::prefix('api-keys')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'index'])
            ->name('api.v1.tenant.api-keys.index');

        Route::post('/', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'store'])
            ->name('api.v1.tenant.api-keys.store');

        Route::get('/scopes', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'scopes'])
            ->name('api.v1.tenant.api-keys.scopes');

        Route::get('/{keyId}', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'show'])
            ->name('api.v1.tenant.api-keys.show');

        Route::put('/{keyId}', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'update'])
            ->name('api.v1.tenant.api-keys.update');

        Route::delete('/{keyId}', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'destroy'])
            ->name('api.v1.tenant.api-keys.destroy');

        Route::get('/{keyId}/usage', [App\Http\Controllers\Api\V1\Tenant\ApiKeyController::class, 'usage'])
            ->name('api.v1.tenant.api-keys.usage');
    });

    // Audit Logs
    Route::prefix('audit')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\AuditController::class, 'index'])
            ->name('api.v1.tenant.audit.index');

        Route::get('/actions', [App\Http\Controllers\Api\V1\Tenant\AuditController::class, 'actions'])
            ->name('api.v1.tenant.audit.actions');
    });

    // Health Monitoring
    Route::prefix('health')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\HealthController::class, 'index'])
            ->name('api.v1.tenant.health.index');

        Route::get('/{service}', [App\Http\Controllers\Api\V1\Tenant\HealthController::class, 'show'])
            ->name('api.v1.tenant.health.show');
    });

    // Usage Metrics
    Route::prefix('metrics')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\MetricsController::class, 'index'])
            ->name('api.v1.tenant.metrics.index');

        Route::get('/summary', [App\Http\Controllers\Api\V1\Tenant\MetricsController::class, 'summary'])
            ->name('api.v1.tenant.metrics.summary');

        Route::get('/breakdown', [App\Http\Controllers\Api\V1\Tenant\MetricsController::class, 'breakdown'])
            ->name('api.v1.tenant.metrics.breakdown');
    });

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Tenant\SubscriptionController::class, 'index'])
            ->name('api.v1.tenant.subscriptions.index');

        Route::get('/catalog', [App\Http\Controllers\Api\V1\Tenant\SubscriptionController::class, 'catalog'])
            ->name('api.v1.tenant.subscriptions.catalog');

        Route::get('/{subscriptionId}', [App\Http\Controllers\Api\V1\Tenant\SubscriptionController::class, 'show'])
            ->name('api.v1.tenant.subscriptions.show');
    });
});

/*
|--------------------------------------------------------------------------
| Domain Verification API Routes
|--------------------------------------------------------------------------
|
| API endpoints for domain ownership verification
|
*/

Route::prefix('tenant/domains')->middleware(['throttle:api', 'auth:sanctum'])->group(function () {
    // Initiate domain verification
    Route::post('/{domain}/verify/initiate', [DomainVerificationController::class, 'initiate'])
        ->name('api.tenant.domains.verify.initiate');

    // Get verification status
    Route::get('/{domain}/verify/status', [DomainVerificationController::class, 'status'])
        ->name('api.tenant.domains.verify.status');

    // Trigger verification check
    Route::post('/{domain}/verify', [DomainVerificationController::class, 'verify'])
        ->name('api.tenant.domains.verify');

    // Get instructions for a verification method
    Route::get('/{domain}/verify/instructions', [DomainVerificationController::class, 'instructions'])
        ->name('api.tenant.domains.verify.instructions');

    // Schedule background verification
    Route::post('/{domain}/verify/schedule', [DomainVerificationController::class, 'scheduleVerification'])
        ->name('api.tenant.domains.verify.schedule');

    // Package management
    Route::post('/{domain}/package/generate', [PackageController::class, 'generate'])
        ->name('api.tenant.domains.package.generate');

    Route::get('/{domain}/package/status', [PackageController::class, 'status'])
        ->name('api.tenant.domains.package.status');

    Route::get('/{domain}/packages', [PackageController::class, 'list'])
        ->name('api.tenant.domains.packages');
});

/*
|--------------------------------------------------------------------------
| Package Download Routes (Public with hash verification)
|--------------------------------------------------------------------------
*/

Route::get('/tenant/package/{package}/download/{hash}', [PackageController::class, 'download'])
    ->name('api.tenant.package.download')
    ->middleware('throttle:60,1');

Route::get('/tenant/package/{package}/install-code', [PackageController::class, 'installCode'])
    ->name('api.tenant.package.install-code')
    ->middleware(['throttle:api', 'auth:sanctum']);

Route::post('/tenant/package/{package}/invalidate', [PackageController::class, 'invalidate'])
    ->name('api.tenant.package.invalidate')
    ->middleware(['throttle:api', 'auth:sanctum']);

/*
|--------------------------------------------------------------------------
| Tenant Client API Routes
|--------------------------------------------------------------------------
|
| API endpoints called by the tenant client package (deployed on tenant domains)
| These use custom middleware for request verification and signature checking
|
*/

Route::prefix('tenant-client')->middleware(['throttle:api', 'tenant.client.cors'])->group(function () {
    // Bootstrap
    Route::get('/bootstrap', [BootstrapController::class, 'index'])
        ->name('api.tenant-client.bootstrap');

    // Auth (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->name('api.tenant-client.auth.register');
        Route::post('/login', [AuthController::class, 'login'])
            ->name('api.tenant-client.auth.login');
        Route::get('/me', [AuthController::class, 'me'])
            ->name('api.tenant-client.auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('api.tenant-client.auth.logout');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->name('api.tenant-client.auth.forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->name('api.tenant-client.auth.reset-password');
        Route::post('/verify-email', [AuthController::class, 'verifyEmail'])
            ->name('api.tenant-client.auth.verify-email');
        Route::post('/resend-verification', [AuthController::class, 'resendVerification'])
            ->name('api.tenant-client.auth.resend-verification');
        Route::post('/super-login', [AuthController::class, 'superAdminLogin'])
            ->name('api.tenant-client.auth.super-login');
    });

    // Account (requires authentication)
    Route::prefix('account')->group(function () {
        Route::get('/orders', [AccountController::class, 'orders'])
            ->name('api.tenant-client.account.orders');
        Route::get('/orders/{orderId}', [AccountController::class, 'orderDetail'])
            ->name('api.tenant-client.account.orders.detail');
        Route::get('/orders/{orderId}/beneficiaries', [AccountController::class, 'orderBeneficiaries'])
            ->name('api.tenant-client.account.orders.beneficiaries');
        Route::put('/orders/{orderId}/beneficiaries', [AccountController::class, 'updateOrderBeneficiaries'])
            ->name('api.tenant-client.account.orders.beneficiaries.update');
        Route::get('/tickets', [AccountController::class, 'tickets'])
            ->name('api.tenant-client.account.tickets');
        Route::get('/profile', [AccountController::class, 'profile'])
            ->name('api.tenant-client.account.profile');
        Route::put('/profile', [AccountController::class, 'updateProfile'])
            ->name('api.tenant-client.account.update-profile');

        // Watchlist routes
        Route::get('/watchlist', [AccountController::class, 'getWatchlist'])
            ->name('api.tenant-client.account.watchlist');
        Route::post('/watchlist/{eventId}', [AccountController::class, 'addToWatchlist'])
            ->name('api.tenant-client.account.watchlist.add');
        Route::delete('/watchlist/{eventId}', [AccountController::class, 'removeFromWatchlist'])
            ->name('api.tenant-client.account.watchlist.remove');
        Route::get('/watchlist/{eventId}/check', [AccountController::class, 'checkWatchlist'])
            ->name('api.tenant-client.account.watchlist.check');

        // Shop orders routes
        Route::get('/shop-orders', [AccountController::class, 'shopOrders'])
            ->name('api.tenant-client.account.shop-orders');
        Route::get('/shop-orders/{orderId}', [AccountController::class, 'shopOrderDetail'])
            ->name('api.tenant-client.account.shop-orders.detail');

        // Gamification / Loyalty points routes
        Route::get('/points', [AccountController::class, 'points'])
            ->name('api.tenant-client.account.points');
        Route::get('/points/history', [AccountController::class, 'pointsHistory'])
            ->name('api.tenant-client.account.points.history');
        Route::get('/points/referrals', [AccountController::class, 'referrals'])
            ->name('api.tenant-client.account.points.referrals');
        Route::get('/points/tier', [AccountController::class, 'tier'])
            ->name('api.tenant-client.account.points.tier');

        // Affiliate status (quick check for account page)
        Route::get('/affiliate', [AccountController::class, 'affiliateStatus'])
            ->name('api.tenant-client.account.affiliate');

        // Delete account
        Route::delete('/delete', [AccountController::class, 'deleteAccount'])
            ->name('api.tenant-client.account.delete');
    });

    // Events (public)
    Route::prefix('events')->group(function () {
        Route::get('/', [EventsController::class, 'index'])
            ->name('api.tenant-client.events.index');
        Route::get('/{slug}', [EventsController::class, 'show'])
            ->name('api.tenant-client.events.show');
        Route::get('/{slug}/tickets', [EventsController::class, 'tickets'])
            ->name('api.tenant-client.events.tickets');
        Route::get('/{slug}/seating', [EventsController::class, 'seating'])
            ->name('api.tenant-client.events.seating');
    });

    // Blog (public - requires blog microservice)
    Route::prefix('blog')->group(function () {
        Route::get('/', [BlogController::class, 'index'])
            ->name('api.tenant-client.blog.index');
        Route::get('/categories', [BlogController::class, 'categories'])
            ->name('api.tenant-client.blog.categories');
        Route::get('/{slug}', [BlogController::class, 'show'])
            ->name('api.tenant-client.blog.show');
    });

    // Shop (public - requires shop microservice)
    Route::prefix('shop')->group(function () {
        // Products
        Route::get('/products', [ShopProductController::class, 'index'])
            ->name('api.tenant-client.shop.products.index');
        Route::get('/products/featured', [ShopProductController::class, 'featured'])
            ->name('api.tenant-client.shop.products.featured');
        Route::get('/products/{slug}', [ShopProductController::class, 'show'])
            ->name('api.tenant-client.shop.products.show');
        Route::get('/categories', [ShopProductController::class, 'categories'])
            ->name('api.tenant-client.shop.categories');

        // Shop Cart
        Route::get('/cart', [ShopCartController::class, 'index'])
            ->name('api.tenant-client.shop.cart.index');
        Route::post('/cart/items', [ShopCartController::class, 'addItem'])
            ->name('api.tenant-client.shop.cart.add');
        Route::put('/cart/items/{itemId}', [ShopCartController::class, 'updateItem'])
            ->name('api.tenant-client.shop.cart.update');
        Route::delete('/cart/items/{itemId}', [ShopCartController::class, 'removeItem'])
            ->name('api.tenant-client.shop.cart.remove');
        Route::delete('/cart', [ShopCartController::class, 'clear'])
            ->name('api.tenant-client.shop.cart.clear');
        Route::post('/cart/coupon', [ShopCartController::class, 'applyCoupon'])
            ->name('api.tenant-client.shop.cart.coupon.apply');
        Route::delete('/cart/coupon', [ShopCartController::class, 'removeCoupon'])
            ->name('api.tenant-client.shop.cart.coupon.remove');
        Route::post('/cart/gift-card', [ShopCartController::class, 'applyGiftCard'])
            ->name('api.tenant-client.shop.cart.gift-card');

        // Shop Orders
        Route::get('/orders', [ShopOrderController::class, 'index'])
            ->name('api.tenant-client.shop.orders.index');
        Route::get('/orders/{orderNumber}', [ShopOrderController::class, 'show'])
            ->name('api.tenant-client.shop.orders.show');
        Route::get('/orders/{orderNumber}/downloads', [ShopOrderController::class, 'downloads'])
            ->name('api.tenant-client.shop.orders.downloads');

        // Reviews
        Route::get('/products/{slug}/reviews', [ShopReviewController::class, 'index'])
            ->name('api.tenant-client.shop.reviews.index');
        Route::post('/products/{slug}/reviews', [ShopReviewController::class, 'store'])
            ->name('api.tenant-client.shop.reviews.store');

        // Event Products (Upsells & Bundles)
        Route::get('/events/{eventId}/upsells', [ShopEventProductController::class, 'upsells'])
            ->name('api.tenant-client.shop.events.upsells');
        Route::get('/ticket-types/{ticketTypeId}/upsells', [ShopEventProductController::class, 'ticketTypeUpsells'])
            ->name('api.tenant-client.shop.ticket-types.upsells');
        Route::get('/ticket-types/{ticketTypeId}/bundles', [ShopEventProductController::class, 'bundles'])
            ->name('api.tenant-client.shop.ticket-types.bundles');

        // Checkout
        Route::get('/checkout/initialize', [ShopCheckoutController::class, 'initialize'])
            ->name('api.tenant-client.shop.checkout.initialize');
        Route::post('/checkout/shipping-methods', [ShopCheckoutController::class, 'shippingMethods'])
            ->name('api.tenant-client.shop.checkout.shipping-methods');
        Route::post('/checkout/validate', [ShopCheckoutController::class, 'validate'])
            ->name('api.tenant-client.shop.checkout.validate');
        Route::post('/checkout/calculate', [ShopCheckoutController::class, 'calculateTotals'])
            ->name('api.tenant-client.shop.checkout.calculate');
        Route::post('/checkout/create-order', [ShopCheckoutController::class, 'createOrder'])
            ->name('api.tenant-client.shop.checkout.create-order');
        Route::post('/checkout/orders/{orderNumber}/confirm-payment', [ShopCheckoutController::class, 'confirmPayment'])
            ->name('api.tenant-client.shop.checkout.confirm-payment');

        // Wishlist
        Route::get('/wishlist', [ShopWishlistController::class, 'index'])
            ->name('api.tenant-client.shop.wishlist.index');
        Route::post('/wishlist/items', [ShopWishlistController::class, 'addItem'])
            ->name('api.tenant-client.shop.wishlist.add');
        Route::delete('/wishlist/items/{itemId}', [ShopWishlistController::class, 'removeItem'])
            ->name('api.tenant-client.shop.wishlist.remove');
        Route::delete('/wishlist/products/{productId}', [ShopWishlistController::class, 'removeByProduct'])
            ->name('api.tenant-client.shop.wishlist.remove-by-product');
        Route::get('/wishlist/check/{productId}', [ShopWishlistController::class, 'check'])
            ->name('api.tenant-client.shop.wishlist.check');
        Route::delete('/wishlist', [ShopWishlistController::class, 'clear'])
            ->name('api.tenant-client.shop.wishlist.clear');
        Route::post('/wishlist/merge', [ShopWishlistController::class, 'merge'])
            ->name('api.tenant-client.shop.wishlist.merge');

        // Stock Alerts
        Route::post('/stock-alerts/subscribe', [ShopStockAlertController::class, 'subscribe'])
            ->name('api.tenant-client.shop.stock-alerts.subscribe');
        Route::delete('/stock-alerts/{alertId}', [ShopStockAlertController::class, 'unsubscribe'])
            ->name('api.tenant-client.shop.stock-alerts.unsubscribe');
        Route::post('/stock-alerts/unsubscribe-by-product', [ShopStockAlertController::class, 'unsubscribeByProduct'])
            ->name('api.tenant-client.shop.stock-alerts.unsubscribe-by-product');
        Route::get('/stock-alerts/my-alerts', [ShopStockAlertController::class, 'myAlerts'])
            ->name('api.tenant-client.shop.stock-alerts.my-alerts');
        Route::get('/stock-alerts/check/{productId}', [ShopStockAlertController::class, 'check'])
            ->name('api.tenant-client.shop.stock-alerts.check');
    });

    // Gamification (loyalty points - requires gamification microservice)
    Route::prefix('gamification')->group(function () {
        // Public endpoints (no auth required)
        Route::get('/config', [GamificationController::class, 'config'])
            ->name('api.tenant-client.gamification.config');
        Route::get('/how-to-earn', [GamificationController::class, 'howToEarn'])
            ->name('api.tenant-client.gamification.how-to-earn');
        Route::post('/referral/{code}/track', [GamificationController::class, 'trackReferral'])
            ->name('api.tenant-client.gamification.referral.track');

        // Authenticated endpoints (customer auth required)
        Route::get('/balance', [GamificationController::class, 'balance'])
            ->name('api.tenant-client.gamification.balance');
        Route::get('/history', [GamificationController::class, 'history'])
            ->name('api.tenant-client.gamification.history');
        Route::get('/referral', [GamificationController::class, 'referral'])
            ->name('api.tenant-client.gamification.referral');

        // Checkout integration
        Route::post('/check-redemption', [GamificationController::class, 'checkRedemption'])
            ->name('api.tenant-client.gamification.check-redemption');
        Route::post('/redeem', [GamificationController::class, 'redeem'])
            ->name('api.tenant-client.gamification.redeem');
    });

    // Affiliate Program (requires affiliates microservice)
    Route::prefix('affiliate')->group(function () {
        // Public endpoints
        Route::get('/program', [TenantClientAffiliateController::class, 'programInfo'])
            ->name('api.tenant-client.affiliate.program');

        // Registration (requires customer auth)
        Route::post('/register', [TenantClientAffiliateController::class, 'register'])
            ->name('api.tenant-client.affiliate.register');

        // Dashboard and stats (requires customer auth + affiliate account)
        Route::get('/dashboard', [TenantClientAffiliateController::class, 'dashboard'])
            ->name('api.tenant-client.affiliate.dashboard');
        Route::get('/conversions', [TenantClientAffiliateController::class, 'conversions'])
            ->name('api.tenant-client.affiliate.conversions');
        Route::get('/clicks', [TenantClientAffiliateController::class, 'clicks'])
            ->name('api.tenant-client.affiliate.clicks');

        // Payment details management
        Route::put('/payment-details', [TenantClientAffiliateController::class, 'updatePaymentDetails'])
            ->name('api.tenant-client.affiliate.payment-details');

        // Withdrawals
        Route::get('/withdrawals', [TenantClientAffiliateController::class, 'withdrawals'])
            ->name('api.tenant-client.affiliate.withdrawals');
        Route::post('/withdrawals', [TenantClientAffiliateController::class, 'requestWithdrawal'])
            ->name('api.tenant-client.affiliate.withdrawals.request');
        Route::delete('/withdrawals/{withdrawalId}', [TenantClientAffiliateController::class, 'cancelWithdrawal'])
            ->name('api.tenant-client.affiliate.withdrawals.cancel');
    });

    // Cart
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index'])
            ->name('api.tenant-client.cart.index');
        Route::post('/items', [CartController::class, 'addItem'])
            ->name('api.tenant-client.cart.add');
        Route::put('/items/{itemId}', [CartController::class, 'updateItem'])
            ->name('api.tenant-client.cart.update');
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem'])
            ->name('api.tenant-client.cart.remove');
        Route::delete('/', [CartController::class, 'clear'])
            ->name('api.tenant-client.cart.clear');
        Route::post('/promo-code', [CartController::class, 'applyPromoCode'])
            ->name('api.tenant-client.cart.promo-code.apply');
        Route::delete('/promo-code', [CartController::class, 'removePromoCode'])
            ->name('api.tenant-client.cart.promo-code.remove');
    });

    // Payment
    Route::prefix('payment')->group(function () {
        Route::get('/config', [CartController::class, 'getPaymentConfig'])
            ->name('api.tenant-client.payment.config');
        Route::post('/create-intent', [CartController::class, 'createPaymentIntent'])
            ->name('api.tenant-client.payment.create-intent');
    });

    // Checkout
    Route::prefix('checkout')->group(function () {
        Route::post('/init', [CheckoutController::class, 'init'])
            ->name('api.tenant-client.checkout.init');
        Route::post('/submit', [CheckoutController::class, 'submit'])
            ->name('api.tenant-client.checkout.submit');
        Route::get('/order/{orderId}', [CheckoutController::class, 'orderStatus'])
            ->name('api.tenant-client.checkout.order-status');
        Route::post('/insurance-quote', [CheckoutController::class, 'insuranceQuote'])
            ->name('api.tenant-client.checkout.insurance-quote');
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\TenantClient\OrderController::class, 'store'])
            ->name('api.tenant-client.orders.store');
        Route::get('/{orderId}', [\App\Http\Controllers\Api\TenantClient\OrderController::class, 'show'])
            ->name('api.tenant-client.orders.show');
    });

    // Cookie Consent (GDPR compliance)
    Route::prefix('consent')->group(function () {
        Route::get('/', [CookieConsentController::class, 'getConsent'])
            ->name('api.tenant-client.consent.get');
        Route::post('/', [CookieConsentController::class, 'saveConsent'])
            ->name('api.tenant-client.consent.save');
        Route::post('/withdraw', [CookieConsentController::class, 'withdrawConsent'])
            ->name('api.tenant-client.consent.withdraw');
        Route::get('/history', [CookieConsentController::class, 'getConsentHistory'])
            ->name('api.tenant-client.consent.history');

        // Consent Renewal
        Route::get('/renewal-status', [CookieConsentController::class, 'getRenewalStatus'])
            ->name('api.tenant-client.consent.renewal-status');
        Route::post('/renew', [CookieConsentController::class, 'renewConsent'])
            ->name('api.tenant-client.consent.renew');

        // Consent Analytics (Dashboard)
        Route::prefix('analytics')->group(function () {
            Route::get('/overview', [CookieConsentController::class, 'analyticsOverview'])
                ->name('api.tenant-client.consent.analytics.overview');
            Route::get('/trends', [CookieConsentController::class, 'analyticsTrends'])
                ->name('api.tenant-client.consent.analytics.trends');
            Route::get('/geographic', [CookieConsentController::class, 'analyticsGeographic'])
                ->name('api.tenant-client.consent.analytics.geographic');
            Route::get('/devices', [CookieConsentController::class, 'analyticsDevices'])
                ->name('api.tenant-client.consent.analytics.devices');
            Route::get('/sources', [CookieConsentController::class, 'analyticsSources'])
                ->name('api.tenant-client.consent.analytics.sources');
            Route::get('/activity', [CookieConsentController::class, 'analyticsActivity'])
                ->name('api.tenant-client.consent.analytics.activity');
            Route::get('/changes', [CookieConsentController::class, 'analyticsChanges'])
                ->name('api.tenant-client.consent.analytics.changes');
            Route::get('/widget', [CookieConsentController::class, 'analyticsWidget'])
                ->name('api.tenant-client.consent.analytics.widget');
            Route::get('/expiring', [CookieConsentController::class, 'analyticsExpiring'])
                ->name('api.tenant-client.consent.analytics.expiring');
        });
    });

    // Taxes (public - calculates applicable taxes for checkout)
    Route::prefix('taxes')->group(function () {
        Route::get('/applicable', [TaxController::class, 'getApplicableTaxes'])
            ->name('api.tenant-client.taxes.applicable');
        Route::post('/calculate', [TaxController::class, 'calculateTaxes'])
            ->name('api.tenant-client.taxes.calculate');
        Route::get('/effective-rate', [TaxController::class, 'getEffectiveRate'])
            ->name('api.tenant-client.taxes.effective-rate');
        Route::get('/summary', [TaxController::class, 'getSummary'])
            ->name('api.tenant-client.taxes.summary');
        Route::get('/locations', [TaxController::class, 'getLocations'])
            ->name('api.tenant-client.taxes.locations');
        Route::get('/locations/counties', [TaxController::class, 'getCounties'])
            ->name('api.tenant-client.taxes.counties');
        Route::get('/locations/cities', [TaxController::class, 'getCities'])
            ->name('api.tenant-client.taxes.cities');
        Route::get('/checkout', [TaxController::class, 'getCheckoutTaxes'])
            ->name('api.tenant-client.taxes.checkout');
    });

    // Admin (requires admin auth)
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])
            ->name('api.tenant-client.admin.dashboard');

        // Events management
        Route::get('/events', [AdminController::class, 'events'])
            ->name('api.tenant-client.admin.events');
        Route::post('/events', [AdminController::class, 'createEvent'])
            ->name('api.tenant-client.admin.events.create');
        Route::put('/events/{eventId}', [AdminController::class, 'updateEvent'])
            ->name('api.tenant-client.admin.events.update');

        // Orders
        Route::get('/orders', [AdminController::class, 'orders'])
            ->name('api.tenant-client.admin.orders');
        Route::get('/orders/{orderId}', [AdminController::class, 'orderDetail'])
            ->name('api.tenant-client.admin.orders.detail');

        // Customers
        Route::get('/customers', [AdminController::class, 'customers'])
            ->name('api.tenant-client.admin.customers');

        // Settings
        Route::get('/settings', [AdminController::class, 'settings'])
            ->name('api.tenant-client.admin.settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])
            ->name('api.tenant-client.admin.settings.update');

        // Theme management
        Route::put('/theme', [ThemeController::class, 'update'])
            ->name('api.tenant-client.admin.theme.update');

        // Page builder management
        Route::post('/pages', [PagesController::class, 'store'])
            ->name('api.tenant-client.admin.pages.store');
        Route::put('/pages/{id}', [PagesController::class, 'update'])
            ->name('api.tenant-client.admin.pages.update');
        Route::delete('/pages/{id}', [PagesController::class, 'destroy'])
            ->name('api.tenant-client.admin.pages.destroy');
    });
});

// Payment callbacks (no tenant.client middleware - called by payment processors)
Route::post('/tenant-client/checkout/callback/{provider}', [CheckoutController::class, 'paymentCallback'])
    ->name('api.tenant-client.checkout.callback')
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Marketplace Client API Routes
|--------------------------------------------------------------------------
|
| API endpoints for marketplace clients (like AmBilet.ro) that have their
| own custom websites hosted on their own servers. All data flows through
| these endpoints.
|
*/

use App\Http\Controllers\Api\MarketplaceClient\ConfigController as MarketplaceConfigController;
use App\Http\Controllers\Api\MarketplaceClient\MarketplaceEventsController;
use App\Http\Controllers\Api\MarketplaceClient\OrdersController as MarketplaceOrdersController;
use App\Http\Controllers\Api\MarketplaceClient\PaymentController as MarketplacePaymentController;
use App\Http\Controllers\Api\MarketplaceClient\TicketsController as MarketplaceTicketsController;
use App\Http\Controllers\Api\MarketplaceClient\StatisticsController as MarketplaceStatisticsController;
use App\Http\Controllers\Api\MarketplaceClient\NewsletterTrackingController;
use App\Http\Controllers\Api\MarketplaceClient\PromoCodeController as MarketplacePromoCodeController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\FavoritesController as CustomerFavoritesController;

Route::prefix('marketplace-client')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    // Handle OPTIONS preflight requests
    Route::options('/{any}', fn () => response('', 200))
        ->where('any', '.*')
        ->name('api.marketplace-client.options');

    // Newsletter Tracking (public, no auth required)
    Route::get('/newsletter/track/open', [NewsletterTrackingController::class, 'trackOpen'])
        ->name('api.marketplace-client.newsletter.track.open');
    Route::get('/newsletter/track/click', [NewsletterTrackingController::class, 'trackClick'])
        ->name('api.marketplace-client.newsletter.track.click');
    Route::get('/newsletter/unsubscribe', [NewsletterTrackingController::class, 'unsubscribe'])
        ->name('api.marketplace-client.newsletter.unsubscribe');
    Route::get('/newsletter/preferences', [NewsletterTrackingController::class, 'preferences'])
        ->name('api.marketplace-client.newsletter.preferences');
    Route::post('/newsletter/preferences', [NewsletterTrackingController::class, 'updatePreferences'])
        ->name('api.marketplace-client.newsletter.preferences.update');

    // Configuration & Authentication
    Route::get('/config', [MarketplaceConfigController::class, 'index'])
        ->name('api.marketplace-client.config');
    Route::get('/tenants', [MarketplaceConfigController::class, 'tenants'])
        ->name('api.marketplace-client.tenants');

    // Events
    Route::get('/events', [MarketplaceEventsController::class, 'index'])
        ->name('api.marketplace-client.events');
    Route::get('/events/featured', [MarketplaceEventsController::class, 'featured'])
        ->name('api.marketplace-client.events.featured');
    Route::get('/events/categories', [MarketplaceEventsController::class, 'categories'])
        ->name('api.marketplace-client.events.categories');
    Route::get('/events/cities', [MarketplaceEventsController::class, 'cities'])
        ->name('api.marketplace-client.events.cities');
    Route::get('/events/{event}', [MarketplaceEventsController::class, 'show'])
        ->name('api.marketplace-client.events.show');
    Route::get('/events/{event}/availability', [MarketplaceEventsController::class, 'availability'])
        ->name('api.marketplace-client.events.availability');
    Route::post('/events/{event}/track-view', [MarketplaceEventsController::class, 'trackView'])
        ->name('api.marketplace-client.events.track-view');
    Route::post('/events/{event}/toggle-interest', [MarketplaceEventsController::class, 'toggleInterest'])
        ->name('api.marketplace-client.events.toggle-interest');
    Route::get('/events/{event}/check-interest', [MarketplaceEventsController::class, 'checkInterest'])
        ->name('api.marketplace-client.events.check-interest');
// Artist/Venue Favorites (uses CustomerFavoritesController)
    Route::post("/artists/{artist}/toggle-favorite", [CustomerFavoritesController::class, "toggleArtist"])
        ->name("api.marketplace-client.artists.toggle-favorite");
    Route::get("/artists/{artist}/check-favorite", [CustomerFavoritesController::class, "checkArtist"])
        ->name("api.marketplace-client.artists.check-favorite");
    Route::post("/venues/{venue}/toggle-favorite", [CustomerFavoritesController::class, "toggleVenue"])
        ->name("api.marketplace-client.venues.toggle-favorite");
    Route::get("/venues/{venue}/check-favorite", [CustomerFavoritesController::class, "checkVenue"])
        ->name("api.marketplace-client.venues.check-favorite");

    // List favorites
    Route::get("/customer/favorites/artists", [CustomerFavoritesController::class, "listArtists"])
        ->name("api.marketplace-client.favorites.artists");
    Route::get("/customer/favorites/venues", [CustomerFavoritesController::class, "listVenues"])
        ->name("api.marketplace-client.favorites.venues");
    Route::get("/customer/favorites/summary", [CustomerFavoritesController::class, "summary"])
        ->name("api.marketplace-client.favorites.summary");

    // Orders
    Route::get('/orders', [MarketplaceOrdersController::class, 'index'])
        ->name('api.marketplace-client.orders');
    Route::post('/orders', [MarketplaceOrdersController::class, 'create'])
        ->name('api.marketplace-client.orders.create');
    Route::get('/orders/{order}', [MarketplaceOrdersController::class, 'show'])
        ->name('api.marketplace-client.orders.show');
    Route::post('/orders/{order}/cancel', [MarketplaceOrdersController::class, 'cancel'])
        ->name('api.marketplace-client.orders.cancel');
    Route::post('/orders/{order}/refund', [MarketplaceOrdersController::class, 'refund'])
        ->name('api.marketplace-client.orders.refund');

    // Payment
    Route::post('/orders/{order}/pay', [MarketplacePaymentController::class, 'initiate'])
        ->name('api.marketplace-client.orders.pay');
    Route::get('/orders/{order}/payment-status', [MarketplacePaymentController::class, 'status'])
        ->name('api.marketplace-client.orders.payment-status');

    // Tickets
    Route::get('/orders/{order}/tickets', [MarketplaceTicketsController::class, 'index'])
        ->name('api.marketplace-client.orders.tickets');
    Route::get('/orders/{order}/tickets/download', [MarketplaceTicketsController::class, 'downloadAll'])
        ->name('api.marketplace-client.orders.tickets.download');
    Route::get('/tickets/{ticket}/download', [MarketplaceTicketsController::class, 'download'])
        ->name('api.marketplace-client.tickets.download');
    Route::get('/tickets/{ticket}/qr', [MarketplaceTicketsController::class, 'qrCode'])
        ->name('api.marketplace-client.tickets.qr');
    Route::post('/tickets/validate', [MarketplaceTicketsController::class, 'validate'])
        ->name('api.marketplace-client.tickets.validate');

    // Statistics & Reports
    Route::get('/stats/dashboard', [MarketplaceStatisticsController::class, 'dashboard'])
        ->name('api.marketplace-client.stats.dashboard');
    Route::get('/stats/timeline', [MarketplaceStatisticsController::class, 'salesTimeline'])
        ->name('api.marketplace-client.stats.timeline');
    Route::get('/stats/by-event', [MarketplaceStatisticsController::class, 'salesByEvent'])
        ->name('api.marketplace-client.stats.by-event');
    Route::get('/stats/by-tenant', [MarketplaceStatisticsController::class, 'salesByTenant'])
        ->name('api.marketplace-client.stats.by-tenant');
    Route::get('/stats/commission-report', [MarketplaceStatisticsController::class, 'commissionReport'])
        ->name('api.marketplace-client.stats.commission-report');
    Route::get('/stats/commission-report/export', [MarketplaceStatisticsController::class, 'exportCommissionReport'])
        ->name('api.marketplace-client.stats.commission-report.export');

    // Promo Codes (public endpoints for checkout)
    Route::post('/promo-codes/validate', [MarketplacePromoCodeController::class, 'validate'])
        ->name('api.marketplace-client.promo-codes.validate');
    Route::get('/events/{event}/promo-codes', [MarketplacePromoCodeController::class, 'publicCodes'])
        ->name('api.marketplace-client.events.promo-codes');
});

// Payment callback (no auth middleware - called by payment processors)
Route::post('/marketplace-client/payment/callback/{client}', [MarketplacePaymentController::class, 'callback'])
    ->name('api.marketplace-client.payment.callback')
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Marketplace Organizer API Routes
|--------------------------------------------------------------------------
|
| API endpoints for marketplace organizers (event creators on a marketplace).
| Organizers can create events, view sales, and manage their profile.
|
*/

use App\Http\Controllers\Api\MarketplaceClient\Organizer\AuthController as OrganizerAuthController;
use App\Http\Controllers\Api\MarketplaceClient\Organizer\EventsController as OrganizerEventsController;
use App\Http\Controllers\Api\MarketplaceClient\Organizer\DashboardController as OrganizerDashboardController;
use App\Http\Controllers\Api\MarketplaceClient\Organizer\PayoutController as OrganizerPayoutController;
use App\Http\Controllers\Api\MarketplaceClient\Organizer\PromoCodeController as OrganizerPromoCodeController;
use App\Http\Controllers\Api\MarketplaceClient\Organizer\TaxReportController as OrganizerTaxReportController;
use App\Http\Controllers\Api\MarketplaceClient\Organizer\InvitationsController as OrganizerInvitationsController;
use App\Http\Controllers\Api\MarketplaceClient\Organizer\RefundReportController as OrganizerRefundReportController;

Route::prefix('marketplace-client/organizer')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    // Public routes (no organizer auth)
    Route::post('/register', [OrganizerAuthController::class, 'register'])
        ->name('api.marketplace-client.organizer.register');
    Route::post('/login', [OrganizerAuthController::class, 'login'])
        ->name('api.marketplace-client.organizer.login');
    Route::post('/forgot-password', [OrganizerAuthController::class, 'forgotPassword'])
        ->name('api.marketplace-client.organizer.forgot-password');
    Route::post('/reset-password', [OrganizerAuthController::class, 'resetPassword'])
        ->name('api.marketplace-client.organizer.reset-password');
    Route::post('/verify-email', [OrganizerAuthController::class, 'verifyEmail'])
        ->name('api.marketplace-client.organizer.verify-email');
    Route::post('/resend-verification', [OrganizerAuthController::class, 'resendVerification'])
        ->name('api.marketplace-client.organizer.resend-verification');

    // Protected routes (require organizer auth)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/logout', [OrganizerAuthController::class, 'logout'])
            ->name('api.marketplace-client.organizer.logout');
        Route::get('/me', [OrganizerAuthController::class, 'me'])
            ->name('api.marketplace-client.organizer.me');
        Route::put('/profile', [OrganizerAuthController::class, 'updateProfile'])
            ->name('api.marketplace-client.organizer.profile.update');
        Route::put('/password', [OrganizerAuthController::class, 'updatePassword'])
            ->name('api.marketplace-client.organizer.password.update');
        Route::put('/payout-details', [OrganizerAuthController::class, 'updatePayoutDetails'])
            ->name('api.marketplace-client.organizer.payout-details.update');

        // Dashboard
        Route::get('/dashboard', [OrganizerDashboardController::class, 'index'])
            ->name('api.marketplace-client.organizer.dashboard');
        Route::get('/dashboard/timeline', [OrganizerDashboardController::class, 'salesTimeline'])
            ->name('api.marketplace-client.organizer.dashboard.timeline');
        Route::get('/dashboard/recent-orders', [OrganizerDashboardController::class, 'recentOrders'])
            ->name('api.marketplace-client.organizer.dashboard.recent-orders');
        Route::get('/dashboard/payout-summary', [OrganizerDashboardController::class, 'payoutSummary'])
            ->name('api.marketplace-client.organizer.dashboard.payout-summary');

        // Orders
        Route::get('/orders', [OrganizerDashboardController::class, 'orders'])
            ->name('api.marketplace-client.organizer.orders');
        Route::get('/orders/{order}', [OrganizerDashboardController::class, 'orderDetail'])
            ->name('api.marketplace-client.organizer.orders.show');

        // Events
        Route::get('/events', [OrganizerEventsController::class, 'index'])
            ->name('api.marketplace-client.organizer.events');
        Route::post('/events', [OrganizerEventsController::class, 'store'])
            ->name('api.marketplace-client.organizer.events.store');
        Route::get('/events/{event}', [OrganizerEventsController::class, 'show'])
            ->name('api.marketplace-client.organizer.events.show');
        Route::put('/events/{event}', [OrganizerEventsController::class, 'update'])
            ->name('api.marketplace-client.organizer.events.update');
        Route::post('/events/{event}/submit', [OrganizerEventsController::class, 'submit'])
            ->name('api.marketplace-client.organizer.events.submit');
        Route::post('/events/{event}/cancel', [OrganizerEventsController::class, 'cancel'])
            ->name('api.marketplace-client.organizer.events.cancel');
        Route::get('/events/{event}/statistics', [OrganizerEventsController::class, 'statistics'])
            ->name('api.marketplace-client.organizer.events.statistics');

        // Participants / Check-in
        Route::get('/events/{event}/participants', [OrganizerEventsController::class, 'participants'])
            ->name('api.marketplace-client.organizer.events.participants');
        Route::get('/events/{event}/participants/export', [OrganizerEventsController::class, 'exportParticipants'])
            ->name('api.marketplace-client.organizer.events.participants.export');
        Route::post('/events/{event}/check-in/{barcode}', [OrganizerEventsController::class, 'checkIn'])
            ->name('api.marketplace-client.organizer.events.check-in');
        Route::delete('/events/{event}/check-in/{barcode}', [OrganizerEventsController::class, 'undoCheckIn'])
            ->name('api.marketplace-client.organizer.events.check-in.undo');

        // Event form helpers (categories, genres, venues)
        Route::get('/event-categories', [OrganizerEventsController::class, 'categories'])
            ->name('api.marketplace-client.organizer.event-categories');
        Route::get('/event-genres', [OrganizerEventsController::class, 'genres'])
            ->name('api.marketplace-client.organizer.event-genres');
        Route::get('/venues', [OrganizerEventsController::class, 'venues'])
            ->name('api.marketplace-client.organizer.venues');
        Route::get('/artists', [OrganizerEventsController::class, 'artists'])
            ->name('api.marketplace-client.organizer.artists');
        Route::post('/artists', [OrganizerEventsController::class, 'storeArtist'])
            ->name('api.marketplace-client.organizer.artists.store');

        // Payouts & Balance
        Route::get('/balance', [OrganizerPayoutController::class, 'balance'])
            ->name('api.marketplace-client.organizer.balance');
        Route::get('/transactions', [OrganizerPayoutController::class, 'transactions'])
            ->name('api.marketplace-client.organizer.transactions');
        Route::get('/payouts', [OrganizerPayoutController::class, 'payouts'])
            ->name('api.marketplace-client.organizer.payouts');
        Route::post('/payouts', [OrganizerPayoutController::class, 'requestPayout'])
            ->name('api.marketplace-client.organizer.payouts.request');
        Route::get('/payouts/{payout}', [OrganizerPayoutController::class, 'showPayout'])
            ->name('api.marketplace-client.organizer.payouts.show');
        Route::delete('/payouts/{payout}', [OrganizerPayoutController::class, 'cancelPayout'])
            ->name('api.marketplace-client.organizer.payouts.cancel');
        Route::get('/statements', [OrganizerPayoutController::class, 'statements'])
            ->name('api.marketplace-client.organizer.statements');

        // Promo Codes
        Route::get('/promo-codes', [OrganizerPromoCodeController::class, 'index'])
            ->name('api.marketplace-client.organizer.promo-codes');
        Route::post('/promo-codes', [OrganizerPromoCodeController::class, 'store'])
            ->name('api.marketplace-client.organizer.promo-codes.store');
        Route::post('/promo-codes/bulk', [OrganizerPromoCodeController::class, 'bulkCreate'])
            ->name('api.marketplace-client.organizer.promo-codes.bulk');
        Route::get('/promo-codes/{promoCode}', [OrganizerPromoCodeController::class, 'show'])
            ->name('api.marketplace-client.organizer.promo-codes.show');
        Route::put('/promo-codes/{promoCode}', [OrganizerPromoCodeController::class, 'update'])
            ->name('api.marketplace-client.organizer.promo-codes.update');
        Route::delete('/promo-codes/{promoCode}', [OrganizerPromoCodeController::class, 'destroy'])
            ->name('api.marketplace-client.organizer.promo-codes.destroy');
        Route::post('/promo-codes/{promoCode}/activate', [OrganizerPromoCodeController::class, 'activate'])
            ->name('api.marketplace-client.organizer.promo-codes.activate');
        Route::post('/promo-codes/{promoCode}/deactivate', [OrganizerPromoCodeController::class, 'deactivate'])
            ->name('api.marketplace-client.organizer.promo-codes.deactivate');
        Route::get('/promo-codes/{promoCode}/stats', [OrganizerPromoCodeController::class, 'stats'])
            ->name('api.marketplace-client.organizer.promo-codes.stats');
        Route::get('/promo-codes/{promoCode}/usage', [OrganizerPromoCodeController::class, 'usageHistory'])
            ->name('api.marketplace-client.organizer.promo-codes.usage');

        // Tax Reports
        Route::get('/tax/settings', [OrganizerTaxReportController::class, 'settings'])
            ->name('api.marketplace-client.organizer.tax.settings');
        Route::put('/tax/settings', [OrganizerTaxReportController::class, 'updateSettings'])
            ->name('api.marketplace-client.organizer.tax.settings.update');
        Route::get('/tax/annual', [OrganizerTaxReportController::class, 'annualSummary'])
            ->name('api.marketplace-client.organizer.tax.annual');
        Route::get('/tax/quarterly', [OrganizerTaxReportController::class, 'quarterlyReport'])
            ->name('api.marketplace-client.organizer.tax.quarterly');
        Route::get('/tax/document', [OrganizerTaxReportController::class, 'taxDocument'])
            ->name('api.marketplace-client.organizer.tax.document');

        // Invitations
        Route::get('/invitations', [OrganizerInvitationsController::class, 'index'])
            ->name('api.marketplace-client.organizer.invitations');
        Route::post('/invitations', [OrganizerInvitationsController::class, 'store'])
            ->name('api.marketplace-client.organizer.invitations.store');
        Route::get('/invitations/{batch}', [OrganizerInvitationsController::class, 'show'])
            ->name('api.marketplace-client.organizer.invitations.show');
        Route::post('/invitations/{batch}/generate', [OrganizerInvitationsController::class, 'generate'])
            ->name('api.marketplace-client.organizer.invitations.generate');
        Route::get('/invitations/{batch}/invites', [OrganizerInvitationsController::class, 'invitations'])
            ->name('api.marketplace-client.organizer.invitations.list');
        Route::post('/invitations/{batch}/send', [OrganizerInvitationsController::class, 'send'])
            ->name('api.marketplace-client.organizer.invitations.send');
        Route::get('/invitations/{batch}/download', [OrganizerInvitationsController::class, 'download'])
            ->name('api.marketplace-client.organizer.invitations.download');
        Route::post('/invitations/{batch}/void', [OrganizerInvitationsController::class, 'void'])
            ->name('api.marketplace-client.organizer.invitations.void');
        Route::get('/invitations/{batch}/stats', [OrganizerInvitationsController::class, 'stats'])
            ->name('api.marketplace-client.organizer.invitations.stats');
        Route::delete('/invitations/{batch}', [OrganizerInvitationsController::class, 'destroy'])
            ->name('api.marketplace-client.organizer.invitations.destroy');

        // Refund Reports
        Route::get('/refunds', [OrganizerRefundReportController::class, 'index'])
            ->name('api.marketplace-client.organizer.refunds');
        Route::get('/refunds/statistics', [OrganizerRefundReportController::class, 'statistics'])
            ->name('api.marketplace-client.organizer.refunds.statistics');
        Route::get('/refunds/export', [OrganizerRefundReportController::class, 'export'])
            ->name('api.marketplace-client.organizer.refunds.export');
        Route::get('/refunds/{refund}', [OrganizerRefundReportController::class, 'show'])
            ->name('api.marketplace-client.organizer.refunds.show');
    });
});

/*
|--------------------------------------------------------------------------
| Marketplace Customer API Routes
|--------------------------------------------------------------------------
|
| API endpoints for marketplace customers (ticket buyers on a marketplace).
| Customers can view orders, tickets, and manage their profile.
|
*/

use App\Http\Controllers\Api\MarketplaceClient\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\AccountController as CustomerAccountController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\TicketTransferController as CustomerTicketTransferController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\RefundController as CustomerRefundController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\GiftCardController as CustomerGiftCardController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\StatsController as CustomerStatsController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\ReviewsController as CustomerReviewsController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\WatchlistController as CustomerWatchlistController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\RewardsController as CustomerRewardsController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\NotificationsController as CustomerNotificationsController;
use App\Http\Controllers\Api\MarketplaceClient\Customer\ReferralsController as CustomerReferralsController;

Route::prefix('marketplace-client/customer')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    // Public routes (no customer auth)
    Route::post('/register', [CustomerAuthController::class, 'register'])
        ->name('api.marketplace-client.customer.register');
    Route::post('/login', [CustomerAuthController::class, 'login'])
        ->name('api.marketplace-client.customer.login');
    Route::post('/forgot-password', [CustomerAuthController::class, 'forgotPassword'])
        ->name('api.marketplace-client.customer.forgot-password');
    Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])
        ->name('api.marketplace-client.customer.reset-password');
    Route::post('/verify-email', [CustomerAuthController::class, 'verifyEmail'])
        ->name('api.marketplace-client.customer.verify-email');
    Route::post('/resend-verification', [CustomerAuthController::class, 'resendVerification'])
        ->name('api.marketplace-client.customer.resend-verification');

    // Protected routes (require customer auth)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/logout', [CustomerAuthController::class, 'logout'])
            ->name('api.marketplace-client.customer.logout');
        Route::get('/me', [CustomerAuthController::class, 'me'])
            ->name('api.marketplace-client.customer.me');
        Route::put('/profile', [CustomerAuthController::class, 'updateProfile'])
            ->name('api.marketplace-client.customer.profile.update');
        Route::put('/password', [CustomerAuthController::class, 'updatePassword'])
            ->name('api.marketplace-client.customer.password.update');
        Route::put('/marketing', [CustomerAuthController::class, 'updateMarketingPreferences'])
            ->name('api.marketplace-client.customer.marketing.update');
        Route::put('/settings', [CustomerAuthController::class, 'updateSettings'])
            ->name('api.marketplace-client.customer.settings.update');

        // Account
        Route::get('/orders', [CustomerAccountController::class, 'orders'])
            ->name('api.marketplace-client.customer.orders');
        Route::get('/orders/{order}', [CustomerAccountController::class, 'orderDetail'])
            ->name('api.marketplace-client.customer.orders.show');
        Route::get('/tickets', [CustomerAccountController::class, 'upcomingTickets'])
            ->name('api.marketplace-client.customer.tickets');
        Route::get('/tickets/all', [CustomerAccountController::class, 'allTickets'])
            ->name('api.marketplace-client.customer.tickets.all');
        Route::get('/tickets/{ticket}', [CustomerAccountController::class, 'ticketDetail'])
            ->name('api.marketplace-client.customer.tickets.show');
        Route::get('/past-events', [CustomerAccountController::class, 'pastEvents'])
            ->name('api.marketplace-client.customer.past-events');
        Route::delete('/account', [CustomerAccountController::class, 'deleteAccount'])
            ->name('api.marketplace-client.customer.account.delete');

        // Dashboard Stats
        Route::get('/stats', [CustomerStatsController::class, 'index'])
            ->name('api.marketplace-client.customer.stats');
        Route::get('/stats/upcoming-events', [CustomerStatsController::class, 'upcomingEvents'])
            ->name('api.marketplace-client.customer.stats.upcoming-events');

        // Reviews
        Route::get('/reviews', [CustomerReviewsController::class, 'index'])
            ->name('api.marketplace-client.customer.reviews');
        Route::post('/reviews', [CustomerReviewsController::class, 'store'])
            ->name('api.marketplace-client.customer.reviews.store');
        Route::get('/reviews/events-to-review', [CustomerReviewsController::class, 'eventsToReview'])
            ->name('api.marketplace-client.customer.reviews.events-to-review');
        Route::get('/reviews/{review}', [CustomerReviewsController::class, 'show'])
            ->name('api.marketplace-client.customer.reviews.show');
        Route::put('/reviews/{review}', [CustomerReviewsController::class, 'update'])
            ->name('api.marketplace-client.customer.reviews.update');
        Route::delete('/reviews/{review}', [CustomerReviewsController::class, 'destroy'])
            ->name('api.marketplace-client.customer.reviews.destroy');

        // Watchlist
        Route::get('/watchlist', [CustomerWatchlistController::class, 'index'])
            ->name('api.marketplace-client.customer.watchlist');
        Route::post('/watchlist', [CustomerWatchlistController::class, 'store'])
            ->name('api.marketplace-client.customer.watchlist.store');
        Route::put('/watchlist/{event}', [CustomerWatchlistController::class, 'update'])
            ->name('api.marketplace-client.customer.watchlist.update');
        Route::delete('/watchlist/{event}', [CustomerWatchlistController::class, 'destroy'])
            ->name('api.marketplace-client.customer.watchlist.destroy');
        Route::get('/watchlist/check/{event}', [CustomerWatchlistController::class, 'check'])
            ->name('api.marketplace-client.customer.watchlist.check');

        // Rewards & Points
        Route::get('/rewards', [CustomerRewardsController::class, 'index'])
            ->name('api.marketplace-client.customer.rewards');
        Route::get('/rewards/history', [CustomerRewardsController::class, 'history'])
            ->name('api.marketplace-client.customer.rewards.history');
        Route::get('/rewards/badges', [CustomerRewardsController::class, 'badges'])
            ->name('api.marketplace-client.customer.rewards.badges');
        Route::get('/rewards/available', [CustomerRewardsController::class, 'availableRewards'])
            ->name('api.marketplace-client.customer.rewards.available');
        Route::post('/rewards/redeem', [CustomerRewardsController::class, 'redeem'])
            ->name('api.marketplace-client.customer.rewards.redeem');
        Route::get('/rewards/redemptions', [CustomerRewardsController::class, 'redemptions'])
            ->name('api.marketplace-client.customer.rewards.redemptions');

        // Notifications
        Route::get('/notifications', [CustomerNotificationsController::class, 'index'])
            ->name('api.marketplace-client.customer.notifications');
        Route::get('/notifications/unread-count', [CustomerNotificationsController::class, 'unreadCount'])
            ->name('api.marketplace-client.customer.notifications.unread-count');
        Route::post('/notifications/mark-read', [CustomerNotificationsController::class, 'markRead'])
            ->name('api.marketplace-client.customer.notifications.mark-read');
        Route::delete('/notifications/{notification}', [CustomerNotificationsController::class, 'destroy'])
            ->name('api.marketplace-client.customer.notifications.destroy');
        Route::get('/notifications/settings', [CustomerNotificationsController::class, 'settings'])
            ->name('api.marketplace-client.customer.notifications.settings');
        Route::put('/notifications/settings', [CustomerNotificationsController::class, 'updateSettings'])
            ->name('api.marketplace-client.customer.notifications.settings.update');

        // Referrals
        Route::get('/referrals', [CustomerReferralsController::class, 'index'])
            ->name('api.marketplace-client.customer.referrals');
        Route::post('/referrals/regenerate', [CustomerReferralsController::class, 'regenerateCode'])
            ->name('api.marketplace-client.customer.referrals.regenerate');
        Route::get('/referrals/leaderboard', [CustomerReferralsController::class, 'leaderboard'])
            ->name('api.marketplace-client.customer.referrals.leaderboard');
        Route::post('/referrals/claim', [CustomerReferralsController::class, 'claimRewards'])
            ->name('api.marketplace-client.customer.referrals.claim');

        // Ticket Transfers
        Route::post('/transfers', [CustomerTicketTransferController::class, 'initiate'])
            ->name('api.marketplace-client.customer.transfers.initiate');
        Route::get('/transfers/outgoing', [CustomerTicketTransferController::class, 'outgoing'])
            ->name('api.marketplace-client.customer.transfers.outgoing');
        Route::get('/transfers/incoming', [CustomerTicketTransferController::class, 'incoming'])
            ->name('api.marketplace-client.customer.transfers.incoming');
        Route::post('/transfers/{transfer}/cancel', [CustomerTicketTransferController::class, 'cancel'])
            ->name('api.marketplace-client.customer.transfers.cancel');
        Route::post('/transfers/{transfer}/accept', [CustomerTicketTransferController::class, 'accept'])
            ->name('api.marketplace-client.customer.transfers.accept');
        Route::post('/transfers/{transfer}/reject', [CustomerTicketTransferController::class, 'reject'])
            ->name('api.marketplace-client.customer.transfers.reject');

        // Refund Requests
        Route::get('/refunds', [CustomerRefundController::class, 'index'])
            ->name('api.marketplace-client.customer.refunds');
        Route::get('/refunds/reasons', [CustomerRefundController::class, 'reasons'])
            ->name('api.marketplace-client.customer.refunds.reasons');
        Route::post('/refunds/check-eligibility', [CustomerRefundController::class, 'checkEligibility'])
            ->name('api.marketplace-client.customer.refunds.check-eligibility');
        Route::post('/refunds', [CustomerRefundController::class, 'store'])
            ->name('api.marketplace-client.customer.refunds.store');
        Route::get('/refunds/{refund}', [CustomerRefundController::class, 'show'])
            ->name('api.marketplace-client.customer.refunds.show');
        Route::post('/refunds/{refund}/cancel', [CustomerRefundController::class, 'cancel'])
            ->name('api.marketplace-client.customer.refunds.cancel');

        // Gift Cards (authenticated - for viewing own gift cards)
        Route::get('/gift-cards', [CustomerGiftCardController::class, 'myGiftCards'])
            ->name('api.marketplace-client.customer.gift-cards');
        Route::post('/gift-cards/claim', [CustomerGiftCardController::class, 'claim'])
            ->name('api.marketplace-client.customer.gift-cards.claim');
        Route::get('/gift-cards/{giftCard}/transactions', [CustomerGiftCardController::class, 'transactions'])
            ->name('api.marketplace-client.customer.gift-cards.transactions');
    });

    // Public transfer acceptance (by token, no auth required)
    Route::post('/transfers/accept-by-token', [CustomerTicketTransferController::class, 'acceptByToken'])
        ->name('api.marketplace-client.customer.transfers.accept-by-token');

    // Public referral tracking (no auth required)
    Route::post('/referrals/track-click', [CustomerReferralsController::class, 'trackClick'])
        ->name('api.marketplace-client.customer.referrals.track-click');
    Route::get('/referrals/validate', [CustomerReferralsController::class, 'validateCode'])
        ->name('api.marketplace-client.customer.referrals.validate');

    // Cart (public, session-based)
    Route::get('/cart', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'index'])
        ->name('api.marketplace-client.customer.cart');
    Route::post('/cart/items', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'addItem'])
        ->name('api.marketplace-client.customer.cart.add');
    Route::post('/cart/items/with-seats', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'addItemWithSeats'])
        ->name('api.marketplace-client.customer.cart.add-with-seats');
    Route::put('/cart/items/{itemKey}', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'updateItem'])
        ->name('api.marketplace-client.customer.cart.update');
    Route::delete('/cart/items/{itemKey}', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'removeItem'])
        ->name('api.marketplace-client.customer.cart.remove');
    Route::delete('/cart/seats', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'releaseSeats'])
        ->name('api.marketplace-client.customer.cart.release-seats');
    Route::delete('/cart', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'clear'])
        ->name('api.marketplace-client.customer.cart.clear');
    Route::post('/cart/promo-code', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'applyPromoCode'])
        ->name('api.marketplace-client.customer.cart.promo-code');
    Route::delete('/cart/promo-code', [App\Http\Controllers\Api\MarketplaceClient\Customer\CartController::class, 'removePromoCode'])
        ->name('api.marketplace-client.customer.cart.promo-code.remove');

    // Checkout
    Route::get('/checkout/summary', [App\Http\Controllers\Api\MarketplaceClient\Customer\CheckoutController::class, 'summary'])
        ->name('api.marketplace-client.customer.checkout.summary');
    Route::post('/checkout', [App\Http\Controllers\Api\MarketplaceClient\Customer\CheckoutController::class, 'checkout'])
        ->name('api.marketplace-client.customer.checkout');

    // Gift Cards (public - for purchasing and checking balance)
    Route::get('/gift-cards/options', [CustomerGiftCardController::class, 'options'])
        ->name('api.marketplace-client.customer.gift-cards.options');
    Route::post('/gift-cards/purchase', [CustomerGiftCardController::class, 'purchase'])
        ->name('api.marketplace-client.customer.gift-cards.purchase');
    Route::post('/gift-cards/complete-purchase', [CustomerGiftCardController::class, 'completePurchase'])
        ->name('api.marketplace-client.customer.gift-cards.complete-purchase');
    Route::post('/gift-cards/check-balance', [CustomerGiftCardController::class, 'checkBalance'])
        ->name('api.marketplace-client.customer.gift-cards.check-balance');
    Route::post('/gift-cards/redeem', [CustomerGiftCardController::class, 'redeem'])
        ->name('api.marketplace-client.customer.gift-cards.redeem');
});

/*
|--------------------------------------------------------------------------
| Marketplace Admin API Routes
|--------------------------------------------------------------------------
|
| API endpoints for marketplace administrators. Admins can approve events,
| manage organizers, process payouts, and configure platform settings.
|
*/

use App\Http\Controllers\Api\MarketplaceClient\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\MarketplaceClient\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\MarketplaceClient\Admin\EventsController as AdminEventsController;
use App\Http\Controllers\Api\MarketplaceClient\Admin\OrganizersController as AdminOrganizersController;
use App\Http\Controllers\Api\MarketplaceClient\Admin\PayoutsController as AdminPayoutsController;
use App\Http\Controllers\Api\MarketplaceClient\Admin\SettingsController as AdminSettingsController;

Route::prefix('marketplace-client/admin')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    // Public routes (no admin auth)
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->name('api.marketplace-client.admin.login');
    Route::post('/forgot-password', [AdminAuthController::class, 'forgotPassword'])
        ->name('api.marketplace-client.admin.forgot-password');
    Route::post('/reset-password', [AdminAuthController::class, 'resetPassword'])
        ->name('api.marketplace-client.admin.reset-password');

    // Protected routes (require admin auth)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/logout', [AdminAuthController::class, 'logout'])
            ->name('api.marketplace-client.admin.logout');
        Route::get('/me', [AdminAuthController::class, 'me'])
            ->name('api.marketplace-client.admin.me');
        Route::put('/profile', [AdminAuthController::class, 'updateProfile'])
            ->name('api.marketplace-client.admin.profile.update');
        Route::put('/password', [AdminAuthController::class, 'updatePassword'])
            ->name('api.marketplace-client.admin.password.update');

        // Admin management
        Route::get('/admins', [AdminAuthController::class, 'listAdmins'])
            ->name('api.marketplace-client.admin.admins');
        Route::post('/admins', [AdminAuthController::class, 'createAdmin'])
            ->name('api.marketplace-client.admin.admins.create');
        Route::put('/admins/{admin}', [AdminAuthController::class, 'updateAdmin'])
            ->name('api.marketplace-client.admin.admins.update');
        Route::delete('/admins/{admin}', [AdminAuthController::class, 'deleteAdmin'])
            ->name('api.marketplace-client.admin.admins.delete');

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('api.marketplace-client.admin.dashboard');
        Route::get('/dashboard/timeline', [AdminDashboardController::class, 'salesTimeline'])
            ->name('api.marketplace-client.admin.dashboard.timeline');
        Route::get('/dashboard/activity', [AdminDashboardController::class, 'recentActivity'])
            ->name('api.marketplace-client.admin.dashboard.activity');
        Route::get('/dashboard/top-organizers', [AdminDashboardController::class, 'topOrganizers'])
            ->name('api.marketplace-client.admin.dashboard.top-organizers');
        Route::get('/dashboard/top-events', [AdminDashboardController::class, 'topEvents'])
            ->name('api.marketplace-client.admin.dashboard.top-events');

        // Events management
        Route::get('/events', [AdminEventsController::class, 'index'])
            ->name('api.marketplace-client.admin.events');
        Route::get('/events/pending', [AdminEventsController::class, 'pendingReview'])
            ->name('api.marketplace-client.admin.events.pending');
        Route::get('/events/{event}', [AdminEventsController::class, 'show'])
            ->name('api.marketplace-client.admin.events.show');
        Route::post('/events/{event}/approve', [AdminEventsController::class, 'approve'])
            ->name('api.marketplace-client.admin.events.approve');
        Route::post('/events/{event}/reject', [AdminEventsController::class, 'reject'])
            ->name('api.marketplace-client.admin.events.reject');
        Route::post('/events/{event}/feature', [AdminEventsController::class, 'toggleFeatured'])
            ->name('api.marketplace-client.admin.events.feature');
        Route::post('/events/{event}/suspend', [AdminEventsController::class, 'suspend'])
            ->name('api.marketplace-client.admin.events.suspend');

        // Organizers management
        Route::get('/organizers', [AdminOrganizersController::class, 'index'])
            ->name('api.marketplace-client.admin.organizers');
        Route::get('/organizers/pending', [AdminOrganizersController::class, 'pending'])
            ->name('api.marketplace-client.admin.organizers.pending');
        Route::get('/organizers/{organizer}', [AdminOrganizersController::class, 'show'])
            ->name('api.marketplace-client.admin.organizers.show');
        Route::post('/organizers/{organizer}/approve', [AdminOrganizersController::class, 'approve'])
            ->name('api.marketplace-client.admin.organizers.approve');
        Route::post('/organizers/{organizer}/verify', [AdminOrganizersController::class, 'verify'])
            ->name('api.marketplace-client.admin.organizers.verify');
        Route::post('/organizers/{organizer}/suspend', [AdminOrganizersController::class, 'suspend'])
            ->name('api.marketplace-client.admin.organizers.suspend');
        Route::post('/organizers/{organizer}/reactivate', [AdminOrganizersController::class, 'reactivate'])
            ->name('api.marketplace-client.admin.organizers.reactivate');
        Route::put('/organizers/{organizer}/commission', [AdminOrganizersController::class, 'updateCommission'])
            ->name('api.marketplace-client.admin.organizers.commission');
        Route::get('/organizers/{organizer}/events', [AdminOrganizersController::class, 'events'])
            ->name('api.marketplace-client.admin.organizers.events');
        Route::get('/organizers/{organizer}/transactions', [AdminOrganizersController::class, 'transactions'])
            ->name('api.marketplace-client.admin.organizers.transactions');

        // Payouts management
        Route::get('/payouts', [AdminPayoutsController::class, 'index'])
            ->name('api.marketplace-client.admin.payouts');
        Route::get('/payouts/pending', [AdminPayoutsController::class, 'pending'])
            ->name('api.marketplace-client.admin.payouts.pending');
        Route::get('/payouts/stats', [AdminPayoutsController::class, 'stats'])
            ->name('api.marketplace-client.admin.payouts.stats');
        Route::get('/payouts/{payout}', [AdminPayoutsController::class, 'show'])
            ->name('api.marketplace-client.admin.payouts.show');
        Route::post('/payouts/{payout}/approve', [AdminPayoutsController::class, 'approve'])
            ->name('api.marketplace-client.admin.payouts.approve');
        Route::post('/payouts/{payout}/processing', [AdminPayoutsController::class, 'markProcessing'])
            ->name('api.marketplace-client.admin.payouts.processing');
        Route::post('/payouts/{payout}/complete', [AdminPayoutsController::class, 'complete'])
            ->name('api.marketplace-client.admin.payouts.complete');
        Route::post('/payouts/{payout}/reject', [AdminPayoutsController::class, 'reject'])
            ->name('api.marketplace-client.admin.payouts.reject');

        // Settings
        Route::get('/settings', [AdminSettingsController::class, 'index'])
            ->name('api.marketplace-client.admin.settings');
        Route::put('/settings', [AdminSettingsController::class, 'update'])
            ->name('api.marketplace-client.admin.settings.update');
        Route::put('/settings/commission', [AdminSettingsController::class, 'updateCommission'])
            ->name('api.marketplace-client.admin.settings.commission');
        Route::put('/settings/custom', [AdminSettingsController::class, 'updateSettings'])
            ->name('api.marketplace-client.admin.settings.custom');
        Route::get('/settings/webhooks', [AdminSettingsController::class, 'webhooks'])
            ->name('api.marketplace-client.admin.settings.webhooks');
        Route::put('/settings/webhooks', [AdminSettingsController::class, 'updateWebhooks'])
            ->name('api.marketplace-client.admin.settings.webhooks.update');
        Route::post('/settings/webhooks/test', [AdminSettingsController::class, 'testWebhook'])
            ->name('api.marketplace-client.admin.settings.webhooks.test');
        Route::post('/settings/regenerate-api', [AdminSettingsController::class, 'regenerateApiCredentials'])
            ->name('api.marketplace-client.admin.settings.regenerate-api');
        Route::get('/settings/permissions', [AdminSettingsController::class, 'permissions'])
            ->name('api.marketplace-client.admin.settings.permissions');
    });
});

/*
|--------------------------------------------------------------------------
| Marketplace Events Public API Routes
|--------------------------------------------------------------------------
|
| Public API endpoints for browsing marketplace events (organizer-created events).
| These are separate from tenant events and allow customers to discover events.
|
*/

use App\Http\Controllers\Api\MarketplaceClient\MarketplaceEventsController as PublicMarketplaceEventsController;

Route::prefix('marketplace-client/marketplace-events')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    Route::get('/', [PublicMarketplaceEventsController::class, 'index'])
        ->name('api.marketplace-client.marketplace-events');
    Route::get('/featured', [PublicMarketplaceEventsController::class, 'featured'])
        ->name('api.marketplace-client.marketplace-events.featured');
    Route::get('/categories', [PublicMarketplaceEventsController::class, 'categories'])
        ->name('api.marketplace-client.marketplace-events.categories');
    Route::get('/cities', [PublicMarketplaceEventsController::class, 'cities'])
        ->name('api.marketplace-client.marketplace-events.cities');
    Route::get('/organizers', [PublicMarketplaceEventsController::class, 'organizers'])
        ->name('api.marketplace-client.marketplace-events.organizers');
    Route::get('/organizers/{identifier}', [PublicMarketplaceEventsController::class, 'organizer'])
        ->name('api.marketplace-client.marketplace-events.organizer');
    Route::get('/{identifier}', [PublicMarketplaceEventsController::class, 'show'])
        ->name('api.marketplace-client.marketplace-events.show');
    Route::get('/{event}/availability', [PublicMarketplaceEventsController::class, 'availability'])
        ->name('api.marketplace-client.marketplace-events.availability');
});

/*
|--------------------------------------------------------------------------
| Marketplace Client Locations API Routes
|--------------------------------------------------------------------------
|
| Public API endpoints for marketplace locations (regions, counties, cities)
|
*/

use App\Http\Controllers\Api\MarketplaceClient\LocationsController as MarketplaceLocationsController;
use App\Http\Controllers\Api\MarketplaceClient\CategoriesController as MarketplaceCategoriesController;

Route::prefix('marketplace-client/locations')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    Route::get('/stats', [MarketplaceLocationsController::class, 'stats'])
        ->name('api.marketplace-client.locations.stats');
    Route::get('/cities/featured', [MarketplaceLocationsController::class, 'featuredCities'])
        ->name('api.marketplace-client.locations.cities.featured');
    Route::get('/cities/alphabet', [MarketplaceLocationsController::class, 'alphabet'])
        ->name('api.marketplace-client.locations.cities.alphabet');
    Route::get('/cities', [MarketplaceLocationsController::class, 'cities'])
        ->name('api.marketplace-client.locations.cities');
    Route::get('/cities/{identifier}', [MarketplaceLocationsController::class, 'city'])
        ->name('api.marketplace-client.locations.city');
    Route::get('/regions', [MarketplaceLocationsController::class, 'regions'])
        ->name('api.marketplace-client.locations.regions');
    Route::get('/regions/{identifier}', [MarketplaceLocationsController::class, 'region'])
        ->name('api.marketplace-client.locations.region');
});

// Event Categories
Route::prefix('marketplace-client/event-categories')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    Route::get('/', [MarketplaceCategoriesController::class, 'index'])
        ->name('api.marketplace-client.event-categories');
    Route::get('/{slug}', [MarketplaceCategoriesController::class, 'show'])
        ->name('api.marketplace-client.event-categories.show');
});

// Event Genres
Route::get('/marketplace-client/event-genres', [MarketplaceEventsController::class, 'genres'])
    ->middleware(['throttle:120,1', 'marketplace.auth'])
    ->name('api.marketplace-client.event-genres');

// Venue Categories
use App\Http\Controllers\Api\MarketplaceClient\VenueCategoriesController as MarketplaceVenueCategoriesController;

Route::prefix('marketplace-client/venue-categories')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    Route::get('/', [MarketplaceVenueCategoriesController::class, 'index'])
        ->name('api.marketplace-client.venue-categories');
    Route::get('/{slug}', [MarketplaceVenueCategoriesController::class, 'show'])
        ->name('api.marketplace-client.venue-categories.show');
});

// Venues
use App\Http\Controllers\Api\MarketplaceClient\VenuesController as MarketplaceVenuesController;

Route::prefix('marketplace-client/venues')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    Route::get('/', [MarketplaceVenuesController::class, 'index'])
        ->name('api.marketplace-client.venues');
    Route::get('/featured', [MarketplaceVenuesController::class, 'featured'])
        ->name('api.marketplace-client.venues.featured');
    Route::get('/{slug}', [MarketplaceVenuesController::class, 'show'])
        ->name('api.marketplace-client.venues.show');
});

// Artists
use App\Http\Controllers\Api\MarketplaceClient\ArtistsController as MarketplaceArtistsController;

Route::prefix('marketplace-client/artists')->middleware(['throttle:120,1', 'marketplace.auth'])->group(function () {
    Route::get('/', [MarketplaceArtistsController::class, 'index'])
        ->name('api.marketplace-client.artists');
    Route::get('/featured', [MarketplaceArtistsController::class, 'featured'])
        ->name('api.marketplace-client.artists.featured');
    Route::get('/trending', [MarketplaceArtistsController::class, 'trending'])
        ->name('api.marketplace-client.artists.trending');
    Route::get('/genre-counts', [MarketplaceArtistsController::class, 'genreCounts'])
        ->name('api.marketplace-client.artists.genre-counts');
    Route::get('/alphabet', [MarketplaceArtistsController::class, 'alphabet'])
        ->name('api.marketplace-client.artists.alphabet');
    Route::get('/{slug}', [MarketplaceArtistsController::class, 'show'])
        ->name('api.marketplace-client.artists.show');
    Route::get('/{slug}/events', [MarketplaceArtistsController::class, 'events'])
        ->name('api.marketplace-client.artists.events');
});

/*
|--------------------------------------------------------------------------
| Platform Analytics API Routes
|--------------------------------------------------------------------------
|
| REST API endpoints for platform analytics, attribution, churn prediction,
| and customer data management
|
*/

Route::prefix('v1/analytics')->middleware(['throttle:api', 'api.key'])->group(function () {
    // Dashboard and Overview
    Route::get('/dashboard', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'dashboard'])
        ->name('api.analytics.dashboard');

    Route::get('/funnel', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'funnel'])
        ->name('api.analytics.funnel');

    Route::get('/segments', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'segments'])
        ->name('api.analytics.segments');

    Route::get('/cohorts', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'cohorts'])
        ->name('api.analytics.cohorts');

    Route::get('/traffic-sources', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'trafficSources'])
        ->name('api.analytics.traffic-sources');

    Route::get('/geography', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'geography'])
        ->name('api.analytics.geography');

    Route::get('/top-customers', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'topCustomers'])
        ->name('api.analytics.top-customers');

    // Attribution
    Route::get('/attribution/models', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'attributionModels'])
        ->name('api.analytics.attribution.models');

    Route::get('/attribution/comparison', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'attributionComparison'])
        ->name('api.analytics.attribution.comparison');

    Route::get('/attribution/channels', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'channelAttribution'])
        ->name('api.analytics.attribution.channels');

    // Churn Prediction
    Route::get('/churn/dashboard', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'churnDashboard'])
        ->name('api.analytics.churn.dashboard');

    Route::get('/churn/at-risk', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'atRiskCustomers'])
        ->name('api.analytics.churn.at-risk');

    Route::get('/churn/by-segment', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'churnBySegment'])
        ->name('api.analytics.churn.by-segment');

    Route::get('/churn/cohort-analysis', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'cohortChurnAnalysis'])
        ->name('api.analytics.churn.cohort-analysis');

    // Duplicate Detection
    Route::get('/duplicates/stats', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'duplicateStats'])
        ->name('api.analytics.duplicates.stats');

    Route::get('/duplicates', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'duplicates'])
        ->name('api.analytics.duplicates');

    // Customer-specific endpoints
    Route::get('/customers/{customerId}/profile', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'customerProfile'])
        ->name('api.analytics.customer.profile');

    Route::get('/customers/{customerId}/journey', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'customerJourney'])
        ->name('api.analytics.customer.journey');

    Route::get('/customers/{customerId}/churn-prediction', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'predictCustomerChurn'])
        ->name('api.analytics.customer.churn-prediction');

    Route::get('/customers/{customerId}/duplicates', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'customerDuplicates'])
        ->name('api.analytics.customer.duplicates');

    Route::get('/customers/{customerId}/ltv-prediction', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'predictCustomerLtv'])
        ->name('api.analytics.customer.ltv-prediction');

    // LTV Prediction
    Route::get('/ltv/dashboard', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'ltvDashboard'])
        ->name('api.analytics.ltv.dashboard');

    Route::get('/ltv/high-potential', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'highPotentialCustomers'])
        ->name('api.analytics.ltv.high-potential');

    Route::get('/ltv/by-segment', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'ltvBySegment'])
        ->name('api.analytics.ltv.by-segment');

    Route::get('/ltv/by-cohort', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'ltvByCohort'])
        ->name('api.analytics.ltv.by-cohort');

    Route::get('/ltv/tiers', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'ltvTiers'])
        ->name('api.analytics.ltv.tiers');

    // Export - with stricter rate limiting (60 exports per hour)
    Route::get('/export', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'export'])
        ->middleware('throttle:60,60')
        ->name('api.analytics.export');

    Route::get('/export/options', [App\Http\Controllers\Api\Platform\AnalyticsController::class, 'exportOptions'])
        ->name('api.analytics.export.options');
});

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Administrative endpoints for system monitoring and management
| Note: Add authentication middleware as needed
|
*/

Route::prefix('admin')->middleware(['throttle:api', 'admin.auth'])->group(function () {
    // System Health
    Route::prefix('health')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\SystemHealthController::class, 'index'])
            ->name('api.admin.health.index');

        Route::get('/history', [App\Http\Controllers\Admin\SystemHealthController::class, 'history'])
            ->name('api.admin.health.history');

        Route::get('/{service}', [App\Http\Controllers\Admin\SystemHealthController::class, 'service'])
            ->name('api.admin.health.service');
    });

    // Alert Management
    Route::prefix('alerts')->group(function () {
        Route::get('/config', [App\Http\Controllers\Admin\AlertController::class, 'config'])
            ->name('api.admin.alerts.config');

        Route::post('/test', [App\Http\Controllers\Admin\AlertController::class, 'test'])
            ->name('api.admin.alerts.test');
    });

    // Audit Logs
    Route::prefix('audit')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\AuditController::class, 'index'])
            ->name('api.admin.audit.index');

        Route::get('/stats', [App\Http\Controllers\Admin\AuditController::class, 'stats'])
            ->name('api.admin.audit.stats');

        Route::get('/export', [App\Http\Controllers\Admin\AuditController::class, 'export'])
            ->name('api.admin.audit.export');
    });

    // API Usage Monitoring
    Route::prefix('api-usage')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ApiUsageController::class, 'index'])
            ->name('api.admin.api-usage.index');

        Route::get('/by-tenant', [App\Http\Controllers\Admin\ApiUsageController::class, 'byTenant'])
            ->name('api.admin.api-usage.by-tenant');

        Route::get('/rate-limit-violations', [App\Http\Controllers\Admin\ApiUsageController::class, 'rateLimitViolations'])
            ->name('api.admin.api-usage.rate-limit-violations');

        Route::get('/{keyId}', [App\Http\Controllers\Admin\ApiUsageController::class, 'show'])
            ->name('api.admin.api-usage.show');
    });
});


/*
|--------------------------------------------------------------------------
| Organizer Event Analytics API Routes
|--------------------------------------------------------------------------
|
| Analytics endpoints for event organizers to track performance,
| milestones, traffic sources, and buyer journeys
|
*/

Route::prefix('organizer/events/{event}')->middleware(['throttle:120,1', 'auth:sanctum'])->group(function () {
    // Dashboard & Overview
    Route::get('/analytics', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'dashboard'])
        ->name('api.organizer.event.analytics');

    Route::get('/analytics/overview', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'overview'])
        ->name('api.organizer.event.analytics.overview');

    Route::get('/analytics/chart', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'chartData'])
        ->name('api.organizer.event.analytics.chart');

    // Real-time & Live
    Route::get('/analytics/realtime', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'realtime'])
        ->name('api.organizer.event.analytics.realtime');

    Route::get('/analytics/globe', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'globeData'])
        ->name('api.organizer.event.analytics.globe');

    // Breakdowns
    Route::get('/analytics/tickets', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'ticketPerformance'])
        ->name('api.organizer.event.analytics.tickets');

    Route::get('/analytics/traffic', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'trafficSources'])
        ->name('api.organizer.event.analytics.traffic');

    Route::get('/analytics/locations', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'topLocations'])
        ->name('api.organizer.event.analytics.locations');

    Route::get('/analytics/funnel', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'funnel'])
        ->name('api.organizer.event.analytics.funnel');

    // Sales & Journeys
    Route::get('/analytics/sales', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'recentSales'])
        ->name('api.organizer.event.analytics.sales');

    Route::get('/analytics/journey/{order}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'buyerJourney'])
        ->name('api.organizer.event.analytics.journey');

    // Campaign Performance
    Route::get('/analytics/campaigns', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'campaignComparison'])
        ->name('api.organizer.event.analytics.campaigns');

    // Milestones CRUD
    Route::get('/milestones', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'milestones'])
        ->name('api.organizer.event.milestones');

    Route::post('/milestones', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'createMilestone'])
        ->name('api.organizer.event.milestones.create');

    Route::get('/milestones/{milestone}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'milestoneDetails'])
        ->name('api.organizer.event.milestones.show');

    Route::put('/milestones/{milestone}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'updateMilestone'])
        ->name('api.organizer.event.milestones.update');

    Route::delete('/milestones/{milestone}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'deleteMilestone'])
        ->name('api.organizer.event.milestones.delete');

    // Admin actions
    Route::post('/analytics/recalculate', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'recalculate'])
        ->name('api.organizer.event.analytics.recalculate');

    // Export
    Route::get('/analytics/export/csv', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'exportCsv'])
        ->name('api.organizer.event.analytics.export.csv');

    Route::get('/analytics/export/pdf', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'exportPdf'])
        ->name('api.organizer.event.analytics.export.pdf');

    Route::get('/analytics/export/sales', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'exportSales'])
        ->name('api.organizer.event.analytics.export.sales');

    // Goals CRUD
    Route::get('/goals', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'goals'])
        ->name('api.organizer.event.goals');

    Route::post('/goals', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'createGoal'])
        ->name('api.organizer.event.goals.create');

    Route::put('/goals/{goal}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'updateGoal'])
        ->name('api.organizer.event.goals.update');

    Route::delete('/goals/{goal}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'deleteGoal'])
        ->name('api.organizer.event.goals.delete');

    // Report Schedules
    Route::get('/report-schedules', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'reportSchedules'])
        ->name('api.organizer.event.report-schedules');

    Route::post('/report-schedules', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'createReportSchedule'])
        ->name('api.organizer.event.report-schedules.create');

    Route::put('/report-schedules/{schedule}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'updateReportSchedule'])
        ->name('api.organizer.event.report-schedules.update');

    Route::delete('/report-schedules/{schedule}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'deleteReportSchedule'])
        ->name('api.organizer.event.report-schedules.delete');

    Route::post('/report-schedules/{schedule}/test', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'sendTestReport'])
        ->name('api.organizer.event.report-schedules.test');
});

// Download route (outside event group)
Route::get('organizer/analytics/download/{filename}', [App\Http\Controllers\Api\OrganizerEventAnalyticsController::class, 'download'])
    ->middleware(['throttle:60,1', 'auth:sanctum'])
    ->name('api.organizer.analytics.download');
