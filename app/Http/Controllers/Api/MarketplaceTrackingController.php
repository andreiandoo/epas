<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class MarketplaceTrackingController extends Controller
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    /**
     * Track an event (page view, add to cart, purchase, etc.)
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|max:50',
            'marketplace_event_id' => 'nullable|integer',
            'marketplace_client_id' => 'nullable|integer',
            'visitor_id' => 'nullable|string|max:64',
            'session_id' => 'nullable|string|max:64',
            'page_url' => 'nullable|string|max:2000',
            'page_path' => 'nullable|string|max:500',
            'page_title' => 'nullable|string|max:255',
            'content_id' => 'nullable|string|max:100',
            'content_type' => 'nullable|string|max:50',
            'content_name' => 'nullable|string|max:255',
            'event_value' => 'nullable|numeric',
            'quantity' => 'nullable|integer',
            'currency' => 'nullable|string|max:3',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'gclid' => 'nullable|string|max:255',
            'fbclid' => 'nullable|string|max:255',
            'ttclid' => 'nullable|string|max:255',
            'referrer' => 'nullable|string|max:2000',
        ]);

        // Generate or use provided visitor/session IDs
        $visitorId = $request->input('visitor_id') ?: $this->generateVisitorId($request);
        $sessionId = $request->input('session_id') ?: Str::uuid()->toString();

        // Parse user agent
        $this->agent->setUserAgent($request->userAgent());

        // Get location from IP (basic - can be enhanced with MaxMind GeoIP)
        $location = $this->getLocationFromIp($request->ip());

        // Determine event category
        $eventCategory = $this->determineEventCategory($request->input('event_type'));

        // Create the tracking event
        $event = CoreCustomerEvent::create([
            'marketplace_event_id' => $request->input('marketplace_event_id'),
            'marketplace_client_id' => $request->input('marketplace_client_id'),
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'event_type' => $request->input('event_type'),
            'event_category' => $eventCategory,
            'event_action' => $request->input('event_action'),
            'event_label' => $request->input('event_label'),
            'event_value' => $request->input('event_value'),
            'page_url' => $request->input('page_url'),
            'page_path' => $request->input('page_path'),
            'page_title' => $request->input('page_title'),
            'content_id' => $request->input('content_id'),
            'content_type' => $request->input('content_type'),
            'content_name' => $request->input('content_name'),
            'quantity' => $request->input('quantity'),
            'currency' => $request->input('currency', 'RON'),
            'referrer' => $request->input('referrer'),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_term' => $request->input('utm_term'),
            'utm_content' => $request->input('utm_content'),
            'gclid' => $request->input('gclid'),
            'fbclid' => $request->input('fbclid'),
            'ttclid' => $request->input('ttclid'),
            'device_type' => $this->getDeviceType(),
            'device_brand' => $this->agent->device() ?: null,
            'browser' => $this->agent->browser() ?: null,
            'browser_version' => $this->agent->version($this->agent->browser()) ?: null,
            'os' => $this->agent->platform() ?: null,
            'os_version' => $this->agent->version($this->agent->platform()) ?: null,
            'screen_width' => $request->input('screen_width'),
            'screen_height' => $request->input('screen_height'),
            'ip_address' => $request->ip(),
            'country_code' => $location['country_code'] ?? null,
            'region' => $location['region'] ?? null,
            'city' => $location['city'] ?? null,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'occurred_at' => now(),
        ]);

        // Update or create session
        $this->updateSession($sessionId, $visitorId, $request, $event);

        return response()->json([
            'success' => true,
            'event_id' => $event->id,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Track multiple events in batch
     */
    public function trackBatch(Request $request): JsonResponse
    {
        $request->validate([
            'events' => 'required|array|max:50',
            'events.*.event_type' => 'required|string|max:50',
        ]);

        $results = [];
        foreach ($request->input('events') as $eventData) {
            // Create a sub-request for each event
            $subRequest = new Request($eventData);
            $subRequest->setUserResolver(fn () => $request->user());
            $subRequest->headers->replace($request->headers->all());
            $subRequest->server->set('REMOTE_ADDR', $request->ip());

            try {
                $response = $this->track($subRequest);
                $results[] = json_decode($response->getContent(), true);
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Get tracking pixel/beacon (1x1 transparent GIF)
     * Useful for email tracking, etc.
     */
    public function pixel(Request $request): \Illuminate\Http\Response
    {
        // Track the event
        $this->track($request);

        // Return 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Generate a consistent visitor ID from request fingerprint
     */
    protected function generateVisitorId(Request $request): string
    {
        $fingerprint = implode('|', [
            $request->ip(),
            $request->userAgent(),
            $request->header('Accept-Language'),
        ]);

        return hash('sha256', $fingerprint);
    }

    /**
     * Get device type from user agent
     */
    protected function getDeviceType(): string
    {
        if ($this->agent->isTablet()) {
            return 'tablet';
        }
        if ($this->agent->isMobile()) {
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Determine event category from event type
     */
    protected function determineEventCategory(string $eventType): string
    {
        return match ($eventType) {
            CoreCustomerEvent::TYPE_PAGE_VIEW,
            CoreCustomerEvent::TYPE_SCROLL,
            CoreCustomerEvent::TYPE_CLICK => CoreCustomerEvent::CATEGORY_NAVIGATION,

            CoreCustomerEvent::TYPE_ADD_TO_CART,
            CoreCustomerEvent::TYPE_BEGIN_CHECKOUT,
            CoreCustomerEvent::TYPE_PURCHASE,
            CoreCustomerEvent::TYPE_REFUND,
            CoreCustomerEvent::TYPE_VIEW_ITEM => CoreCustomerEvent::CATEGORY_ECOMMERCE,

            CoreCustomerEvent::TYPE_SIGN_UP,
            CoreCustomerEvent::TYPE_LOGIN => CoreCustomerEvent::CATEGORY_USER,

            CoreCustomerEvent::TYPE_VIDEO_START,
            CoreCustomerEvent::TYPE_VIDEO_PROGRESS,
            CoreCustomerEvent::TYPE_VIDEO_COMPLETE => CoreCustomerEvent::CATEGORY_MEDIA,

            default => CoreCustomerEvent::CATEGORY_ENGAGEMENT,
        };
    }

    /**
     * Get location data from IP address
     * Note: This is a basic implementation. For production, use MaxMind GeoIP2 or similar.
     */
    protected function getLocationFromIp(string $ip): array
    {
        // Skip for localhost/private IPs
        if (in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return [
                'country_code' => 'RO',
                'region' => null,
                'city' => 'Local',
                'latitude' => null,
                'longitude' => null,
            ];
        }

        // Try to get location from cache or external service
        // For now, return null values - can be enhanced with GeoIP service
        return [
            'country_code' => null,
            'region' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
        ];
    }

    /**
     * Update or create session record
     */
    protected function updateSession(string $sessionId, string $visitorId, Request $request, CoreCustomerEvent $event): void
    {
        $session = CoreSession::where('session_id', $sessionId)->first();

        if (!$session) {
            CoreSession::create([
                'session_id' => $sessionId,
                'visitor_id' => $visitorId,
                'marketplace_event_id' => $request->input('marketplace_event_id'),
                'marketplace_client_id' => $request->input('marketplace_client_id'),
                'started_at' => now(),
                'pageviews' => $event->event_type === CoreCustomerEvent::TYPE_PAGE_VIEW ? 1 : 0,
                'events' => 1,
                'landing_page' => $event->page_url,
                'landing_page_type' => $event->page_type,
                'source' => $this->determineSource($request),
                'medium' => $request->input('utm_medium'),
                'campaign' => $request->input('utm_campaign'),
                'referrer' => $request->input('referrer'),
                'utm_source' => $request->input('utm_source'),
                'utm_medium' => $request->input('utm_medium'),
                'utm_campaign' => $request->input('utm_campaign'),
                'gclid' => $request->input('gclid'),
                'fbclid' => $request->input('fbclid'),
                'ttclid' => $request->input('ttclid'),
                'device_type' => $this->getDeviceType(),
                'browser' => $this->agent->browser() ?: null,
                'os' => $this->agent->platform() ?: null,
                'country_code' => $event->country_code,
                'city' => $event->city,
            ]);
        } else {
            $session->increment('events');
            if ($event->event_type === CoreCustomerEvent::TYPE_PAGE_VIEW) {
                $session->increment('pageviews');
            }
            $session->update([
                'ended_at' => now(),
                'exit_page' => $event->page_url,
                'exit_page_type' => $event->page_type,
                'duration_seconds' => $session->started_at->diffInSeconds(now()),
                'is_bounce' => $session->pageviews <= 1,
            ]);

            // Track conversions
            if ($event->event_type === CoreCustomerEvent::TYPE_PURCHASE) {
                $session->update([
                    'converted' => true,
                    'conversion_value' => $event->event_value,
                    'conversion_type' => 'purchase',
                ]);
            }
        }
    }

    /**
     * Determine traffic source from request
     */
    protected function determineSource(Request $request): string
    {
        if ($request->input('gclid')) return 'google_ads';
        if ($request->input('fbclid')) return 'facebook_ads';
        if ($request->input('ttclid')) return 'tiktok_ads';
        if ($request->input('utm_source')) return $request->input('utm_source');

        $referrer = $request->input('referrer');
        if (!$referrer) return 'direct';

        $host = parse_url($referrer, PHP_URL_HOST);
        if (!$host) return 'direct';

        if (str_contains($host, 'google')) return 'google';
        if (str_contains($host, 'facebook') || str_contains($host, 'fb.')) return 'facebook';
        if (str_contains($host, 'instagram')) return 'instagram';
        if (str_contains($host, 'tiktok')) return 'tiktok';
        if (str_contains($host, 'twitter') || str_contains($host, 'x.com')) return 'twitter';

        return 'referral';
    }
}
