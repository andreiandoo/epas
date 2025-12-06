<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $hasAffiliates = $this->hasFeature($tenant, 'affiliates');

        $data = [
            'tenant' => [
                'id' => $tenant->id,
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
                'affiliates' => $hasAffiliates,
                'insurance' => $this->hasFeature($tenant, 'insurance'),
                'promo_codes' => $this->hasFeature($tenant, 'promo-codes'),
            ],
            'payment_methods' => $this->getPaymentMethods($tenant),
            'currency' => $settings['currency'] ?? 'RON',
            'locale' => $settings['locale'] ?? 'ro',
        ];

        // Include affiliate tracking config if feature is enabled
        if ($hasAffiliates) {
            $data['affiliate_tracking'] = $this->getAffiliateConfig($tenant);
        }

        // Add platform branding (Powered by logo)
        $data['platform'] = $this->getPlatformBranding();

        return response()->json([
            'success' => true,
            'data' => $data,
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

    protected function getAffiliateConfig($tenant): array
    {
        // Get microservice configuration from pivot table
        $microservice = $tenant->microservices()
            ->whereHas('microservice', fn ($q) => $q->where('slug', 'affiliates'))
            ->first();

        $config = $microservice?->pivot->configuration ?? [];

        return [
            'cookie_name' => $config['cookie_name'] ?? 'aff_ref',
            'cookie_duration_days' => $config['cookie_duration_days'] ?? 90,
            'api_url' => config('app.url') . '/api/affiliates/track-click',
        ];
    }

    /**
     * Get platform branding (public logos for "Powered by" section)
     */
    protected function getPlatformBranding(): array
    {
        $settings = Setting::current();
        $meta = $settings->meta ?? [];

        $getLogoUrl = function ($value) {
            if (empty($value)) {
                return null;
            }
            // Handle array (FileUpload can store as array)
            if (is_array($value)) {
                $value = reset($value);
            }
            if (empty($value)) {
                return null;
            }
            return Storage::disk('public')->url($value);
        };

        return [
            'name' => 'Tixello',
            'url' => 'https://tixello.com',
            'logo_light' => $getLogoUrl($meta['logo_public_light'] ?? null),
            'logo_dark' => $getLogoUrl($meta['logo_public_dark'] ?? null),
        ];
    }
}
