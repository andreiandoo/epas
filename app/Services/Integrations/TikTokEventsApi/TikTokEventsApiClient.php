<?php

namespace App\Services\Integrations\TikTokEventsApi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the TikTok Events API v1.3.
 *
 * Distinct from the tenant-scoped App\Services\Integrations\TikTokAds\TikTokAdsService
 * (which is coupled to tiktok_ads_connections rows and the tenant/Shop flow).
 * This client takes credentials directly, so it can be driven from a
 * tracking_integrations row scoped to either a marketplace_client or a
 * marketplace_organizer without any extra table plumbing.
 *
 * Docs: https://business-api.tiktok.com/portal/docs?id=1739584716533249
 *
 * Dedupe: pass the same event_id used by the browser pixel to prevent
 * double-counting (TikTok, unlike GA4 MP, honors event_id for dedup).
 */
class TikTokEventsApiClient
{
    protected string $endpoint = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    /**
     * Send a single event.
     *
     * @param string $pixelCode     TikTok Pixel ID (e.g. "CXXXXXXXXXXXXXXXXX")
     * @param string $accessToken   Long-lived access token from Events Manager → Settings
     * @param string $eventName     TikTok event name (ViewContent, AddToCart, Purchase, CompleteRegistration…)
     * @param string $eventId       Event ID for browser↔server dedup
     * @param array  $userData      Hashed PII + identifiers (email, phone, external_id, ttclid, ttp, ip, ua…)
     * @param array  $properties    Event-specific fields (value, currency, contents, content_ids…)
     * @param array  $page          {url, referrer}
     * @param ?string $testEventCode  When set, event routes to Events Manager → Test Events sandbox.
     */
    public function sendEvent(
        string $pixelCode,
        string $accessToken,
        string $eventName,
        string $eventId,
        array $userData = [],
        array $properties = [],
        array $page = [],
        ?string $testEventCode = null
    ): array {
        $data = [
            'event' => $eventName,
            'event_id' => $eventId,
            'event_time' => time(),
            'user' => $this->buildUser($userData),
        ];

        if (!empty($properties)) {
            $data['properties'] = $properties;
        }

        if (!empty($page)) {
            $data['page'] = $page;
        }

        $payload = [
            'event_source' => 'web',
            'event_source_id' => $pixelCode,
            'data' => [$data],
        ];

        if (filled($testEventCode)) {
            $payload['test_event_code'] = $testEventCode;
        }

        try {
            $response = Http::timeout(5)->connectTimeout(3)
                ->withHeaders([
                    'Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint, $payload);

            $body = $response->json();
            $code = (int) ($body['code'] ?? -1);
            $ok = $response->successful() && $code === 0;

            return [
                'success' => $ok,
                'status' => $response->status(),
                'code' => $code,
                'message' => $body['message'] ?? null,
                'request_id' => $body['request_id'] ?? null,
                'events_received' => $body['data']['received'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('TikTok EAPI send failed', [
                'pixel' => $pixelCode,
                'event_name' => $eventName,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 0,
                'code' => -1,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify pixel_code + access_token by sending a Test event to
     * TikTok's Test Events sandbox. Returns whether TikTok accepted
     * the credentials (not whether the event shows up — that requires
     * a matching test_event_code configured in Events Manager).
     */
    public function testCredentials(string $pixelCode, string $accessToken): array
    {
        $result = $this->sendEvent(
            pixelCode: $pixelCode,
            accessToken: $accessToken,
            eventName: 'ViewContent',
            eventId: 'test_' . bin2hex(random_bytes(6)),
            userData: [],
            properties: ['value' => 0, 'currency' => 'USD'],
            testEventCode: 'TEST00000',
        );

        return [
            'valid' => $result['success'],
            'status' => $result['status'],
            'code' => $result['code'],
            'message' => $result['message'],
        ];
    }

    /**
     * Map our internal event names to TikTok event names.
     * Unmapped names pass through so custom events reach TikTok as-is.
     */
    public function mapEventName(string $internalName): string
    {
        return match ($internalName) {
            'page_view' => 'ViewContent',
            'view_item' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'begin_checkout' => 'InitiateCheckout',
            'purchase' => 'CompletePayment',
            'sign_up' => 'CompleteRegistration',
            'lead' => 'SubmitForm',
            'search' => 'Search',
            default => $internalName,
        };
    }

    /**
     * Build the `user` object per TikTok's spec.
     *
     * PII fields (email, phone, external_id) MUST be SHA-256 hashed
     * before send — TikTok rejects unhashed values. Non-PII fields
     * (ttclid, ttp, ip, user_agent) go through as-is.
     */
    protected function buildUser(array $userData): array
    {
        $out = [];

        foreach (['email', 'phone', 'external_id'] as $key) {
            if (!empty($userData[$key])) {
                $out[$key] = hash('sha256', $this->normalize($key, (string) $userData[$key]));
            }
        }

        foreach (['ttclid', 'ttp', 'ip', 'user_agent', 'locale'] as $key) {
            if (!empty($userData[$key])) {
                $out[$key] = (string) $userData[$key];
            }
        }

        return $out;
    }

    protected function normalize(string $key, string $value): string
    {
        $value = trim(strtolower($value));
        if ($key === 'phone') {
            $value = preg_replace('/[^0-9+]/', '', $value);
        }
        return $value;
    }
}
