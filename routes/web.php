<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\ArtistPublicController;
use App\Http\Controllers\Public\VenueController as PublicVenueController;
use App\Http\Controllers\Public\LocationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\ContractSigningController;
use App\Http\Controllers\MicroserviceMarketplaceController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\MarketplaceStripeWebhookController;
use App\Http\Controllers\TenantPaymentWebhookController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\Public\DocsController as PublicDocsController;
use App\Http\Controllers\Tenant\PreviewProxyController;
use App\Http\Controllers\Admin\TicketCustomizerController;
use App\Http\Controllers\Tenant\TicketCustomizerController as TenantTicketCustomizerController;
use App\Http\Controllers\Marketplace\TicketCustomizerController as MarketplaceTicketCustomizerController;
use App\Http\Controllers\ApplePayVerificationController;
use App\Http\Controllers\Seating\SeatingEmbedController;
use App\Http\Controllers\NewsletterTrackingController;

Route::pattern('locale', 'en|ro|de|fr|es');

// Newsletter open + click tracking. Token is self-signed (HMAC over
// APP_KEY) so no auth / no CSRF needed. Public edge; must be reachable
// from any inbox / forwarded copy. See NewsletterTrackingController.
Route::get('/newsletter/click/{token}', [NewsletterTrackingController::class, 'click'])
    ->name('newsletter.click')
    ->where('token', '[A-Za-z0-9._-]+');
Route::get('/newsletter/open/{token}.gif', [NewsletterTrackingController::class, 'open'])
    ->name('newsletter.open')
    ->where('token', '[A-Za-z0-9._-]+');

// Flexible Payments — public surface (pay-links + customer portal). JSON-first;
// the processor's hosted page handles card entry via the returned redirect_url.
// Throttled: these are unauthenticated, token-gated endpoints.
Route::middleware('throttle:30,1')->group(function () {
Route::prefix('pay')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\FlexiblePaymentController::class, 'showLink'])
        ->name('flex.pay.show')->where('token', '[A-Za-z0-9]+');
    Route::post('/{token}', [\App\Http\Controllers\FlexiblePaymentController::class, 'payLink'])
        ->name('flex.pay.start')->where('token', '[A-Za-z0-9]+');
    Route::get('/{token}/confirm', [\App\Http\Controllers\FlexiblePaymentController::class, 'confirmLink'])
        ->name('flex.pay.confirm')->where('token', '[A-Za-z0-9]+');
});
// Public marketing page presenting the flexible-payment methods.
Route::get('/plati-flexibile', fn () => view('public.flexible-payments'))->name('flex.info');

Route::prefix('installments')->group(function () {
    // Token-gated (unguessable per-agreement token) so portal/payoff can't be
    // reached by iterating sequential ids.
    Route::get('/agreements/{token}', [\App\Http\Controllers\FlexiblePaymentController::class, 'portal'])
        ->name('flex.portal')->where('token', '[A-Za-z0-9]+');
    Route::post('/agreements/{token}/payoff', [\App\Http\Controllers\FlexiblePaymentController::class, 'earlyPayoff'])
        ->name('flex.payoff')->where('token', '[A-Za-z0-9]+');
    Route::post('/orders/{order}/delegated', [\App\Http\Controllers\FlexiblePaymentController::class, 'createDelegated'])
        ->name('flex.delegated.create')->whereNumber('order');
});
}); // end throttle group

// Define login route that redirects to Filament admin login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// DEBUG: Check auth status (NO auth middleware - shows if user is logged in)
Route::middleware(['web'])->get('/check-auth', function() {
    return response()->json([
        'is_authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'user_email' => auth()->user()?->email,
        'user_role' => auth()->user()?->role,
        'session_id' => session()->getId(),
        'session_driver' => config('session.driver'),
        'has_session_cookie' => request()->hasCookie(config('session.cookie')),
        'session_data' => session()->all(),
    ]);
})->name('check.auth');

// DEBUG: Test route to bypass Filament and test middleware
Route::middleware(['web', 'auth'])->get('/test-admin-access', function() {
    $user = auth()->user();
    return response()->json([
        'success' => true,
        'message' => 'Middleware auth passed successfully!',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ],
        'can_access_panel' => $user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')),
    ]);
})->name('test.admin.access');

// Global Search API (requires authentication) - outside Filament panels to avoid route conflicts
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/api/search/admin', [GlobalSearchController::class, 'search'])->name('admin.api.global-search');
    Route::get('/api/search/tenant/{tenant}', [GlobalSearchController::class, 'searchTenant'])->name('tenant.api.global-search');

    // E6 — Order dispute evidence PDF (admin only). Generates a self-
    // contained audit PDF with sessions/events/tickets timeline.
    Route::get('/admin/orders/{order}/dispute-evidence', [\App\Http\Controllers\Admin\OrderDisputeEvidenceController::class, 'download'])
        ->name('admin.orders.dispute-evidence');
});

