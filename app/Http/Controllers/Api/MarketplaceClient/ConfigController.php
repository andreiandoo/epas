<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\MarketplaceClientMicroservice;
use App\Models\Setting;
use App\Models\TrackingIntegration;
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

    /**
     * Get checkout features/options (ticket insurance, etc.)
     */
    public function checkoutFeatures(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $features = [
            'ticket_insurance' => $this->getTicketInsuranceSettings($client),
        ];

        return $this->success($features);
    }

    /**
     * Get tracking scripts for injection into marketplace pages
     */
    public function trackingScripts(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $integrations = TrackingIntegration::where('marketplace_client_id', $client->id)
            ->where('enabled', true)
            ->get();

        $headScripts = [];
        $bodyScripts = [];

        foreach ($integrations as $integration) {
            $settings = $integration->getSettings();
            $injectAt = $integration->getInjectAt();
            $providerId = $integration->getProviderId();

            if (empty($providerId)) {
                continue;
            }

            $script = $this->generateTrackingScript($integration->provider, $providerId, $settings);

            if (empty($script)) {
                continue;
            }

            if ($injectAt === 'head') {
                $headScripts[] = $script;
            } else {
                $bodyScripts[] = $script;
            }
        }

        return $this->success([
            'head_scripts' => implode("\n", $headScripts),
            'body_scripts' => implode("\n", $bodyScripts),
        ]);
    }

    /**
     * Generate tracking script HTML for a specific provider
     */
    protected function generateTrackingScript(string $provider, string $providerId, array $settings): string
    {
        return match ($provider) {
            'ga4' => $this->generateGA4Script($providerId),
            'gtm' => $this->generateGTMScript($providerId),
            'meta' => $this->generateMetaPixelScript($providerId),
            'tiktok' => $this->generateTikTokPixelScript($providerId),
            default => '',
        };
    }

    protected function generateGA4Script(string $measurementId): string
    {
        $id = htmlspecialchars($measurementId, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('js',new Date());gtag('config','{$id}');
</script>
HTML;
    }

    protected function generateGTMScript(string $containerId): string
    {
        $id = htmlspecialchars($containerId, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$id}');</script>
<!-- End Google Tag Manager -->
HTML;
    }

    protected function generateMetaPixelScript(string $pixelId): string
    {
        $id = htmlspecialchars($pixelId, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init','{$id}');fbq('track','PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1"/></noscript>
<!-- End Meta Pixel -->
HTML;
    }

    protected function generateTikTokPixelScript(string $pixelId): string
    {
        $id = htmlspecialchars($pixelId, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- TikTok Pixel -->
<script>
!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=
["page","track","identify","instances","debug","on","off","once","ready","alias",
"group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],
ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=
function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);
return e};ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",
o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},
ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var a=document.createElement("script");
a.type="text/javascript",a.async=!0,a.src=r+"?sdkid="+e+"&lib="+t;var s=
document.getElementsByTagName("script")[0];s.parentNode.insertBefore(a,s)};
ttq.load('{$id}');ttq.page();
}(window,document,'ttq');
</script>
<!-- End TikTok Pixel -->
HTML;
    }

    /**
     * Get ticket insurance settings for a marketplace client
     */
    protected function getTicketInsuranceSettings($client): ?array
    {
        $pivot = MarketplaceClientMicroservice::where('marketplace_client_id', $client->id)
            ->whereHas('microservice', fn($q) => $q->where('slug', 'ticket-insurance'))
            ->where('status', 'active')
            ->first();

        if (!$pivot) {
            return null;
        }

        $settings = $pivot->settings ?? [];

        // Only return if enabled
        if (empty($settings['is_enabled'])) {
            return null;
        }

        // Only return settings needed for checkout display
        return [
            'enabled' => true,
            'label' => $settings['label'] ?? 'Taxa de retur',
            'description' => $settings['description'] ?? '',
            'price' => (float) ($settings['price'] ?? 5.00),
            'price_type' => $settings['price_type'] ?? 'fixed',
            'price_percentage' => (float) ($settings['price_percentage'] ?? 5),
            'show_in_checkout' => $settings['show_in_checkout'] ?? true,
            'pre_checked' => $settings['pre_checked'] ?? false,
            'terms_url' => $settings['terms_url'] ?? null,
        ];
    }
}
