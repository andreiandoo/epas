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
use App\Http\Controllers\TenantPaymentWebhookController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\Public\DocsController as PublicDocsController;
use App\Http\Controllers\Tenant\PreviewProxyController;
use App\Http\Controllers\Admin\TicketCustomizerController;
use App\Http\Controllers\Tenant\TicketCustomizerController as TenantTicketCustomizerController;
use App\Http\Controllers\Marketplace\TicketCustomizerController as MarketplaceTicketCustomizerController;
use App\Http\Controllers\ApplePayVerificationController;

Route::pattern('locale', 'en|ro|de|fr|es');

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
});

// Marketplace Search API (uses marketplace_admin guard)
Route::middleware(['web', 'auth:marketplace_admin'])->group(function () {
    Route::get('/api/search/marketplace/{marketplace}', [GlobalSearchController::class, 'searchMarketplace'])->name('marketplace.api.global-search');
});

// Marketplace Client Switcher (for super-admins)
Route::middleware(['web'])->get('/marketplace/switch-client/{clientId}', function ($clientId) {
    // Only allow if user is super-admin from core
    if (!auth('web')->check() || !auth('web')->user()->isSuperAdmin()) {
        abort(403, 'Unauthorized');
    }

    // Validate client exists
    $client = \App\Models\MarketplaceClient::where('status', 'active')->find($clientId);
    if (!$client) {
        abort(404, 'Marketplace client not found');
    }

    // Update session and logout from current marketplace admin
    auth('marketplace_admin')->logout();
    session(['super_admin_marketplace_client_id' => $clientId]);
    session()->forget('marketplace_is_super_admin'); // Force re-login

    return redirect('/marketplace');
})->name('marketplace.switch-client');

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

// Stripe Webhook (must be outside CSRF protection)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe')
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
        session()->forget(['demo_tenant_id', 'demo_parent_tenant_id', 'demo_parent_tenant_name']);
        return redirect('/tenant')->with('success', 'Demo mode deactivated.');
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
            $data = $variableService->resolveTicketData($ticket);
            $content = $generator->renderToHtml($template->template_data, $data);

            if (!empty(trim($content))) {
                $size = $template->getSize();
                $widthPt = round($size['width'] * 2.8346, 2);
                $heightPt = round($size['height'] * 2.8346, 2);
                $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';

                $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>@page{margin:0;size:{$widthPt}pt {$heightPt}pt;}*{margin:0;padding:0;}body{margin:0;padding:0;width:{$widthPt}pt;height:{$heightPt}pt;background-color:{$bgColor};font-family:'DejaVu Sans',sans-serif;overflow:hidden;}</style></head><body>{$content}</body></html>";

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

// Android APK Download
Route::get('/download-android', function () {
    $path = public_path('downloads/tixello-staff.apk');
    if (!file_exists($path)) {
        abort(404, 'APK not available yet');
    }
    return response()->download($path, 'tixello-staff.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
})->name('download.android');

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

        // Location API endpoints for filters
        Route::get('/api/countries', [LocationController::class, 'countries']);
        Route::get('/api/states/{country}', [LocationController::class, 'states']);
        Route::get('/api/cities/{country}/{state}', [LocationController::class, 'cities']);

        // Taxonomy API endpoints
        Route::get('/api/event-genres/{typeSlug?}', [LocationController::class, 'eventGenresByType']);
    });
}

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
