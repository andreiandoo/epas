<?php

namespace App\Services\Integrations\Ga4;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the GA4 Measurement Protocol.
 *
 * Purpose: server-side fallback for GA4 events when the browser pixel
 * is blocked (adblockers, iOS 14.5+ ATT, cookie deletion). This is the
 * GA equivalent of Meta CAPI or TikTok Events API.
 *
 * Docs: https://developers.google.com/analytics/devguides/collection/protocol/ga4
 *
 * NOTE ON DEDUPE: GA4 does NOT support explicit event_id-based dedup
 * between the browser gtag call and the Measurement Protocol call — a
 * quirk of MP that differs from Meta CAPI / TikTok EAPI. If both
 * browser (gtag) and server (this service) fire the SAME event, GA4
 * will count both. We add a `x_epas_source: 'server'` custom parameter
 * so organizers can filter/segment in Explorations if double-counting
 * shows up in production. This is a known limitation, not a bug.
 */
class Ga4MeasurementProtocolService
{
    protected string $collectUrl = 'https://www.google-analytics.com/mp/collect';
    protected string $debugCollectUrl = 'https://www.google-analytics.com/debug/mp/collect';

    /**
     * Send a single event to GA4 Measurement Protocol.
     *
     * @param string $measurementId  e.g. "G-XXXXXXXXXX"
     * @param string $apiSecret      GA4 property MP API secret
     * @param string $clientId       Stable per-visitor identifier (visitor_id / _ga cookie value / uuid)
     * @param string $eventName      GA4 event name (page_view, view_item, purchase, generate_lead, etc.)
     * @param array  $params         Event params (value, currency, items, transaction_id, engagement_time_msec…)
     * @param array  $userProperties Optional user-scoped properties
     * @param bool   $debug          Send to debug endpoint (validates payload, does NOT record hit)
     */
    public function sendEvent(
        string $measurementId,
        string $apiSecret,
        string $clientId,
        string $eventName,
        array $params = [],
        array $userProperties = [],
        bool $debug = false
    ): array {
        $endpoint = $debug ? $this->debugCollectUrl : $this->collectUrl;

        $payload = [
            'client_id' => $clientId,
            'events' => [[
                'name' => $eventName,
                'params' => array_merge([
                    // Required-ish param; GA4 needs at least one engagement signal
                    // to attribute the event to a session. 100ms is a conservative
                    // minimum used by MP examples.
                    'engagement_time_msec' => 100,
                    // Custom parameter so organizers can filter server-side events
                    // in Explorations when reconciling numbers against gtag.js.
                    'x_epas_source' => 'server',
                ], $params),
            ]],
        ];

        if (!empty($userProperties)) {
            $payload['user_properties'] = $this->mapUserProperties($userProperties);
        }

        try {
            $response = Http::timeout(5)->connectTimeout(3)
                ->asJson()
                ->post($endpoint . '?' . http_build_query([
                    'measurement_id' => $measurementId,
                    'api_secret' => $apiSecret,
                ]), $payload);

            // Non-debug endpoint returns 204 on success with no body.
            // Debug endpoint returns 200 with a JSON validation report.
            $ok = $response->successful();

            return [
                'success' => $ok,
                'status' => $response->status(),
                'body' => $debug ? $response->json() : null,
            ];
        } catch (\Throwable $e) {
            Log::error('GA4 MP send failed', [
                'measurement_id' => $measurementId,
                'event_name' => $eventName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 0,
                'body' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify that a measurement_id + api_secret pair works by sending a
     * throwaway page_view to the /debug/ endpoint. Returns the raw
     * validationMessages array so the admin form can surface the exact
     * mismatch (e.g. "Bad API Secret").
     */
    public function testCredentials(string $measurementId, string $apiSecret): array
    {
        $result = $this->sendEvent(
            measurementId: $measurementId,
            apiSecret: $apiSecret,
            clientId: 'test-' . bin2hex(random_bytes(6)),
            eventName: 'page_view',
            params: ['page_location' => 'https://example.com/test'],
            debug: true,
        );

        // Debug endpoint returns { validationMessages: [], … }.
        // Empty array = credentials + payload are both valid.
        $messages = $result['body']['validationMessages'] ?? null;
        $isValid = $result['success'] && is_array($messages) && count($messages) === 0;

        return [
            'valid' => $isValid,
            'status' => $result['status'],
            'validation_messages' => $messages,
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Map our internal event names to GA4 recommended event names.
     * Anything not in the map is passed through untouched so an
     * organizer's custom event names (see Etapa custom-events) reach GA4
     * as-is.
     *
     * Reference: https://developers.google.com/analytics/devguides/collection/ga4/reference/events
     */
    public function mapEventName(string $internalName): string
    {
        return match ($internalName) {
            'page_view' => 'page_view',
            'view_item' => 'view_item',
            'add_to_cart' => 'add_to_cart',
            'begin_checkout' => 'begin_checkout',
            'purchase' => 'purchase',
            'sign_up' => 'sign_up',
            'lead' => 'generate_lead',
            'search' => 'search',
            'add_payment_info' => 'add_payment_info',
            'add_shipping_info' => 'add_shipping_info',
            default => $internalName,
        };
    }

    /**
     * User properties in GA4 MP have a specific shape:
     * { key: { value: "..." } }
     */
    protected function mapUserProperties(array $userProperties): array
    {
        $mapped = [];
        foreach ($userProperties as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $mapped[$key] = ['value' => (string) $value];
        }
        return $mapped;
    }
}
