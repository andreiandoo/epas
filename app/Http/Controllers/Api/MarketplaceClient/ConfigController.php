<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConfigController extends BaseController
{
    /**
     * Get marketplace client configuration
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $settings = Setting::current();

        return $this->success([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
                'domain' => $client->domain,
            ],
            'contact' => [
                'email' => $client->contact_email,
                'phone' => $client->contact_phone,
                'operating_hours' => $client->operating_hours,
            ],
            'platform' => [
                'name' => $settings->company_name ?? 'Tixello',
                'url' => config('app.url'),
            ],
            'settings' => $client->settings ?? [],
            'allowed_tenants' => $client->allowed_tenants,
        ]);
    }

    /**
     * Get list of tenants this client can sell for
     */
    public function tenants(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $tenants = $client->activeTenants()
            ->select(['tenants.id', 'tenants.name', 'tenants.public_name', 'tenants.slug', 'tenants.domain'])
            ->where('tenants.status', 'active')
            ->get()
            ->map(function ($tenant) use ($client) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'public_name' => $tenant->public_name,
                    'slug' => $tenant->slug,
                    'domain' => $tenant->domain,
                    'commission_rate' => $client->getCommissionForTenant($tenant->id),
                ];
            });

        return $this->success($tenants);
    }
}
