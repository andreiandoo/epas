<?php

namespace App\Http\Controllers;

use App\Models\Microservice;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Http\Request;

class MicroserviceMarketplaceController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Display the microservices store
     */
    public function index(Request $request)
    {
        // Handle locale from query parameter
        if ($request->has('locale')) {
            app()->setLocale($request->get('locale'));
        }

        $microservices = Microservice::active()->get();

        // Group by category
        $microservicesByCategory = $microservices->groupBy('category');

        // Define category metadata (colors and descriptions)
        $categoryMeta = [
            'sales' => [
                'name' => 'Sales & Ticketing',
                'description' => 'Boost your revenue with powerful sales tools, door management, and ticketing solutions.',
                'color' => 'indigo',
                'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            ],
            'communication' => [
                'name' => 'Communication',
                'description' => 'Stay connected with your audience through multiple channels and automated messaging.',
                'color' => 'green',
                'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            ],
            'compliance' => [
                'name' => 'Compliance & Legal',
                'description' => 'Ensure regulatory compliance with e-invoicing, tax reporting, and legal documentation.',
                'color' => 'amber',
                'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            ],
            'analytics' => [
                'name' => 'Analytics & Insights',
                'description' => 'Make data-driven decisions with comprehensive analytics and reporting tools.',
                'color' => 'purple',
                'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            ],
            'marketing' => [
                'name' => 'Marketing & Growth',
                'description' => 'Grow your audience with affiliate programs, CRM, and marketing automation.',
                'color' => 'rose',
                'icon' => 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z',
            ],
            'experience' => [
                'name' => 'Attendee Experience',
                'description' => 'Enhance the attendee journey with mobile wallets, waitlists, and group bookings.',
                'color' => 'cyan',
                'icon' => 'M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
        ];

        $cart = session('cart', []);
        $cartCount = count($cart);

        return view('store.index', compact('microservices', 'microservicesByCategory', 'categoryMeta', 'cartCount'));
    }

    /**
     * Display single microservice details
     */
    public function show(string $slug)
    {
        $microservice = Microservice::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $cart = session('cart', []);
        $inCart = in_array($microservice->id, $cart);
        $cartCount = count($cart);

        // Get related microservices
        $relatedMicroservices = Microservice::active()
            ->where('id', '!=', $microservice->id)
            ->where('category', $microservice->category)
            ->limit(3)
            ->get();

        return view('store.show', compact('microservice', 'inCart', 'cartCount', 'relatedMicroservices'));
    }

    /**
     * Add microservice to cart
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'microservice_id' => 'required|exists:microservices,id',
        ]);

        $cart = session('cart', []);
        $microserviceId = $request->microservice_id;

        if (!in_array($microserviceId, $cart)) {
            $cart[] = $microserviceId;
            session(['cart' => $cart]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'cartCount' => count($cart),
                'message' => 'Added to cart',
            ]);
        }

        return back()->with('success', 'Microservice added to cart');
    }

    /**
     * Remove microservice from cart
     */
    public function removeFromCart(Request $request)
    {
        $request->validate([
            'microservice_id' => 'required|exists:microservices,id',
        ]);

        $cart = session('cart', []);
        $cart = array_values(array_diff($cart, [$request->microservice_id]));
        session(['cart' => $cart]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'cartCount' => count($cart),
                'message' => 'Removed from cart',
            ]);
        }

        return back()->with('success', 'Microservice removed from cart');
    }

    /**
     * Display cart page
     */
    public function cart()
    {
        $cart = session('cart', []);
        $microservices = Microservice::whereIn('id', $cart)->get();

        $subtotal = $microservices->sum('price');
        $currency = $microservices->first()?->currency ?? 'EUR';

        return view('store.cart', compact('microservices', 'subtotal', 'currency'));
    }

    /**
     * Display checkout page (requires auth + tenant)
     */
    public function checkoutPage(Request $request)
    {
        $user = auth()->user();

        // Check if user is a tenant
        if ($user->role !== 'tenant') {
            return redirect()->route('store.cart')
                ->with('error', 'Only tenant accounts can purchase microservices. Please register as a tenant first.');
        }

        // Get tenant associated with this user
        $tenant = Tenant::where('owner_id', $user->id)->first();

        if (!$tenant) {
            return redirect()->route('store.cart')
                ->with('error', 'No tenant account found. Please complete your registration.');
        }

        $cart = session('cart', []);

        if (empty($cart)) {
            return redirect()->route('store.index')
                ->with('error', 'Your cart is empty');
        }

        $microservices = Microservice::whereIn('id', $cart)->get();

        // Check if any microservices are already owned
        $ownedIds = $tenant->microservices()
            ->wherePivot('is_active', true)
            ->pluck('microservices.id')
            ->toArray();

        $alreadyOwned = $microservices->whereIn('id', $ownedIds);

        $subtotal = $microservices->sum('price');
        $currency = $microservices->first()?->currency ?? 'EUR';

        // Check if Stripe is configured
        $stripeConfigured = $this->stripeService->isConfigured();
        $stripePublicKey = $this->stripeService->getPublishableKey();

        return view('store.checkout', compact(
            'microservices',
            'tenant',
            'subtotal',
            'currency',
            'alreadyOwned',
            'stripeConfigured',
            'stripePublicKey'
        ));
    }

    /**
     * Process checkout and redirect to Stripe
     */
    public function processCheckout(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'tenant') {
            return redirect()->route('store.cart')
                ->with('error', 'Only tenant accounts can purchase microservices.');
        }

        $tenant = Tenant::where('owner_id', $user->id)->first();

        if (!$tenant) {
            return redirect()->route('store.cart')
                ->with('error', 'No tenant account found.');
        }

        $cart = session('cart', []);

        if (empty($cart)) {
            return redirect()->route('store.index')
                ->with('error', 'Your cart is empty');
        }

        try {
            $session = $this->stripeService->createCheckoutSession(
                $tenant,
                $cart,
                route('store.payment.success'),
                route('store.payment.cancel')
            );

            // Store session ID for later retrieval
            session(['stripe_checkout_session_id' => $session->id]);

            return redirect($session->url);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create checkout session: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment success
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('store.index')->with('error', 'Invalid session');
        }

        try {
            $session = $this->stripeService->retrieveSession($sessionId);

            // Get tenant and microservices from metadata
            $tenantId = $session->metadata->tenant_id ?? $session->client_reference_id;
            $microserviceIds = explode(',', $session->metadata->microservice_ids ?? '');

            $tenant = Tenant::find($tenantId);
            $microservices = Microservice::whereIn('id', $microserviceIds)->get();

            // Clear the cart
            session()->forget('cart');

            return view('store.success', compact('session', 'tenant', 'microservices'));
        } catch (\Exception $e) {
            return redirect()->route('store.index')
                ->with('error', 'Failed to retrieve session: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment cancellation
     */
    public function cancel()
    {
        return redirect()->route('store.cart')
            ->with('warning', 'Payment was cancelled. Your cart is still available.');
    }
}
