<?php

namespace App\Services\Tracking;

use App\Models\Tenant;
use App\Models\TrackingIntegration;
use App\Services\Tracking\Providers\TrackingProviderFactory;
use Illuminate\Http\Request;

/**
 * Tracking Script Injector
 *
 * Handles injection of tracking scripts into HTML responses
 * based on tenant configuration and user consent
 */
class TrackingScriptInjector
{
    public function __construct(
        private ConsentServiceInterface $consentService
    ) {}

    /**
     * Inject tracking scripts into HTML content
     *
     * @param string $html Original HTML content
     * @param Tenant $tenant Tenant whose tracking config to use
     * @param string $pageScope Current page scope (public, admin, all)
     * @param Request $request Current request (for nonce)
     * @return string Modified HTML with injected scripts
     */
    public function inject(string $html, Tenant $tenant, string $pageScope = 'public', ?Request $request = null): string
    {
        // Get nonce from request if available (for CSP)
        $nonce = $request?->attributes->get('csp_nonce');

        // Get enabled integrations for this tenant
        $integrations = TrackingIntegration::where('tenant_id', $tenant->id)
            ->where('enabled', true)
            ->get();

        if ($integrations->isEmpty()) {
            return $html;
        }

        $headInjections = [];
        $bodyInjections = [];
        $dataLayerAdapters = [];

        foreach ($integrations as $integration) {
            // Check page scope
            if (!$integration->shouldInjectOnPage($pageScope)) {
                continue;
            }

            // Check consent
            if (!$this->consentService->hasConsent($integration->consent_category)) {
                continue;
            }

            try {
                $provider = TrackingProviderFactory::make($integration->provider);
                $settings = $integration->getSettings();

                // Get injection location preference
                $injectAt = $integration->getInjectAt();

                if ($injectAt === 'head') {
                    $headInjections[] = $provider->injectHead($settings, $nonce);
                } else {
                    $bodyInjections[] = $provider->injectBodyEnd($settings, $nonce);
                }

                // Add data layer adapter
                $dataLayerAdapters[] = $provider->getDataLayerAdapter();

            } catch (\Exception $e) {
                // Log error but don't break the page
                \Log::error('Tracking script injection error', [
                    'tenant_id' => $tenant->id,
                    'provider' => $integration->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Inject head scripts before </head>
        if (!empty($headInjections)) {
            $headCode = implode("\n", $headInjections);
            $html = str_replace('</head>', $headCode . "\n</head>", $html);
        }

        // Inject body scripts before </body>
        if (!empty($bodyInjections) || !empty($dataLayerAdapters)) {
            $nonceAttr = $nonce ? " nonce=\"{$nonce}\"" : '';

            $bodyCode = implode("\n", $bodyInjections);

            // Add tracking event bus helper library
            $bodyCode .= "\n<script{$nonceAttr}>\n" . TrackingEventBus::getHelperLibrary() . "\n</script>";

            // Add data layer adapters
            if (!empty($dataLayerAdapters)) {
                $bodyCode .= "\n<script{$nonceAttr}>\n" . implode("\n\n", $dataLayerAdapters) . "\n</script>";
            }

            $html = str_replace('</body>', $bodyCode . "\n</body>", $html);
        }

        return $html;
    }

    /**
     * Get preview of what would be injected (for debug endpoint)
     *
     * @param Tenant $tenant
     * @param string $pageScope
     * @return array
     */
    public function getInjectionPreview(Tenant $tenant, string $pageScope = 'public'): array
    {
        $integrations = TrackingIntegration::where('tenant_id', $tenant->id)
            ->where('enabled', true)
            ->get();

        $preview = [
            'head' => [],
            'body' => [],
            'adapters' => [],
            'consent_status' => [],
        ];

        foreach ($integrations as $integration) {
            $hasConsent = $this->consentService->hasConsent($integration->consent_category);
            $shouldInject = $integration->shouldInjectOnPage($pageScope);

            $preview['consent_status'][$integration->provider] = [
                'consent_category' => $integration->consent_category,
                'has_consent' => $hasConsent,
                'page_scope_match' => $shouldInject,
                'will_inject' => $hasConsent && $shouldInject,
            ];

            if (!$hasConsent || !$shouldInject) {
                continue;
            }

            try {
                $provider = TrackingProviderFactory::make($integration->provider);
                $settings = $integration->getSettings();
                $injectAt = $integration->getInjectAt();

                if ($injectAt === 'head') {
                    $preview['head'][$integration->provider] = $provider->injectHead($settings, 'NONCE_PLACEHOLDER');
                } else {
                    $preview['body'][$integration->provider] = $provider->injectBodyEnd($settings, 'NONCE_PLACEHOLDER');
                }

                $preview['adapters'][$integration->provider] = $provider->getDataLayerAdapter();

            } catch (\Exception $e) {
                $preview['errors'][$integration->provider] = $e->getMessage();
            }
        }

        return $preview;
    }
}