// Marketplace Search API (uses marketplace_admin guard)
Route::middleware(['web', 'auth:marketplace_admin'])->group(function () {
    Route::get('/api/search/marketplace/{marketplace}', [GlobalSearchController::class, 'searchMarketplace'])->name('marketplace.api.global-search');

    // E6 — same evidence PDF, available to marketplace admins for orders
    // belonging to their own marketplace_client.
    Route::get('/marketplace/orders/{order}/dispute-evidence', function (\App\Models\Order $order) {
        $admin = auth('marketplace_admin')->user();
        if (!$admin || $order->marketplace_client_id !== $admin->marketplace_client_id) {
            abort(403);
        }
        return app(\App\Http\Controllers\Admin\OrderDisputeEvidenceController::class)->download($order);
    })->name('marketplace.orders.dispute-evidence');

    // Login-as-organizer: generates a 30-min Sanctum token + redirects to the
    // marketplace's organizer panel with ?_admin_token=… (auth.js picks it
    // up). Used by EventResource venue-config redirect placeholder + the
    // OrganizerResource "Login as Organizer" header action.
    Route::get('/marketplace/organizers/{organizerId}/login-as', [
        \App\Http\Controllers\Marketplace\OrganizerImpersonationController::class,
        'loginAs',
    ])->name('marketplace.organizers.login-as');

    // Print invitations as tiled PDF (N per page) with configurable paper
    // size + bleed. GET without ?paper=... shows the config form; GET with
    // params generates + downloads the PDF. Auth scope enforced against
    // the marketplace admin's marketplace_client_id.
    Route::get('/marketplace/events/{event}/print-invitations', [
        \App\Http\Controllers\Marketplace\PrintInvitationsController::class,
        'index',
    ])->name('marketplace.events.print-invitations');
});

