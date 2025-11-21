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
        $microservices = Microservice::active()->get();
        $cart = session('cart', []);
        $cartCount = count($cart);

        return view('store.index', compact('microservices', 'cartCount'));
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
            ->wherePivot('status', 'active')
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
