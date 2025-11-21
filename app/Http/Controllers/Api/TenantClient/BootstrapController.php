<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    /**
     * Get initial bootstrap data for the tenant client
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $domain = $request->attributes->get('domain');

        $settings = $tenant->settings ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => [
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
                'branding' => [
                    'logo' => $settings['branding']['logo_url'] ?? null,
                    'favicon' => $settings['branding']['favicon_url'] ?? null,
                    'company_name' => $tenant->company_name ?? $tenant->name,
                ],
                'theme' => [
                    'primary_color' => $settings['theme']['primary_color'] ?? '#3B82F6',
                    'secondary_color' => $settings['theme']['secondary_color'] ?? '#1E40AF',
                    'font_family' => $settings['theme']['font_family'] ?? 'Inter',
                ],
                'features' => [
                    'seating' => $this->hasFeature($tenant, 'seating'),
                    'affiliates' => $this->hasFeature($tenant, 'affiliates'),
                    'insurance' => $this->hasFeature($tenant, 'insurance'),
                    'promo_codes' => $this->hasFeature($tenant, 'promo-codes'),
                ],
                'payment_methods' => $this->getPaymentMethods($tenant),
                'currency' => $settings['currency'] ?? 'RON',
                'locale' => $settings['locale'] ?? 'ro',
            ],
        ]);
    }

    protected function hasFeature($tenant, string $slug): bool
    {
        return $tenant->microservices()
            ->whereHas('microservice', fn ($q) => $q->where('slug', $slug))
            ->where('is_active', true)
            ->exists();
    }

    protected function getPaymentMethods($tenant): array
    {
        $settings = $tenant->settings ?? [];
        $methods = [];

        if (!empty($settings['payments']['stripe']['enabled'])) {
            $methods[] = ['id' => 'stripe', 'name' => 'Card Payment'];
        }
        if (!empty($settings['payments']['netopia']['enabled'])) {
            $methods[] = ['id' => 'netopia', 'name' => 'Netopia'];
        }
        if (!empty($settings['payments']['euplatesc']['enabled'])) {
            $methods[] = ['id' => 'euplatesc', 'name' => 'EuPlatesc'];
        }
        if (!empty($settings['payments']['payu']['enabled'])) {
            $methods[] = ['id' => 'payu', 'name' => 'PayU'];
        }

        return $methods;
    }
}