// Marketplace Client Switcher (for super-admins).
//
// Writes the guard session key + flags by hand instead of calling
// auth('marketplace_admin')->login() — login() calls Session::migrate(true)
// which regenerates the session ID and destroys the old session row in DB.
// In practice the new session cookie wasn't getting picked up by the browser
// reliably, so the next request would land on a session that no longer
// existed — Laravel created an empty session, the panel middleware saw no
// super_admin_marketplace_client_id, and fell back to the first active
// marketplace. Keeping the session ID stable avoids the whole Set-Cookie race.
Route::middleware(['web'])->get('/marketplace/switch-client/{clientId}', function ($clientId) {
    if (!auth('web')->check() || !auth('web')->user()->isSuperAdmin()) {
        abort(403, 'Unauthorized');
    }

    $client = \App\Models\MarketplaceClient::where('status', 'active')->find($clientId);
    if (!$client) {
        abort(404, 'Marketplace client not found');
    }

    $user = auth('web')->user();

    $admin = \App\Models\MarketplaceAdmin::where('marketplace_client_id', $clientId)
        ->where(function ($q) use ($user) {
            $q->where('email', $user->email)->orWhere('role', 'super_admin');
        })
        ->first();

    if (!$admin) {
        $admin = \App\Models\MarketplaceAdmin::create([
            'marketplace_client_id' => $clientId,
            'email' => $user->email,
            'password' => bcrypt(uniqid('system_', true)),
            'name' => $user->name . ' (System)',
            'role' => 'super_admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    switchSuperAdminToMarketplace($admin, (int) $clientId, $user);

    return redirect('/marketplace');
})->name('marketplace.switch-client');

if (!function_exists('switchSuperAdminToMarketplace')) {
    /**
     * Switch the marketplace_admin guard to $admin without regenerating the
     * session ID. Updates the AuthenticateSession password-hash sentinel for
     * both the panel guard and the global default so the next request doesn't
     * get flushed by a hash mismatch (which would also drop us back to the
     * first active marketplace). Shared by the route above + the admin-side
     * "Login to marketplace" Filament actions on MarketplaceClientResource.
     */
    function switchSuperAdminToMarketplace(
        \App\Models\MarketplaceAdmin $admin,
        int $clientId,
        \App\Models\User $superAdmin
    ): void {
        $guard = auth('marketplace_admin');
        $guardSessionKey = $guard instanceof \Illuminate\Auth\SessionGuard
            ? $guard->getName()
            : 'login_marketplace_admin_' . sha1(\Illuminate\Auth\SessionGuard::class);

        session()->put($guardSessionKey, $admin->getAuthIdentifier());

        // AuthenticateSession flushes the session when the new user's password
        // hash doesn't match the value stored under password_hash_<guard>.
        // Pre-populate it for both the panel guard and the global default.
        $hashForCookie = $admin->getAuthPassword();
        try {
            if ($guard instanceof \Illuminate\Auth\SessionGuard && method_exists($guard, 'hashPasswordForCookie')) {
                $hashForCookie = $guard->hashPasswordForCookie($admin->getAuthPassword());
            }
        } catch (\Throwable $e) {
            // older guards don't have hashPasswordForCookie — fall through to raw hash
        }
        session()->put('password_hash_marketplace_admin', $hashForCookie);
        session()->put('password_hash_web', $hashForCookie);

        session([
            'super_admin_marketplace_client_id' => $clientId,
            'marketplace_is_super_admin' => true,
            'marketplace_super_admin_user_id' => $superAdmin->id,
        ]);

        $guard->setUser($admin);
        session()->save();
    }
}

// DEBUG: Test session and cookies
Route::middleware(['web'])->get('/test-session', function() {
    session(['test_key' => 'test_value_' . time()]);
    return response()->json([
        'session_id' => session()->getId(),
        'test_key' => session('test_key'),
        'all_session' => session()->all(),
        'has_cookie' => request()->hasCookie(config('session.cookie')),
        'cookie_name' => config('session.cookie'),
        'session_driver' => config('session.driver'),
    ])->withCookie(cookie('manual-test', 'manual-value', 120));
});

Route::middleware(['web'])->get('/test-session-read', function() {
    return response()->json([
        'session_id' => session()->getId(),
        'test_key' => session('test_key'),
        'all_session' => session()->all(),
        'has_cookie' => request()->hasCookie(config('session.cookie')),
        'manual_test_cookie' => request()->cookie('manual-test'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Microservices Marketplace & Stripe Routes
|--------------------------------------------------------------------------
*/

// Stripe Webhook for TENANT subscriptions / microservice purchases.
// Tenant-only: uses the global Setting.stripe_webhook_secret + Tenant lookup
// via session metadata. NOT used for marketplace customer ticket orders —
// see /webhooks/marketplace-stripe/{id} below for that.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Stripe Webhook for MARKETPLACE customer orders (per-marketplace endpoint).
// Each marketplace registers its own URL with its own client id baked in:
//   bilete.online → https://core.tixello.com/webhooks/marketplace-stripe/3
//   ambilet.ro    → https://core.tixello.com/webhooks/marketplace-stripe/1
// Stripe issues a unique signing secret per registered endpoint; that secret
// is stored in the marketplace_client_microservices pivot settings for the
// `payment-stripe` microservice (test_webhook_secret / live_webhook_secret).
Route::post('/webhooks/marketplace-stripe/{marketplaceClientId}', [MarketplaceStripeWebhookController::class, 'handle'])
    ->name('webhooks.marketplace-stripe')
    ->where('marketplaceClientId', '[0-9]+')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Tenant Payment Processor Webhooks
Route::post('/webhooks/tenant-payment/{tenant}/{processor}', [TenantPaymentWebhookController::class, 'handle'])
    ->name('webhooks.tenant-payment')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// WhatsApp Cloud API Webhook (Meta/Facebook)
Route::get('/webhooks/whatsapp-cloud', [\App\Http\Controllers\Webhooks\WhatsAppCloudWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp-cloud.verify');
Route::post('/webhooks/whatsapp-cloud', [\App\Http\Controllers\Webhooks\WhatsAppCloudWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp-cloud.handle')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Microservices Store Routes
Route::prefix('store')->middleware(['web'])->group(function () {
    // Public pages
    Route::get('/', [MicroserviceMarketplaceController::class, 'index'])
        ->name('store.index');

    Route::get('/microservice/{slug}', [MicroserviceMarketplaceController::class, 'show'])
        ->name('store.show');

    // Cart management (session-based)
    Route::post('/cart/add', [MicroserviceMarketplaceController::class, 'addToCart'])
        ->name('store.cart.add');

    Route::post('/cart/remove', [MicroserviceMarketplaceController::class, 'removeFromCart'])
        ->name('store.cart.remove');

    Route::get('/cart', [MicroserviceMarketplaceController::class, 'cart'])
        ->name('store.cart');

    // Checkout (requires auth + tenant role)
    Route::middleware(['auth'])->group(function () {
        Route::get('/checkout', [MicroserviceMarketplaceController::class, 'checkoutPage'])
            ->name('store.checkout');

        Route::post('/checkout/process', [MicroserviceMarketplaceController::class, 'processCheckout'])
            ->name('store.checkout.process');
    });

    Route::get('/payment/success', [MicroserviceMarketplaceController::class, 'success'])
        ->name('store.payment.success');

    Route::get('/payment/cancel', [MicroserviceMarketplaceController::class, 'cancel'])
        ->name('store.payment.cancel');
});

// Onboarding routes (no locale prefix needed for registration)
Route::get('/register', [OnboardingController::class, 'index'])->name('onboarding.index');
Route::post('/register/step-1', [OnboardingController::class, 'storeStepOne'])->name('onboarding.step1');
Route::post('/register/step-2', [OnboardingController::class, 'storeStepTwo'])->name('onboarding.step2');
Route::post('/register/step-3', [OnboardingController::class, 'storeStepThree'])->name('onboarding.step3');
Route::post('/register/step-4', [OnboardingController::class, 'storeStepFour'])->name('onboarding.step4');
Route::post('/register/step-5', [OnboardingController::class, 'storeStepFive'])->name('onboarding.step5');
Route::post('/register/lookup-cui', [OnboardingController::class, 'lookupCui'])->name('onboarding.lookup-cui');
Route::post('/register/check-email', [OnboardingController::class, 'checkEmail'])->name('onboarding.check-email');
Route::post('/register/check-domain', [OnboardingController::class, 'checkDomain'])->name('onboarding.check-domain');
Route::post('/register/check-subdomain', [OnboardingController::class, 'checkSubdomain'])->name('onboarding.check-subdomain');
Route::post('/register/search-artists', [OnboardingController::class, 'searchArtists'])->name('onboarding.search-artists');
Route::get('/register/api/cities/{country}/{state}', [OnboardingController::class, 'getCities'])->name('onboarding.cities');
Route::get('/register/verify/{token}', [OnboardingController::class, 'verify'])->name('onboarding.verify');
// Redirect /verify/{token} to /register/verify/{token}
Route::get('/verify/{token}', function ($token) {
    return redirect()->route('onboarding.verify', ['token' => $token]);
});

// Logout routes (support both GET and POST)
Route::get('/admin/logout', function () {
    \Illuminate\Support\Facades\Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('filament.admin.logout.get');

// Legal pages
Route::get('/termeni-si-conditii', function () {
    return view('public.legal.terms');
})->name('legal.terms');

Route::get('/politica-confidentialitate', function () {
    return view('public.legal.privacy');
})->name('legal.privacy');

// Apple Pay Domain Verification
Route::get('/.well-known/apple-developer-merchantid-domain-association', ApplePayVerificationController::class)
    ->name('apple-pay.verification');

// Marketplace Document Generation API
Route::middleware(['web', 'auth'])->prefix('marketplace/api')->group(function () {
    Route::post('/events/{eventId}/generate-document', function (int $eventId) {
        $event = \App\Models\Event::findOrFail($eventId);
        $templateId = request()->input('template_id');
        $template = \App\Models\MarketplaceTaxTemplate::findOrFail($templateId);

        try {
            $document = \App\Models\EventGeneratedDocument::generateDocument(
                event: $event,
                template: $template,
                generatedBy: auth()->user()
            );
            return response()->json(['success' => true, 'message' => "Document generat: {$document->filename}"]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    })->name('marketplace.events.generate-document');
});

// Admin Demo Data Management Routes
Route::middleware(['web', 'auth'])->prefix('admin/api')->group(function () {
    Route::post('/tenants/{tenantId}/demo', [\App\Http\Controllers\Admin\DemoDataController::class, 'seed'])->name('admin.tenants.demo.seed');
    Route::delete('/tenants/{tenantId}/demo', [\App\Http\Controllers\Admin\DemoDataController::class, 'cleanup'])->name('admin.tenants.demo.cleanup');
});

// Demo tenant session switching (for tenant panel)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/tenant/demo/enter/{tenantId}', function (int $tenantId) {
        $tenant = \App\Models\Tenant::findOrFail($tenantId);
        $user = auth()->user();

        // Allow super-admin or tenant owner
        $isSuperAdmin = $user->role === 'super-admin';
        $isOwner = $user->tenant_id === $tenantId || ($user->ownedTenant?->id === $tenantId);

        if (!$isSuperAdmin && !$isOwner) {
            abort(403);
        }

        if (!$tenant->demo_shadow_id) {
            return redirect('/tenant')->with('error', 'No demo data on this tenant.');
        }

        session([
            'demo_tenant_id' => $tenant->demo_shadow_id,
            'demo_parent_tenant_id' => $tenant->id,
            'demo_parent_tenant_name' => $tenant->public_name ?? $tenant->name,
        ]);

        return redirect('/tenant')->with('success', 'Demo mode activated.');
    })->name('tenant.demo.enter');

    Route::get('/tenant/demo/exit', function () {
        $parentId = session('demo_parent_tenant_id');
        session()->forget(['demo_tenant_id', 'demo_parent_tenant_id', 'demo_parent_tenant_name']);

        if ($parentId) {
            return redirect("/admin/tenants/{$parentId}/edit?tab=domains-deployment%3A%3Adata%3A%3Atab");
        }
        return redirect('/admin');
    })->name('tenant.demo.exit');
});

// Admin Domain Management Routes
Route::middleware(['web'])->prefix('admin')->group(function () {
    Route::post('/tenants/{tenantId}/domains', [DomainController::class, 'store'])->name('admin.tenants.domains.store');
    Route::post('/domains/{domainId}/toggle-active', [DomainController::class, 'toggleActive'])->name('admin.domains.toggle-active');
    Route::post('/domains/{domainId}/toggle-confirmed', [DomainController::class, 'toggleConfirmed'])->name('admin.domains.toggle-confirmed');
    Route::post('/domains/{domainId}/toggle-suspended', [DomainController::class, 'toggleSuspended'])->name('admin.domains.toggle-suspended');
    Route::delete('/domains/{domainId}', [DomainController::class, 'destroy'])->name('admin.domains.destroy');
    Route::post('/domains/{domainId}/verify', [DomainController::class, 'verify'])->name('admin.domains.verify');
    Route::get('/tenants/{tenantId}/domains/{domain}/login-as-admin', [DomainController::class, 'loginAsAdmin'])->name('tenant.login-as-admin');
});

// Admin Package Management Routes
Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    Route::get('/tenants/{tenant}/domains/{domain}/package/download', [PackageController::class, 'download'])
        ->name('admin.tenant.package.download');
    Route::get('/tenants/{tenant}/domains/{domain}/package/download-zip', [PackageController::class, 'downloadZip'])
        ->name('admin.tenant.package.download-zip');
    Route::post('/tenants/{tenant}/domains/{domain}/package/generate', [PackageController::class, 'generate'])
        ->name('admin.tenant.package.generate');
    Route::post('/tenants/{tenant}/domains/{domain}/package/regenerate', [PackageController::class, 'regenerate'])
        ->name('admin.tenant.package.regenerate');
    Route::get('/tenants/{tenant}/domains/{domain}/package/instructions', [PackageController::class, 'instructions'])
        ->name('admin.tenant.package.instructions');
});

// Admin Contract Management Routes
Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    Route::get('/tenants/{tenant}/contract/download', [ContractController::class, 'download'])
        ->name('admin.tenant.contract.download');
    Route::get('/tenants/{tenant}/contract/preview', [ContractController::class, 'preview'])
        ->name('admin.tenant.contract.preview');
    Route::get('/contract-templates/{template}/preview', [ContractController::class, 'previewTemplate'])
        ->name('admin.contract-template.preview');
});

// Admin Ticket Customizer (Visual Editor) Routes
Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    Route::get('/ticket-customizer/{template}', [TicketCustomizerController::class, 'edit'])
        ->name('admin.ticket-customizer.edit');
    Route::put('/ticket-customizer/{template}', [TicketCustomizerController::class, 'update'])
        ->name('admin.ticket-customizer.update');
});

// Tenant Ticket Customizer (Visual Editor) Routes
Route::middleware(['web', 'auth'])->prefix('tenant')->group(function () {
    Route::get('/ticket-customizer/{template}/editor', [TenantTicketCustomizerController::class, 'edit'])
        ->name('tenant.ticket-customizer.edit');
    Route::put('/ticket-customizer/{template}/editor', [TenantTicketCustomizerController::class, 'update'])
        ->name('tenant.ticket-customizer.update');
});

// Marketplace Ticket Customizer (Visual Editor) Routes
Route::middleware(['web', 'auth:marketplace_admin'])->prefix('marketplace')->group(function () {
    // Real-time presence on event edit page (concurrent-editor warning)
    Route::post('/events/{eventId}/edit-presence', [\App\Http\Controllers\Marketplace\EditPresenceController::class, 'heartbeat'])
        ->where('eventId', '[0-9]+')
        ->name('marketplace.events.edit-presence');
    Route::post('/events/{eventId}/edit-presence/leave', [\App\Http\Controllers\Marketplace\EditPresenceController::class, 'leave'])
        ->where('eventId', '[0-9]+')
        ->name('marketplace.events.edit-presence.leave');

    Route::get('/ticket-customizer/{template}/editor', [MarketplaceTicketCustomizerController::class, 'edit'])
        ->name('marketplace.ticket-customizer.edit');
    Route::put('/ticket-customizer/{template}/editor', [MarketplaceTicketCustomizerController::class, 'update'])
        ->name('marketplace.ticket-customizer.update');

    Route::get('/tickets/{ticket}/download-pdf', function (\App\Models\Ticket $ticket) {
        $ticket->load(['order.marketplaceClient', 'marketplaceEvent', 'marketplaceTicketType']);
        $event = $ticket->resolveEvent();

        // Resolve template: event → marketplace client default → generic fallback
        $template = null;
        if ($event?->ticketTemplate && $event->ticketTemplate->status === 'active' && !empty($event->ticketTemplate->template_data)) {
            $template = $event->ticketTemplate;
        } else {
            $clientId = $ticket->marketplace_client_id ?? $event?->marketplace_client_id ?? $ticket->order?->marketplace_client_id;
            if ($clientId) {
                $template = \App\Models\TicketTemplate::where('marketplace_client_id', $clientId)
                    ->where('status', 'active')
                    ->orderByDesc('is_default')
                    ->orderByDesc('last_used_at')
                    ->get()
                    ->first(fn ($t) => !empty($t->template_data['layers'] ?? []));
            }
        }

        // Custom template PDF
        if ($template && !empty($template->template_data)) {
            $variableService = app(\App\Services\TicketCustomizer\TicketVariableService::class);
            $generator = app(\App\Services\TicketCustomizer\TicketPreviewGenerator::class);
            $locale = $variableService->resolveOrderLocale($ticket);
            $data = $variableService->resolveTicketData($ticket, $locale);
            $content = $generator->renderToHtml($template->template_data, $data, $locale);

            if (!empty(trim($content))) {
                $size = $template->getSize();
                $widthPt = round($size['width'] * 2.8346, 2);
                $heightPt = round($size['height'] * 2.8346, 2);
                $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';

                // Seating-map second page — gated, falls back to empty string
                // when off. Same wrapper switch as ViewTicket downloadCustomTemplate.
                $seatingPageHtml = \App\Support\SeatingPdfInjector::renderPageFor($ticket, $widthPt, $heightPt);

                // Multi-page verso opt-in (template_data.page_2.enabled).
                // Randam pagina 2 folosind template_data.page_2 ca template separat.
                $page2Html = '';
                $page2Data = $template->template_data['page_2'] ?? null;
                if (is_array($page2Data) && ($page2Data['enabled'] ?? false) && !empty($page2Data['layers'] ?? [])) {
                    try {
                        $page2Content = $generator->renderToHtml($page2Data, $data, $locale);
                        if (!empty(trim($page2Content))) {
                            $page2Bg = $page2Data['meta']['background']['color'] ?? $bgColor;
                            $page2Html = '<div class="ep-ticket-page" style="page-break-before: always; background-color: ' . $page2Bg . ';">' . $page2Content . '</div>';
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::channel('marketplace')->warning('Ticket download page_2 render failed', [
                            'ticket_id' => $ticket->id,
                            'template_id' => $template->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $extraPagesHtml = $seatingPageHtml . $page2Html;

                if ($extraPagesHtml === '') {
                    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>@page{margin:0;size:{$widthPt}pt {$heightPt}pt;}*{margin:0;padding:0;}body{margin:0;padding:0;width:{$widthPt}pt;height:{$heightPt}pt;background-color:{$bgColor};font-family:'DejaVu Sans',sans-serif;overflow:hidden;}</style></head><body>{$content}</body></html>";
                } else {
                    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>@page{margin:0;size:{$widthPt}pt {$heightPt}pt;}*{margin:0;padding:0;}body{margin:0;padding:0;background-color:{$bgColor};font-family:'DejaVu Sans',sans-serif;}.ep-ticket-page{width:{$widthPt}pt;height:{$heightPt}pt;overflow:hidden;position:relative;}</style></head><body><div class=\"ep-ticket-page\">{$content}</div>{$extraPagesHtml}</body></html>";
                }

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                    ->setPaper([0, 0, $widthPt, $heightPt])
                    ->setOption('isRemoteEnabled', true)
                    ->setOption('isHtml5ParserEnabled', true);

                $template->markAsUsed();
                $ticketCode = $ticket->code ?? $ticket->barcode ?? $ticket->id;
                return $pdf->download("bilet-{$ticketCode}.pdf");
            }
        }

        // Generic fallback
        $order = $ticket->order;
        $client = $order?->marketplaceClient;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('marketplace-tickets-pdf', [
            'order' => $order,
            'tickets' => collect([$ticket]),
            'eventName' => $ticket->marketplaceEvent?->name ?? 'Eveniment',
            'marketplaceName' => $client?->public_name ?? $client?->name ?? 'Marketplace',
            'primaryColor' => $client?->settings['theme']['primary_color'] ?? '#1a1a2e',
        ])
            ->setOption('isRemoteEnabled', true)
            ->setPaper([0, 0, 396, 700], 'portrait');

        $ticketCode = $ticket->code ?? $ticket->barcode ?? $ticket->id;
        return $pdf->download("bilet-{$ticketCode}.pdf");
    })->name('marketplace.ticket.download-pdf');
});

// Public Ticket Verification (QR code landing page)
Route::get('/t/{code}', [\App\Http\Controllers\Public\TicketVerificationController::class, 'show'])
    ->name('ticket.verify');

// Public Online-Event Join Gate — the QR-scan analog for events that
// don't have a physical door. Validates the ticket + reveals the
// Zoom/Meet/custom livestream URL only inside the join window.
Route::get('/join/{code}', [\App\Http\Controllers\Public\TicketJoinController::class, 'show'])
    ->name('ticket.join');

// POS Ticket Claim (QR code self-service)
Route::get('/claim/{token}', [\App\Http\Controllers\PosTicketClaimController::class, 'show'])
    ->name('pos-claim.show');
Route::post('/claim/{token}/step1', [\App\Http\Controllers\PosTicketClaimController::class, 'submitRequired'])
    ->name('pos-claim.step1');
Route::post('/claim/{token}/step2', [\App\Http\Controllers\PosTicketClaimController::class, 'submitOptional'])
    ->name('pos-claim.step2');
Route::post('/claim/{token}/skip', [\App\Http\Controllers\PosTicketClaimController::class, 'skipOptional'])
    ->name('pos-claim.skip');
Route::get('/claim/{token}/status', [\App\Http\Controllers\PosTicketClaimController::class, 'status'])
    ->name('pos-claim.status');
Route::get('/claim/{token}/download', [\App\Http\Controllers\PosTicketClaimController::class, 'download'])
    ->name('pos-claim.download');

// Gate Scanner (organizer check-in interface)
Route::get('/gate', [\App\Http\Controllers\Public\GateController::class, 'show'])
    ->name('gate.scanner');

// Activities check-in scanner (A6) — sibling of /gate but for the
// activity flow: confirmation_code OR ticket code/barcode resolves to an
// ActivityBooking and flips status to checked_in.
Route::get('/gate-activitati', [\App\Http\Controllers\Public\GateActivityController::class, 'show'])
    ->name('gate.scanner.activity');

// Android APK Download
Route::get('/download-android', function () {
    // Prefer new filename, fall back to legacy filename for backwards compat
    $path = public_path('downloads/ambilet-android.apk');
    if (!file_exists($path)) {
        $path = public_path('downloads/tixello-staff.apk');
    }
    if (!file_exists($path)) {
        abort(404, 'APK not available yet');
    }
    return response()->download($path, 'ambilet-android.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
})->name('download.android');

Route::get('/android', function () {
    return redirect()->route('download.android');
});

// Tixello Sfana APK Download
Route::get('/download-android-sfana', function () {
    $path = public_path('downloads/sfana-android.apk');
    if (!file_exists($path)) {
        abort(404, 'APK not available yet');
    }
    return response()->download($path, 'sfana-android.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
})->name('download.android.sfana');

Route::get('/android-sfana', function () {
    return redirect()->route('download.android.sfana');
});

// AmBilet v2 (rebrand) APK Download — test-only distribution pentru
// telefoane separate; bundle ID identic cu v1 (com.ambilet.scan) deci
// instaland-o inlocuieste v1. Cand v2 e stabil, se face merge in
// tixello-app + serveste tot pe /android.
Route::get('/download-android-nou', function () {
    $path = public_path('downloads/ambilet-android-nou.apk');
    if (!file_exists($path)) {
        abort(404, 'APK not available yet');
    }
    return response()->download($path, 'ambilet-android-nou.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
})->name('download.android.nou');

Route::get('/android-nou', function () {
    return redirect()->route('download.android.nou');
});

// App version check (used by mobile app to detect updates)
Route::get('/api/app-version', function () {
    return response()->json([
        'latest_version' => config('app.staff_app_version', '1.0.0'),
        'download_url' => 'https://ambilet.ro/android',
        'force_update' => false,
    ]);
});

// Sfana app version check
Route::get('/api/app-version-sfana', function () {
    return response()->json([
        'latest_version' => config('app.sfana_app_version', '0.1.0'),
        'download_url' => 'https://ambilet.ro/android-sfana',
        'force_update' => false,
    ]);
});

// Public Contract Signing Routes (no auth required - token-based)
Route::prefix('contract')->group(function () {
    Route::get('/{token}', [ContractSigningController::class, 'view'])->name('contract.view');
    Route::get('/{token}/pdf', [ContractSigningController::class, 'pdf'])->name('contract.pdf');
    Route::get('/{token}/sign', [ContractSigningController::class, 'signPage'])->name('contract.sign');
    Route::post('/{token}/sign', [ContractSigningController::class, 'sign'])->name('contract.sign.submit');
    Route::get('/{token}/history', [ContractSigningController::class, 'history'])->name('contract.history');
    Route::get('/{token}/version/{versionId}', [ContractSigningController::class, 'downloadVersion'])->name('contract.version.download');
});

// Documentation Routes (Legacy - Microservices)
Route::middleware(['web'])->prefix('docs')->group(function () {
    Route::get('/microservices', [DocsController::class, 'microservicesIndex'])->name('docs.microservices.index');
    Route::get('/microservices/{slug}', [DocsController::class, 'microserviceShow'])->name('docs.microservices.show');
});

// Public Documentation Routes
Route::middleware(['web'])->group(function () {
    Route::get('/docs', [PublicDocsController::class, 'index'])->name('docs.index');
    Route::get('/docs/search', [PublicDocsController::class, 'search'])->name('docs.search');
    Route::get('/docs/category/{categorySlug}', [PublicDocsController::class, 'category'])->name('docs.category');
    Route::get('/docs/{slug}', [PublicDocsController::class, 'show'])->name('docs.show');
});

// Define a helper to register public routes
$registerPublicRoutes = function ($prefix = '') {
    $groupOptions = ['middleware' => 'set.locale'];
    if ($prefix) {
        $groupOptions['prefix'] = $prefix;
    }

    Route::group($groupOptions, function () use ($prefix) {
        Route::get('/', [\App\Http\Controllers\Public\HomeController::class, 'index'])->name($prefix ? '' : 'public.home');
        Route::get('/about', [\App\Http\Controllers\Public\PageController::class, 'about'])->name($prefix ? '' : 'public.about');
        Route::get('/contact', [\App\Http\Controllers\Public\PageController::class, 'contact'])->name($prefix ? '' : 'public.contact');

        Route::get('/events', [\App\Http\Controllers\Public\EventController::class, 'index'])->name($prefix ? '' : 'public.events.index');

        Route::get('/venues', [PublicVenueController::class, 'index'])->name($prefix ? '' : 'public.venues.index');
        Route::get('/venue/{venue:slug}', [PublicVenueController::class, 'show'])->name($prefix ? '' : 'public.venues.show');

        Route::get('/compare/{country}/{slug}', [\App\Http\Controllers\Public\CompareController::class, 'show'])
            ->where('country', '[a-z]{2}')
            ->where('slug', '[a-z0-9\-]+')
            ->name($prefix ? '' : 'public.compare');

        Route::get('/artists', [ArtistPublicController::class, 'index'])->name($prefix ? '' : 'public.artists.index');
        Route::get('/artist/{slug}', [ArtistPublicController::class, 'show'])->name($prefix ? '' : 'public.artist.show');

        // Smart EPK public pages — Modul 3 din Extended Artist
        Route::get('/epk/{artistSlug}', [\App\Http\Controllers\Public\EpkPublicController::class, 'show'])
            ->name($prefix ? '' : 'public.epk.show');
        Route::get('/epk/{artistSlug}/{variantSlug}', [\App\Http\Controllers\Public\EpkPublicController::class, 'show'])
            ->name($prefix ? '' : 'public.epk.show.variant');

        // Location API endpoints for filters
        Route::get('/api/countries', [LocationController::class, 'countries'])->name($prefix ? '' : 'public.api.countries');
        Route::get('/api/states/{country}', [LocationController::class, 'states'])->name($prefix ? '' : 'public.api.states');
        Route::get('/api/cities/{country}/{state}', [LocationController::class, 'cities'])->name($prefix ? '' : 'public.api.cities');

        // Taxonomy API endpoints
        Route::get('/api/event-genres/{typeSlug?}', [LocationController::class, 'eventGenresByType'])->name($prefix ? '' : 'public.api.event-genres');
    });
};

// Register routes WITHOUT locale prefix (e.g., /artist/slug)
$registerPublicRoutes('');

// Register routes WITH locale prefix (e.g., /en/artist/slug)
foreach (['en', 'ro', 'de', 'fr', 'es'] as $locale) {
    Route::group(['prefix' => $locale, 'middleware' => 'set.locale'], function () use ($locale) {
        Route::get('/', [\App\Http\Controllers\Public\HomeController::class, 'index']);
        Route::get('/about', [\App\Http\Controllers\Public\PageController::class, 'about']);
        Route::get('/contact', [\App\Http\Controllers\Public\PageController::class, 'contact']);

        Route::get('/events', [\App\Http\Controllers\Public\EventController::class, 'index']);

        Route::get('/venues', [PublicVenueController::class, 'index']);
        Route::get('/venue/{venue:slug}', [PublicVenueController::class, 'show']);

        Route::get('/compare/{country}/{slug}', [\App\Http\Controllers\Public\CompareController::class, 'show'])
            ->where('country', '[a-z]{2}')
            ->where('slug', '[a-z0-9\-]+');

        Route::get('/artists', [ArtistPublicController::class, 'index']);
        Route::get('/artist/{slug}', [ArtistPublicController::class, 'show']);

        // Smart EPK public pages
        Route::get('/epk/{artistSlug}', [\App\Http\Controllers\Public\EpkPublicController::class, 'show']);
        Route::get('/epk/{artistSlug}/{variantSlug}', [\App\Http\Controllers\Public\EpkPublicController::class, 'show']);

        // Location API endpoints for filters
        Route::get('/api/countries', [LocationController::class, 'countries']);
        Route::get('/api/states/{country}', [LocationController::class, 'states']);
        Route::get('/api/cities/{country}/{state}', [LocationController::class, 'cities']);

        // Taxonomy API endpoints
        Route::get('/api/event-genres/{typeSlug?}', [LocationController::class, 'eventGenresByType']);
    });
}

// Smart EPK rider download (signed URL, expires 5min after lead capture).
// Standalone, no locale prefix needed (URL-ul e generat one-time).
Route::get('/epk/rider/download/{lead}', [\App\Http\Controllers\Public\EpkPublicController::class, 'riderDownload'])
    ->whereNumber('lead')
    ->name('public.epk.rider.download');

// Invoice PDF Routes
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/invoices/{invoice}/pdf', [App\Http\Controllers\InvoicePdfController::class, 'download'])->name('invoices.pdf');
    Route::get('/invoices/{invoice}/pdf/preview', [App\Http\Controllers\InvoicePdfController::class, 'preview'])->name('invoices.pdf.preview');

    // API endpoint for tenant data (used in invoice create/edit forms)
    Route::get('/api/tenants/{tenant}', function (\App\Models\Tenant $tenant) {
        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'company_name' => $tenant->company_name,
            'cui' => $tenant->cui,
            'reg_com' => $tenant->reg_com,
            'contract_number' => $tenant->contract_number,
            'bank_name' => $tenant->bank_name,
            'bank_account' => $tenant->bank_account,
            'address' => $tenant->address,
            'city' => $tenant->city,
            'state' => $tenant->state,
            'country' => $tenant->country,
            'billing_starts_at' => $tenant->billing_starts_at?->toDateString(),
            'billing_cycle_days' => $tenant->billing_cycle_days ?? 30,
        ]);
    })->name('api.tenants.show');
});

/*
|--------------------------------------------------------------------------
| Public Status Page
|--------------------------------------------------------------------------
*/

Route::get('/status', [StatusController::class, 'index'])
    ->name('status');

/*
|--------------------------------------------------------------------------
| Tenant Preview Proxy (for Page Builder iframe)
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/tenant/preview/{domain}/{path?}', [PreviewProxyController::class, 'proxy'])
        ->where('path', '.*')
        ->name('tenant.preview.proxy');
});

/*
|--------------------------------------------------------------------------
| Web Templates — Preview & Demo
|--------------------------------------------------------------------------
*/

Route::prefix('web-templates')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [\App\Http\Controllers\WebTemplatePreviewController::class, 'index'])
        ->name('web-template.index');

    Route::get('/compare', [\App\Http\Controllers\WebTemplatePreviewController::class, 'compare'])
        ->name('web-template.compare');

    Route::post('/feedback/{token}', [\App\Http\Controllers\WebTemplatePreviewController::class, 'submitFeedback'])
        ->where('token', '[A-Za-z0-9\-]{6,20}')
        ->name('web-template.feedback');

    Route::match(['get', 'post'], '/self-service/{token}', [\App\Http\Controllers\WebTemplatePreviewController::class, 'selfService'])
        ->where('token', '[A-Za-z0-9]{20,40}')
        ->name('web-template.self-service');

    Route::get('/{templateSlug}/preview', [\App\Http\Controllers\WebTemplatePreviewController::class, 'preview'])
        ->name('web-template.preview');

    Route::match(['get', 'post'], '/{templateSlug}/{token}', [\App\Http\Controllers\WebTemplatePreviewController::class, 'customizedPreview'])
        ->where('token', '[A-Za-z0-9\-]{6,20}')
        ->name('web-template.customized-preview');
});

// ──────────────────────────────────────────────────────────────────────
// Seating embed for the mobile WebView. Token is HMAC-signed, so the
// route itself doesn't need session/sanctum — the controller validates
// the signature and the event_id binding before serving the page.
// ──────────────────────────────────────────────────────────────────────
Route::get('/seating/embed/{event}', [SeatingEmbedController::class, 'show'])
    ->whereNumber('event')
    ->name('seating.embed.show');

// ─────────────────────────────────────────────────────────────────────────
// Leisure: QR-code printable page for physical inventory (E3).
// Auth-required. Tenant scoping is enforced in the controller.
// ─────────────────────────────────────────────────────────────────────────
Route::get('/leisure/qr-print', [\App\Http\Controllers\Leisure\QrPrintController::class, 'show'])
    ->middleware(['auth'])
    ->name('leisure.qr-print');

// ─────────────────────────────────────────────────────────────────────────
// Leisure: embed widget iframe target (E11).
// Public — gated server-side on tenant_type=leisure + features.embed.enabled.
// ─────────────────────────────────────────────────────────────────────────
Route::get('/embed/leisure/{tenantSlug}', [\App\Http\Controllers\Leisure\EmbedController::class, 'show'])
    ->name('leisure.embed.show');
