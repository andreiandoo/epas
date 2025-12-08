<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\CookieConsent;
use App\Models\CookieConsentHistory;
use App\Services\Tracking\ConsentAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class CookieConsentController extends Controller
{
    /**
     * Get cookie consent status for a visitor/customer
     */
    public function getConsent(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $visitorId = $request->query('visitor_id');
        $customerId = $request->attributes->get('customer')?->id;

        if (!$visitorId && !$customerId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_consent' => false,
                    'consent' => null,
                ],
            ]);
        }

        $consent = CookieConsent::where('tenant_id', $tenant->id)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $visitorId, fn($q) => $q->where('visitor_id', $visitorId))
            ->whereNull('withdrawn_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$consent) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_consent' => false,
                    'consent' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_consent' => true,
                'consent' => [
                    'necessary' => $consent->necessary,
                    'analytics' => $consent->analytics,
                    'marketing' => $consent->marketing,
                    'preferences' => $consent->preferences,
                    'consent_version' => $consent->consent_version,
                    'consented_at' => $consent->consented_at->toIso8601String(),
                    'expires_at' => $consent->expires_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Save cookie consent preferences
     */
    public function saveConsent(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'visitor_id' => 'required|string|max:64',
            'necessary' => 'boolean',
            'analytics' => 'required|boolean',
            'marketing' => 'required|boolean',
            'preferences' => 'required|boolean',
            'action' => 'required|in:accept_all,reject_all,customize,update',
            'consent_version' => 'nullable|string|max:20',
            'page_url' => 'nullable|string|max:2048',
            'referrer_url' => 'nullable|string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $customerId = $request->attributes->get('customer')?->id;

        // Check for existing consent
        $existingConsent = CookieConsent::where('tenant_id', $tenant->id)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId, fn($q) => $q->where('visitor_id', $data['visitor_id']))
            ->whereNull('withdrawn_at')
            ->first();

        $ip = $request->ip();
        $userAgent = $request->userAgent();

        // Detect device info from user agent
        $deviceInfo = $this->parseUserAgent($userAgent);

        if ($existingConsent) {
            // Create history record for the change
            CookieConsentHistory::create([
                'cookie_consent_id' => $existingConsent->id,
                'previous_analytics' => $existingConsent->analytics,
                'previous_marketing' => $existingConsent->marketing,
                'previous_preferences' => $existingConsent->preferences,
                'new_analytics' => $data['analytics'],
                'new_marketing' => $data['marketing'],
                'new_preferences' => $data['preferences'],
                'change_type' => CookieConsentHistory::TYPE_UPDATE,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'change_source' => 'banner',
                'changed_at' => now(),
            ]);

            // Update existing consent
            $existingConsent->update([
                'analytics' => $data['analytics'],
                'marketing' => $data['marketing'],
                'preferences' => $data['preferences'],
                'action' => $data['action'],
                'consent_version' => $data['consent_version'] ?? $existingConsent->consent_version,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'page_url' => $data['page_url'] ?? $existingConsent->page_url,
                'updated_at' => now(),
            ]);

            // Link to customer if they logged in
            if ($customerId && !$existingConsent->customer_id) {
                $existingConsent->update(['customer_id' => $customerId]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Consent updated successfully.',
                'data' => [
                    'consent_id' => $existingConsent->id,
                    'expires_at' => $existingConsent->expires_at?->toIso8601String(),
                ],
            ]);
        }

        // Create new consent record
        $consent = CookieConsent::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'visitor_id' => $data['visitor_id'],
            'session_id' => $request->header('X-Session-Id'),
            'necessary' => true,
            'analytics' => $data['analytics'],
            'marketing' => $data['marketing'],
            'preferences' => $data['preferences'],
            'action' => $data['action'],
            'consent_version' => $data['consent_version'] ?? '1.0',
            'ip_address' => $ip,
            'ip_country' => $this->getCountryFromIp($ip),
            'user_agent' => $userAgent,
            'device_type' => $deviceInfo['device_type'],
            'browser' => $deviceInfo['browser'],
            'os' => $deviceInfo['os'],
            'consent_source' => 'banner',
            'page_url' => $data['page_url'],
            'referrer_url' => $data['referrer_url'],
            'legal_basis' => 'consent',
            'consented_at' => now(),
            'expires_at' => now()->addYear(), // 12 months as per GDPR recommendations
        ]);

        // Create initial history record
        CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => null,
            'previous_marketing' => null,
            'previous_preferences' => null,
            'new_analytics' => $data['analytics'],
            'new_marketing' => $data['marketing'],
            'new_preferences' => $data['preferences'],
            'change_type' => CookieConsentHistory::TYPE_INITIAL,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'change_source' => 'banner',
            'changed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consent saved successfully.',
            'data' => [
                'consent_id' => $consent->id,
                'expires_at' => $consent->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Withdraw consent (GDPR right)
     */
    public function withdrawConsent(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $visitorId = $request->input('visitor_id');
        $customerId = $request->attributes->get('customer')?->id;

        if (!$visitorId && !$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Visitor ID or customer authentication required.',
            ], 400);
        }

        $consent = CookieConsent::where('tenant_id', $tenant->id)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $visitorId, fn($q) => $q->where('visitor_id', $visitorId))
            ->whereNull('withdrawn_at')
            ->first();

        if (!$consent) {
            return response()->json([
                'success' => false,
                'message' => 'No active consent found.',
            ], 404);
        }

        // Create withdrawal history record
        CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => $consent->analytics,
            'previous_marketing' => $consent->marketing,
            'previous_preferences' => $consent->preferences,
            'new_analytics' => false,
            'new_marketing' => false,
            'new_preferences' => false,
            'change_type' => CookieConsentHistory::TYPE_WITHDRAWAL,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'change_source' => 'settings',
            'changed_at' => now(),
        ]);

        // Mark consent as withdrawn
        $consent->update([
            'withdrawn_at' => now(),
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consent withdrawn successfully.',
        ]);
    }

    /**
     * Get consent history for a visitor/customer (GDPR transparency)
     */
    public function getConsentHistory(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $visitorId = $request->query('visitor_id');
        $customerId = $request->attributes->get('customer')?->id;

        if (!$visitorId && !$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Visitor ID or customer authentication required.',
            ], 400);
        }

        $consent = CookieConsent::where('tenant_id', $tenant->id)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $visitorId, fn($q) => $q->where('visitor_id', $visitorId))
            ->first();

        if (!$consent) {
            return response()->json([
                'success' => true,
                'data' => [
                    'history' => [],
                ],
            ]);
        }

        $history = CookieConsentHistory::where('cookie_consent_id', $consent->id)
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(fn($record) => [
                'change_type' => $record->change_type,
                'changed_at' => $record->changed_at->toIso8601String(),
                'changes' => $record->getChangesArray(),
                'source' => $record->change_source,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'history' => $history,
            ],
        ]);
    }

    /**
     * Get renewal status for consent
     * GET /api/tenant-client/consent/renewal-status
     */
    public function getRenewalStatus(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $visitorId = $request->query('visitor_id');
        $customerId = $request->attributes->get('customer')?->id;

        if (!$visitorId && !$customerId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'needs_renewal' => false,
                ],
            ]);
        }

        $consent = CookieConsent::where('tenant_id', $tenant->id)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $visitorId, fn($q) => $q->where('visitor_id', $visitorId))
            ->whereNull('withdrawn_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$consent || !$consent->expires_at) {
            return response()->json([
                'success' => true,
                'data' => [
                    'needs_renewal' => false,
                ],
            ]);
        }

        $daysUntilExpiry = now()->diffInDays($consent->expires_at, false);

        return response()->json([
            'success' => true,
            'data' => [
                'needs_renewal' => $daysUntilExpiry <= 30,
                'is_urgent' => $daysUntilExpiry <= 7,
                'expires_at' => $consent->expires_at->toIso8601String(),
                'days_until_expiry' => max(0, $daysUntilExpiry),
            ],
        ]);
    }

    /**
     * Renew consent (extend expiration)
     * POST /api/tenant-client/consent/renew
     */
    public function renewConsent(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $visitorId = $request->input('visitor_id');
        $customerId = $request->attributes->get('customer')?->id;

        if (!$visitorId && !$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Visitor ID or customer authentication required.',
            ], 400);
        }

        $consent = CookieConsent::where('tenant_id', $tenant->id)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $visitorId, fn($q) => $q->where('visitor_id', $visitorId))
            ->whereNull('withdrawn_at')
            ->first();

        if (!$consent) {
            return response()->json([
                'success' => false,
                'message' => 'No active consent found.',
            ], 404);
        }

        // Record renewal in history
        CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => $consent->analytics,
            'previous_marketing' => $consent->marketing,
            'previous_preferences' => $consent->preferences,
            'new_analytics' => $consent->analytics,
            'new_marketing' => $consent->marketing,
            'new_preferences' => $consent->preferences,
            'change_type' => 'renewal',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'change_source' => 'settings',
            'changed_at' => now(),
        ]);

        // Extend expiration
        $consent->update([
            'expires_at' => now()->addYear(),
            'renewal_first_notified_at' => null,
            'renewal_reminder_notified_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consent renewed successfully.',
            'data' => [
                'expires_at' => $consent->expires_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Parse user agent string for device info
     */
    private function parseUserAgent(?string $userAgent): array
    {
        if (!$userAgent) {
            return ['device_type' => null, 'browser' => null, 'os' => null];
        }

        $deviceType = 'desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
            $deviceType = preg_match('/Tablet|iPad/i', $userAgent) ? 'tablet' : 'mobile';
        }

        $browser = 'Unknown';
        if (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        }

        $os = 'Unknown';
        if (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $os = 'iOS';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
        ];
    }

    /**
     * Get country code from IP (simplified - in production use GeoIP service)
     */
    private function getCountryFromIp(?string $ip): ?string
    {
        // In production, integrate with MaxMind GeoIP or similar
        // For now, return null
        return null;
    }

    // =====================================================================
    // CONSENT ANALYTICS ENDPOINTS (Tenant Dashboard)
    // =====================================================================

    /**
     * Get consent analytics overview
     * GET /api/tenant-client/consent/analytics/overview
     */
    public function analyticsOverview(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : null;
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : null;

        $overview = $analyticsService->getOverview($tenant->id, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * Get consent trends over time
     * GET /api/tenant-client/consent/analytics/trends
     */
    public function analyticsTrends(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : null;
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : null;

        $trends = $analyticsService->getTrends($tenant->id, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get geographic breakdown of consents
     * GET /api/tenant-client/consent/analytics/geographic
     */
    public function analyticsGeographic(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : null;
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : null;

        $geographic = $analyticsService->getGeographicBreakdown($tenant->id, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $geographic,
        ]);
    }

    /**
     * Get device breakdown of consents
     * GET /api/tenant-client/consent/analytics/devices
     */
    public function analyticsDevices(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : null;
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : null;

        $devices = $analyticsService->getDeviceBreakdown($tenant->id, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    /**
     * Get consent source breakdown
     * GET /api/tenant-client/consent/analytics/sources
     */
    public function analyticsSources(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : null;
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : null;

        $sources = $analyticsService->getSourceBreakdown($tenant->id, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $sources,
        ]);
    }

    /**
     * Get recent consent activity
     * GET /api/tenant-client/consent/analytics/activity
     */
    public function analyticsActivity(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $limit = min((int) $request->query('limit', 50), 100);

        $activity = $analyticsService->getRecentActivity($tenant->id, $limit);

        return response()->json([
            'success' => true,
            'data' => $activity,
        ]);
    }

    /**
     * Get consent change analytics
     * GET /api/tenant-client/consent/analytics/changes
     */
    public function analyticsChanges(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : null;
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : null;

        $changes = $analyticsService->getChangeAnalytics($tenant->id, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $changes,
        ]);
    }

    /**
     * Get dashboard widget data
     * GET /api/tenant-client/consent/analytics/widget
     */
    public function analyticsWidget(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $widget = $analyticsService->getDashboardWidget($tenant->id);

        return response()->json([
            'success' => true,
            'data' => $widget,
        ]);
    }

    /**
     * Get expiring consents (for renewal notifications)
     * GET /api/tenant-client/consent/analytics/expiring
     */
    public function analyticsExpiring(Request $request, ConsentAnalyticsService $analyticsService): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $days = min((int) $request->query('days', 30), 90);

        $expiring = $analyticsService->getExpiringConsents($tenant->id, $days);

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $expiring->count(),
                'consents' => $expiring->map(function ($consent) {
                    return [
                        'id' => $consent->id,
                        'visitor_id' => substr($consent->visitor_id, 0, 8) . '...',
                        'customer_id' => $consent->customer_id,
                        'expires_at' => $consent->expires_at->toIso8601String(),
                        'days_until_expiry' => now()->diffInDays($consent->expires_at, false),
                    ];
                })->toArray(),
            ],
        ]);
    }
}
