<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CustomerAuthController extends Controller
{
    /**
     * Get tenant from route parameter
     */
    protected function getTenant(string $tenantSlug): ?Tenant
    {
        return Tenant::where('slug', $tenantSlug)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Show login form
     */
    public function showLogin(Request $request, string $tenant)
    {
        $tenant = $this->getTenant($tenant);

        if (!$tenant) {
            abort(404);
        }

        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.account', ['tenant' => $tenant->slug]);
        }

        return view('customer.auth.login', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Handle login
     */
    public function login(Request $request, string $tenantSlug)
    {
        $tenant = $this->getTenant($tenantSlug);

        if (!$tenant) {
            abort(404);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Find customer by email
        $customer = Customer::where('email', $request->email)->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            return back()->withErrors([
                'email' => __('The provided credentials do not match our records.'),
            ])->onlyInput('email');
        }

        // Attach customer to tenant if not already
        if (!$customer->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $customer->tenants()->attach($tenant->id);
        }

        Auth::guard('customer')->login($customer, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('customer.account', ['tenant' => $tenant->slug]));
    }

    /**
     * Show registration form
     */
    public function showRegister(Request $request, string $tenant)
    {
        $tenant = $this->getTenant($tenant);

        if (!$tenant) {
            abort(404);
        }

        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.account', ['tenant' => $tenant->slug]);
        }

        return view('customer.auth.register', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Handle registration
     */
    public function register(Request $request, string $tenantSlug)
    {
        $tenant = $this->getTenant($tenantSlug);

        if (!$tenant) {
            abort(404);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Check if customer already exists
        $customer = Customer::where('email', $request->email)->first();

        if ($customer) {
            // Customer exists, check password
            if (!Hash::check($request->password, $customer->password)) {
                return back()->withErrors([
                    'email' => __('An account with this email already exists. Please login instead.'),
                ])->onlyInput('email', 'first_name', 'last_name');
            }
        } else {
            // Create new customer
            $customer = Customer::create([
                'tenant_id' => $tenant->id,
                'primary_tenant_id' => $tenant->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
        }

        // Attach customer to tenant if not already
        if (!$customer->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $customer->tenants()->attach($tenant->id);
        }

        Auth::guard('customer')->login($customer);

        $request->session()->regenerate();

        return redirect()->route('customer.account', ['tenant' => $tenant->slug]);
    }
}
