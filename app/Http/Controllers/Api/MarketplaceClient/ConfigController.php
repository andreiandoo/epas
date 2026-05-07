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
     * Contact form submission — relays the visitor's message to the
     * marketplace's configured contact_email. Routed through the marketplace's
     * own SMTP transport via sendMarketplaceEmail() so the From header carries
     * the marketplace's domain (no localhost / Tixello leakage). The visitor's
     * email is set as Reply-To so a one-click reply lands in their inbox.
     *
     * Subject options on the form (bilete/plati/rambursare/cont/organizator/
     * parteneriat/altele) are mapped to a human-readable Romanian label.
     */
    public function contact(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        if (empty($client->contact_email) || !filter_var($client->contact_email, FILTER_VALIDATE_EMAIL)) {
            return $this->error(
                'Adresa de contact a marketplace-ului nu este configurată. Contactează administratorul.',
                422
            );
        }

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|max:180',
            'phone'      => 'nullable|string|max:50',
            'subject'    => 'required|string|max:50',
            'order_id'   => 'nullable|string|max:80',
            'message'    => 'required|string|max:5000',
        ]);

        $subjectLabels = [
            'bilete'       => 'Întrebări despre bilete',
            'plati'        => 'Probleme cu plata',
            'rambursare'   => 'Solicitare rambursare',
            'cont'         => 'Probleme cu contul',
            'organizator'  => 'Devino organizator',
            'parteneriat'  => 'Propunere parteneriat',
            'altele'       => 'Altele',
        ];
        $subjectLabel = $subjectLabels[$data['subject']] ?? $data['subject'];

        $siteName = $client->public_name ?? $client->name ?? 'Marketplace';
        $fullName = trim($data['first_name'] . ' ' . $data['last_name']);

        $body = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;color:#1a1a1a;">'
            . '<h2 style="color:#A51C30;margin:0 0 16px;">Mesaj nou prin formularul de contact</h2>'
            . '<table style="width:100%;border-collapse:collapse;margin:0 0 16px;">'
            . '<tr><td style="padding:6px 0;color:#6b7280;width:140px;">De la</td><td style="padding:6px 0;font-weight:600;">' . e($fullName) . ' &lt;' . e($data['email']) . '&gt;</td></tr>'
            . (!empty($data['phone']) ? '<tr><td style="padding:6px 0;color:#6b7280;">Telefon</td><td style="padding:6px 0;">' . e($data['phone']) . '</td></tr>' : '')
            . '<tr><td style="padding:6px 0;color:#6b7280;">Subiect</td><td style="padding:6px 0;font-weight:600;">' . e($subjectLabel) . '</td></tr>'
            . (!empty($data['order_id']) ? '<tr><td style="padding:6px 0;color:#6b7280;">Comandă</td><td style="padding:6px 0;font-family:monospace;">' . e($data['order_id']) . '</td></tr>' : '')
            . '</table>'
            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">'
            . '<div style="white-space:pre-line;color:#374151;font-size:15px;line-height:1.6;">' . e($data['message']) . '</div>'
            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0 12px;">'
            . '<p style="color:#9ca3af;font-size:12px;margin:0;">Trimis prin formularul de contact al ' . e($siteName) . '. Apasă "Reply" pentru a răspunde direct vizitatorului.</p>'
            . '</div>';

        $this->sendMarketplaceEmail(
            $client,
            $client->contact_email,
            $siteName,
            '[' . $siteName . '] ' . $subjectLabel . ' — ' . $fullName,
            $body,
            [
                'template_slug' => 'public_contact_form',
                'reply_to_email' => $data['email'],
                'reply_to_name' => $fullName,
            ]
        );

        return $this->success(['message' => 'Mesajul a fost trimis cu succes.']);
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
            'cultural_card' => $this->getCulturalCardSettings($client),
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
            ->whereNull('marketplace_organizer_id')
            ->where('enabled', true)
            ->get();

        return $this->success($this->buildScriptResponse($integrations));
    }

    /**
     * Get tracking scripts for a specific organizer's event pages
     */
    public function organizerTrackingScripts(Request $request, int $organizerId): JsonResponse
    {
        $client = $this->requireClient($request);

        $integrations = TrackingIntegration::where('marketplace_organizer_id', $organizerId)
            ->where('enabled', true)
            ->get();

        return $this->success($this->buildScriptResponse($integrations));
    }

    /**
     * Build head/body script response from integrations
     */
    protected function buildScriptResponse($integrations): array
    {
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

        return [
            'head_scripts' => implode("\n", $headScripts),
            'body_scripts' => implode("\n", $bodyScripts),
        ];
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
(function(){var c=null;try{c=JSON.parse(localStorage.getItem('ambilet_cookie_consent'))}catch(e){}
if(!c||!c.marketing)fbq('consent','revoke');})();
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
ttq.load('{$id}');
(function(){var c=null;try{c=JSON.parse(localStorage.getItem('ambilet_cookie_consent'))}catch(e){}
if(!c||!c.marketing){ttq.disableCookie();ttq.revokeConsent();}})();
ttq.page();
}(window,document,'ttq');
</script>
<!-- End TikTok Pixel -->
HTML;
    }

    /**
     * Get cultural card payment settings from Netopia microservice config
     */
    protected function getCulturalCardSettings($client): ?array
    {
        $pivot = MarketplaceClientMicroservice::where('marketplace_client_id', $client->id)
            ->whereHas('microservice', fn($q) => $q->where('slug', 'payment-netopia'))
            ->where('status', 'active')
            ->first();

        if (!$pivot) {
            return null;
        }

        $settings = $pivot->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?? [];
        }

        if (empty($settings['cultural_card_enabled'])) {
            return null;
        }

        return [
            'enabled' => true,
            'surcharge_percent' => (float) ($settings['cultural_card_surcharge_percent'] ?? 4),
        ];
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
            'apply_to' => $settings['apply_to'] ?? 'all',
        ];
    }
}
