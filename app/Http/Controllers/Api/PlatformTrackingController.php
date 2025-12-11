<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformTrackingService;
use App\Models\Platform\CoreSession;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlatformTrackingController extends Controller
{
    protected PlatformTrackingService $trackingService;

    public function __construct(PlatformTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Receive tracking events from tenant websites
     * POST /api/tracking/events
     */
    public function trackEvents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenantId' => 'required|string',
            'userData' => 'required|array',
            'userData.visitorId' => 'required|string',
            'userData.sessionId' => 'required|string',
            'deviceInfo' => 'required|array',
            'events' => 'required|array|min:1',
            'events.*.eventType' => 'required|string',
            'events.*.timestamp' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Find tenant by ID or slug
            $tenant = Tenant::where('id', $request->tenantId)
                ->orWhere('slug', $request->tenantId)
                ->first();

            $tenantId = $tenant?->id;

            // Build base event data from request - use client-provided device info
            $baseData = [
                'tenant_id' => $tenantId,
                'visitor_id' => $request->input('userData.visitorId'),
                'session_token' => $request->input('userData.sessionId'),
                'email' => $request->input('userData.email'),
                'phone' => $request->input('userData.phone'),
                'first_name' => $request->input('userData.firstName'),
                'last_name' => $request->input('userData.lastName'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $request->input('deviceInfo.deviceType', 'desktop'),
                'device_brand' => $request->input('deviceInfo.deviceBrand'),
                'device_model' => $request->input('deviceInfo.deviceModel'),
                'browser' => $request->input('deviceInfo.browser', 'Unknown'),
                'browser_version' => $request->input('deviceInfo.browserVersion', ''),
                'os' => $request->input('deviceInfo.os', 'Unknown'),
                'os_version' => $request->input('deviceInfo.osVersion', ''),
                'screen_width' => $request->input('deviceInfo.screenWidth'),
                'screen_height' => $request->input('deviceInfo.screenHeight'),
                'viewport_width' => $request->input('deviceInfo.viewportWidth'),
                'viewport_height' => $request->input('deviceInfo.viewportHeight'),
                'is_mobile' => $request->input('deviceInfo.isMobile', false),
                'is_tablet' => $request->input('deviceInfo.isTablet', false),
                'is_desktop' => $request->input('deviceInfo.isDesktop', true),
                'is_bot' => $this->isBot($request->userAgent()),
            ];

            // Geolocate IP if possible
            $geoData = $this->geolocateIp($request->ip());
            if ($geoData) {
                $baseData = array_merge($baseData, $geoData);
            }

            $processedCount = 0;
            $events = $request->input('events', []);

            foreach ($events as $event) {
                $eventData = array_merge($baseData, [
                    'page_url' => $event['pageUrl'] ?? null,
                    'page_title' => $event['pageTitle'] ?? null,
                    'referrer' => $event['referrer'] ?? null,
                    'utm_source' => $event['utmSource'] ?? null,
                    'utm_medium' => $event['utmMedium'] ?? null,
                    'utm_campaign' => $event['utmCampaign'] ?? null,
                    'utm_term' => $event['utmTerm'] ?? null,
                    'utm_content' => $event['utmContent'] ?? null,
                    'gclid' => $event['gclid'] ?? null,
                    'fbclid' => $event['fbclid'] ?? null,
                    'ttclid' => $event['ttclid'] ?? null,
                    'li_fat_id' => $event['liFatId'] ?? null,
                    'time_on_page' => $event['timeOnPage'] ?? null,
                    'scroll_depth' => $event['scrollDepth'] ?? null,
                    'event_data' => $event['eventData'] ?? [],
                ]);

                // Route to appropriate tracking method based on event type
                switch ($event['eventType']) {
                    case 'page_view':
                        $this->trackingService->trackPageView($eventData);
                        break;

                    case 'add_to_cart':
                        $eventData['value'] = $event['value'] ?? null;
                        $eventData['currency'] = $event['currency'] ?? 'USD';
                        $this->trackingService->trackAddToCart($eventData);
                        break;

                    case 'begin_checkout':
                        $eventData['value'] = $event['value'] ?? null;
                        $eventData['currency'] = $event['currency'] ?? 'USD';
                        $this->trackingService->trackBeginCheckout($eventData);
                        break;

                    case 'purchase':
                        $eventData['order_id'] = $event['orderId'] ?? null;
                        $eventData['order_total'] = $event['value'] ?? null;
                        $eventData['currency'] = $event['currency'] ?? 'USD';
                        $eventData['ticket_count'] = $event['eventData']['ticketCount'] ?? null;
                        $eventData['event_id'] = $event['eventData']['eventId'] ?? null;
                        $this->trackingService->trackPurchase($eventData);
                        break;

                    case 'sign_up':
                        $this->trackingService->trackSignUp($eventData);
                        break;

                    case 'view_item':
                    case 'engagement':
                    case 'search':
                    case 'login':
                    case 'identify':
                    default:
                        $this->trackingService->trackEvent($event['eventType'], $eventData);
                        break;
                }

                $processedCount++;
            }

            return response()->json([
                'success' => true,
                'processed' => $processedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Platform tracking error', [
                'error' => $e->getMessage(),
                'tenant_id' => $request->tenantId,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process tracking events',
            ], 500);
        }
    }

    /**
     * Get real-time analytics data
     * GET /api/tracking/realtime
     */
    public function getRealTimeStats(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');

        try {
            $stats = $this->trackingService->getRealTimeStats(
                $tenantId ? (int) $tenantId : null
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get real-time stats', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve analytics data',
            ], 500);
        }
    }

    /**
     * Get active visitors count
     * GET /api/tracking/active-visitors
     */
    public function getActiveVisitors(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');

        try {
            $count = CoreSession::getActiveCount(
                $tenantId ? (int) $tenantId : null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'active_visitors' => $count,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get active visitors count',
            ], 500);
        }
    }

    /**
     * Get recent events stream (for real-time dashboard)
     * GET /api/tracking/events/stream
     */
    public function getEventStream(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $limit = min((int) $request->query('limit', 50), 100);
        $sinceId = $request->query('since_id');

        try {
            $query = CoreCustomerEvent::with('coreCustomer')
                ->when($tenantId, fn($q) => $q->forTenant((int) $tenantId))
                ->when($sinceId, fn($q) => $q->where('id', '>', $sinceId))
                ->orderByDesc('created_at')
                ->limit($limit);

            $events = $query->get()->map(fn($event) => [
                'id' => $event->id,
                'type' => $event->event_type,
                'category' => $event->event_category,
                'page_url' => $event->page_url,
                'page_title' => $event->page_title,
                'value' => $event->conversion_value,
                'source' => $event->getAttributionSource(),
                'location' => implode(', ', array_filter([$event->city, $event->country_code])),
                'device' => $event->device_type,
                'created_at' => $event->created_at->toIso8601String(),
                'time_ago' => $event->created_at->diffForHumans(),
                'customer' => $event->coreCustomer ? [
                    'id' => $event->coreCustomer->id,
                    'is_returning' => $event->coreCustomer->total_visits > 1,
                    'total_orders' => $event->coreCustomer->total_orders ?? 0,
                ] : null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'events' => $events,
                    'last_id' => $events->first()?->id,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get event stream',
            ], 500);
        }
    }

    /**
     * Get customer insights
     * GET /api/tracking/customers/insights
     */
    public function getCustomerInsights(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $days = min((int) $request->query('days', 30), 90);

        try {
            $startDate = now()->subDays($days);

            // Total customers
            $totalCustomers = CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->count();

            // New customers in period
            $newCustomers = CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('first_seen_at', '>=', $startDate)
                ->count();

            // Returning customers (more than 1 visit)
            $returningCustomers = CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('total_visits', '>', 1)
                ->count();

            // Purchasers
            $purchasers = CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('total_orders', '>', 0)
                ->count();

            // High-value customers
            $highValue = CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('total_spent', '>=', 500)
                ->count();

            // Average RFM score
            $avgRfm = CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereNotNull('rfm_score')
                ->avg('rfm_score');

            // Revenue by source
            $revenueBySource = CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('total_spent', '>', 0)
                ->selectRaw("
                    CASE
                        WHEN first_gclid IS NOT NULL THEN 'Google Ads'
                        WHEN first_fbclid IS NOT NULL THEN 'Facebook Ads'
                        WHEN first_ttclid IS NOT NULL THEN 'TikTok Ads'
                        WHEN first_li_fat_id IS NOT NULL THEN 'LinkedIn Ads'
                        WHEN first_utm_source IS NOT NULL THEN first_utm_source
                        ELSE 'Direct/Organic'
                    END as source,
                    SUM(total_spent) as revenue,
                    COUNT(*) as customers
                ")
                ->groupBy('source')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get();

            // Customer segments
            $segments = [
                'champions' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->where('rfm_score', '>=', 12)->count(),
                'loyal' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->whereBetween('rfm_score', [9, 11])->count(),
                'promising' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->whereBetween('rfm_score', [6, 8])->count(),
                'at_risk' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->whereBetween('rfm_score', [3, 5])->count(),
                'lost' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->where('rfm_score', '<', 3)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'totals' => [
                        'total_customers' => $totalCustomers,
                        'new_customers' => $newCustomers,
                        'returning_customers' => $returningCustomers,
                        'purchasers' => $purchasers,
                        'high_value' => $highValue,
                    ],
                    'metrics' => [
                        'average_rfm_score' => round($avgRfm ?? 0, 1),
                        'conversion_rate' => $totalCustomers > 0
                            ? round(($purchasers / $totalCustomers) * 100, 2)
                            : 0,
                        'returning_rate' => $totalCustomers > 0
                            ? round(($returningCustomers / $totalCustomers) * 100, 2)
                            : 0,
                    ],
                    'revenue_by_source' => $revenueBySource,
                    'segments' => $segments,
                    'period_days' => $days,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get customer insights',
            ], 500);
        }
    }

    /**
     * Detect if request is from a bot based on user agent
     */
    protected function isBot(?string $userAgent): bool
    {
        if (!$userAgent) {
            return false;
        }

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'search', 'fetch',
            'googlebot', 'bingbot', 'yandex', 'baidu', 'duckduck',
            'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'whatsapp', 'telegram', 'slack', 'discord',
            'headless', 'phantom', 'selenium', 'puppeteer',
            'curl', 'wget', 'python', 'java/', 'perl', 'ruby',
        ];

        $userAgentLower = strtolower($userAgent);

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Geolocate IP address
     */
    protected function geolocateIp(string $ip): ?array
    {
        // Skip localhost/private IPs
        if (in_array($ip, ['127.0.0.1', '::1']) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false) {
            return null;
        }

        try {
            // Use a simple GeoIP service (could be replaced with MaxMind or similar)
            // For now, return null - would need GeoIP database integration
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
