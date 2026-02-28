<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceClient;
use App\Services\Analytics\RedisAnalyticsService;
use App\Services\GeoIpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketplaceTrackingController extends Controller
{
    protected RedisAnalyticsService $redisAnalytics;
    protected ?string $userAgent = null;

    public function __construct(RedisAnalyticsService $redisAnalytics)
    {
        $this->redisAnalytics = $redisAnalytics;
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
        $this->userAgent = $request->userAgent() ?? '';
        $uaParsed = $this->parseUserAgent($this->userAgent);

        // Get location from IP (uses multi-provider fallback: ipgeolocation.io -> ip-api.com -> ipwhois.io)
        $geoIpService = app(GeoIpService::class);
        $location = $geoIpService->getLocation($request->ip());

        // Determine event category
        $eventCategory = $this->determineEventCategory($request->input('event_type'));

        // Resolve marketplace client ID from authenticated client (middleware) or request body
        $authenticatedClient = $request->attributes->get('marketplace_client');
        $clientId = $request->input('marketplace_client_id') ?? $authenticatedClient?->id;

        // Create the tracking event
        $event = CoreCustomerEvent::create([
            'marketplace_event_id' => $request->input('marketplace_event_id'),
            'marketplace_client_id' => $clientId,
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
            'device_type' => $uaParsed['device_type'],
            'device_brand' => $uaParsed['device_brand'],
            'browser' => $uaParsed['browser'],
            'browser_version' => $uaParsed['browser_version'],
            'os' => $uaParsed['os'],
            'os_version' => $uaParsed['os_version'],
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

        // Update CoreCustomer metrics (link visitor to customer if email known)
        $this->updateCoreCustomer($visitorId, $request, $event);

        // INSTANT: Write to Redis for real-time analytics (globe, live visitors)
        $eventId = $request->input('marketplace_event_id');
        if ($eventId) {
            $this->redisAnalytics->trackVisitor(
                (int) $eventId,
                $visitorId,
                $location,
                $request->input('event_type')
            );
        }

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
            // Forward the authenticated marketplace client from middleware
            if ($client = $request->attributes->get('marketplace_client')) {
                $subRequest->attributes->set('marketplace_client', $client);
            }

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
     * Parse user agent string into device/browser/OS info
     */
    protected function parseUserAgent(string $ua): array
    {
        // Device type
        $deviceType = 'desktop';
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|Opera Mini|IEMobile/i', $ua)) {
            $deviceType = 'mobile';
        }

        // Device brand
        $deviceBrand = null;
        if (preg_match('/iPhone|iPad|iPod|Macintosh/i', $ua)) $deviceBrand = 'Apple';
        elseif (preg_match('/Samsung/i', $ua)) $deviceBrand = 'Samsung';
        elseif (preg_match('/Huawei/i', $ua)) $deviceBrand = 'Huawei';
        elseif (preg_match('/Xiaomi|Redmi|POCO/i', $ua)) $deviceBrand = 'Xiaomi';

        // Browser
        $browser = null;
        $browserVersion = null;
        if (preg_match('/Edg(?:e|A|iOS)?\/(\S+)/i', $ua, $m)) { $browser = 'Edge'; $browserVersion = $m[1]; }
        elseif (preg_match('/OPR\/(\S+)/i', $ua, $m)) { $browser = 'Opera'; $browserVersion = $m[1]; }
        elseif (preg_match('/Chrome\/(\S+)/i', $ua, $m)) { $browser = 'Chrome'; $browserVersion = $m[1]; }
        elseif (preg_match('/Firefox\/(\S+)/i', $ua, $m)) { $browser = 'Firefox'; $browserVersion = $m[1]; }
        elseif (preg_match('/Safari\/(\S+)/i', $ua, $m) && preg_match('/Version\/(\S+)/i', $ua, $v)) { $browser = 'Safari'; $browserVersion = $v[1]; }

        // OS
        $os = null;
        $osVersion = null;
        if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $m)) { $os = 'Windows'; $osVersion = $m[1]; }
        elseif (preg_match('/Mac OS X (\d+[._]\d+[._]?\d*)/i', $ua, $m)) { $os = 'macOS'; $osVersion = str_replace('_', '.', $m[1]); }
        elseif (preg_match('/Android (\d+[\.\d]*)/i', $ua, $m)) { $os = 'Android'; $osVersion = $m[1]; }
        elseif (preg_match('/iPhone OS (\d+[._]\d+)/i', $ua, $m)) { $os = 'iOS'; $osVersion = str_replace('_', '.', $m[1]); }
        elseif (preg_match('/Linux/i', $ua)) { $os = 'Linux'; }

        return [
            'device_type' => $deviceType,
            'device_brand' => $deviceBrand,
            'browser' => $browser,
            'browser_version' => $browserVersion ? explode('.', $browserVersion)[0] . '.' . (explode('.', $browserVersion)[1] ?? '0') : null,
            'os' => $os,
            'os_version' => $osVersion,
        ];
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
                'device_type' => $this->parseUserAgent($request->userAgent() ?? '')['device_type'],
                'browser' => $this->parseUserAgent($request->userAgent() ?? '')['browser'],
                'os' => $this->parseUserAgent($request->userAgent() ?? '')['os'],
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
     * Update CoreCustomer metrics from tracking event
     */
    protected function updateCoreCustomer(string $visitorId, Request $request, CoreCustomerEvent $event): void
    {
        try {
            // Find customer by visitor_id
            $customer = CoreCustomer::where('visitor_id', $visitorId)->first();

            // If not found by visitor, try linking via email in request
            $email = $request->input('email') ?? $request->input('customer_email');
            if (!$customer && $email) {
                $customer = CoreCustomer::findByEmail($email);
                if ($customer && !$customer->visitor_id) {
                    $customer->update(['visitor_id' => $visitorId]);
                }
            }

            if (!$customer) {
                // Create minimal customer from visitor data (will be enriched on purchase)
                $customer = CoreCustomer::create([
                    'visitor_id' => $visitorId,
                    'ip_address' => $request->ip(),
                    'device_type' => $event->device_type,
                    'browser' => $event->browser,
                    'os' => $event->os,
                    'country_code' => $event->country_code,
                    'city' => $event->city,
                    'region' => $event->region,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'first_source' => $this->determineSource($request),
                    'first_medium' => $request->input('utm_medium'),
                    'first_campaign' => $request->input('utm_campaign'),
                    'first_referrer' => $request->input('referrer'),
                    'first_landing_page' => $request->input('page_url'),
                    'first_utm_source' => $request->input('utm_source'),
                    'first_utm_medium' => $request->input('utm_medium'),
                    'first_utm_campaign' => $request->input('utm_campaign'),
                    'first_gclid' => $request->input('gclid'),
                    'first_fbclid' => $request->input('fbclid'),
                    'first_ttclid' => $request->input('ttclid'),
                ]);
            }

            // Link event to customer
            $event->update(['customer_id' => $customer->id]);

            // Update visit metrics
            $customer->recordVisit([
                'referrer' => $request->input('referrer'),
                'utm_source' => $request->input('utm_source'),
                'utm_medium' => $request->input('utm_medium'),
                'utm_campaign' => $request->input('utm_campaign'),
                'gclid' => $request->input('gclid'),
                'fbclid' => $request->input('fbclid'),
                'ttclid' => $request->input('ttclid'),
            ]);
        } catch (\Exception $e) {
            // Don't fail the tracking request if customer update fails
            \Log::warning('Failed to update CoreCustomer from tracking: ' . $e->getMessage());
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
