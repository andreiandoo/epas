<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class CustomerAccountController extends Controller
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
     * Show customer account dashboard
     */
    public function index(Request $request, string $tenant)
    {
        $tenant = $this->getTenant($tenant);

        if (!$tenant) {
            abort(404);
        }

        return view('customer.account', [
            'tenant' => $tenant,
            'customer' => auth('customer')->user(),
        ]);
    }

    /**
     * Show customer orders
     */
    public function orders(Request $request, string $tenant)
    {
        $tenant = $this->getTenant($tenant);

        if (!$tenant) {
            abort(404);
        }

        $customer = auth('customer')->user();
        $orders = $customer->orders()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('customer.orders', [
            'tenant' => $tenant,
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }

    /**
     * Show customer tickets
     */
    public function tickets(Request $request, string $tenant)
    {
        $tenant = $this->getTenant($tenant);

        if (!$tenant) {
            abort(404);
        }

        return view('customer.tickets', [
            'tenant' => $tenant,
            'customer' => auth('customer')->user(),
        ]);
    }

    /**
     * Show affiliate program page
     */
    public function affiliate(Request $request, string $tenant)
    {
        $tenant = $this->getTenant($tenant);

        if (!$tenant) {
            abort(404);
        }

        // Check if affiliate microservice is active
        if (!$tenant->hasMicroservice('affiliate-tracking')) {
            abort(404, 'Affiliate program not available');
        }

        return view('customer.affiliate', [
            'tenant' => $tenant,
            'customer' => auth('customer')->user(),
        ]);
    }

    /**
     * Logout customer
     */
    public function logout(Request $request, string $tenant)
    {
        auth('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $tenantModel = $this->getTenant($tenant);

        if ($tenantModel) {
            // Get primary domain for redirect
            $domain = $tenantModel->domains()->where('is_primary', true)->first();
            if ($domain) {
                return redirect("https://{$domain->domain}");
            }
        }

        return redirect('/');
    }
}
