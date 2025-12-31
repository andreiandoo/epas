<?php

namespace App\Services\Tracking;

use App\Models\Tenant;

class TxSdkHelper
{
    /**
     * Generate the SDK configuration script tag.
     */
    public static function configScript(
        string|int $tenantId,
        ?string $siteId = null,
        bool $debug = false,
        array $options = []
    ): string {
        $config = array_merge([
            'tenantId' => (string) $tenantId,
            'siteId' => $siteId,
            'apiEndpoint' => config('app.url') . '/api/tx/events/batch',
            'debug' => $debug || config('app.debug'),
            'autoTrackPageView' => true,
            'autoTrackEngagement' => true,
        ], $options);

        // Remove null values
        $config = array_filter($config, fn($v) => $v !== null);

        $json = json_encode($config, JSON_UNESCAPED_SLASHES);

        return "<script>window.txConfig = {$json};</script>";
    }

    /**
     * Generate the SDK loader script tag.
     */
    public static function loaderScript(?string $customSdkUrl = null): string
    {
        $sdkUrl = $customSdkUrl ?? config('app.url') . '/js/tracking/tx-loader.js';

        return "<script src=\"{$sdkUrl}\" async></script>";
    }

    /**
     * Generate complete SDK embed code.
     */
    public static function embedCode(
        string|int $tenantId,
        ?string $siteId = null,
        bool $debug = false,
        array $options = []
    ): string {
        $configScript = self::configScript($tenantId, $siteId, $debug, $options);
        $loaderScript = self::loaderScript();

        return "{$configScript}\n{$loaderScript}";
    }

    /**
     * Generate SDK embed for a tenant.
     */
    public static function forTenant(Tenant $tenant, bool $debug = false): string
    {
        return self::embedCode(
            $tenant->id,
            $tenant->slug,
            $debug,
            [
                'autoTrackPageView' => $tenant->settings['tracking_auto_pageview'] ?? true,
                'autoTrackEngagement' => $tenant->settings['tracking_auto_engagement'] ?? true,
            ]
        );
    }

    /**
     * Generate hidden inputs for forms (to pass visitor/session IDs).
     */
    public static function hiddenInputs(): string
    {
        return <<<HTML
<input type="hidden" name="_tx_visitor_id" id="_tx_visitor_id" value="">
<input type="hidden" name="_tx_session_id" id="_tx_session_id" value="">
<script>
(function() {
    var setIds = function() {
        if (window.tx && typeof tx.getVisitorId === 'function') {
            document.getElementById('_tx_visitor_id').value = tx.getVisitorId();
            document.getElementById('_tx_session_id').value = tx.getSessionId();
        } else {
            setTimeout(setIds, 100);
        }
    };
    setIds();
})();
</script>
HTML;
    }

    /**
     * Get visitor ID from request (from form submission or header).
     */
    public static function getVisitorIdFromRequest(): ?string
    {
        $request = request();

        // Check form input
        $visitorId = $request->input('_tx_visitor_id');
        if ($visitorId && self::isValidUuid($visitorId)) {
            return $visitorId;
        }

        // Check header (for API calls)
        $visitorId = $request->header('X-TX-Visitor-Id');
        if ($visitorId && self::isValidUuid($visitorId)) {
            return $visitorId;
        }

        return null;
    }

    /**
     * Get session ID from request.
     */
    public static function getSessionIdFromRequest(): ?string
    {
        $request = request();

        $sessionId = $request->input('_tx_session_id');
        if ($sessionId && self::isValidUuid($sessionId)) {
            return $sessionId;
        }

        $sessionId = $request->header('X-TX-Session-Id');
        if ($sessionId && self::isValidUuid($sessionId)) {
            return $sessionId;
        }

        return null;
    }

    /**
     * Generate JavaScript for tracking an event.
     */
    public static function trackEventScript(string $eventName, array $payload = [], array $entities = []): string
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $entitiesJson = json_encode(['entities' => $entities], JSON_UNESCAPED_SLASHES);

        return "<script>window.tx && tx.track('{$eventName}', {$payloadJson}, {$entitiesJson});</script>";
    }

    /**
     * Generate data layer push for GTM integration.
     */
    public static function gtmDataLayerPush(string $eventName, array $data = []): string
    {
        $data['event'] = 'tx_' . $eventName;
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        return "<script>window.dataLayer = window.dataLayer || []; dataLayer.push({$json});</script>";
    }

    /**
     * Validate UUID format.
     */
    protected static function isValidUuid(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }
}
