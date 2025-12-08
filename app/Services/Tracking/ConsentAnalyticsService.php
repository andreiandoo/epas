<?php

namespace App\Services\Tracking;

use App\Models\CookieConsent;
use App\Models\CookieConsentHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for cookie consent analytics dashboard
 *
 * Provides aggregated statistics and insights about consent behavior
 * for tenant dashboards.
 */
class ConsentAnalyticsService
{
    /**
     * Get consent overview statistics for a tenant
     */
    public function getOverview(int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $query = CookieConsent::forTenant($tenantId)
            ->whereBetween('consented_at', [$startDate, $endDate]);

        $totalConsents = $query->count();
        $activeConsents = (clone $query)->active()->count();
        $withdrawnConsents = CookieConsent::forTenant($tenantId)
            ->whereBetween('withdrawn_at', [$startDate, $endDate])
            ->count();

        // Calculate acceptance rates
        $acceptAllCount = (clone $query)->where('action', CookieConsent::ACTION_ACCEPT_ALL)->count();
        $rejectAllCount = (clone $query)->where('action', CookieConsent::ACTION_REJECT_ALL)->count();
        $customizeCount = (clone $query)->where('action', CookieConsent::ACTION_CUSTOMIZE)->count();

        // Category acceptance rates
        $analyticsAccepted = (clone $query)->where('analytics', true)->count();
        $marketingAccepted = (clone $query)->where('marketing', true)->count();
        $preferencesAccepted = (clone $query)->where('preferences', true)->count();

        return [
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
            'totals' => [
                'total_consents' => $totalConsents,
                'active_consents' => $activeConsents,
                'withdrawn_consents' => $withdrawnConsents,
            ],
            'action_breakdown' => [
                'accept_all' => [
                    'count' => $acceptAllCount,
                    'percentage' => $totalConsents > 0 ? round(($acceptAllCount / $totalConsents) * 100, 2) : 0,
                ],
                'reject_all' => [
                    'count' => $rejectAllCount,
                    'percentage' => $totalConsents > 0 ? round(($rejectAllCount / $totalConsents) * 100, 2) : 0,
                ],
                'customize' => [
                    'count' => $customizeCount,
                    'percentage' => $totalConsents > 0 ? round(($customizeCount / $totalConsents) * 100, 2) : 0,
                ],
            ],
            'category_acceptance' => [
                'analytics' => [
                    'accepted' => $analyticsAccepted,
                    'percentage' => $totalConsents > 0 ? round(($analyticsAccepted / $totalConsents) * 100, 2) : 0,
                ],
                'marketing' => [
                    'accepted' => $marketingAccepted,
                    'percentage' => $totalConsents > 0 ? round(($marketingAccepted / $totalConsents) * 100, 2) : 0,
                ],
                'preferences' => [
                    'accepted' => $preferencesAccepted,
                    'percentage' => $totalConsents > 0 ? round(($preferencesAccepted / $totalConsents) * 100, 2) : 0,
                ],
            ],
        ];
    }

    /**
     * Get consent trends over time (daily aggregates)
     */
    public function getTrends(int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $daily = CookieConsent::forTenant($tenantId)
            ->whereBetween('consented_at', [$startDate, $endDate])
            ->select([
                DB::raw('DATE(consented_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN action = "accept_all" THEN 1 ELSE 0 END) as accept_all'),
                DB::raw('SUM(CASE WHEN action = "reject_all" THEN 1 ELSE 0 END) as reject_all'),
                DB::raw('SUM(CASE WHEN action = "customize" THEN 1 ELSE 0 END) as customize'),
                DB::raw('SUM(CASE WHEN analytics = 1 THEN 1 ELSE 0 END) as analytics_accepted'),
                DB::raw('SUM(CASE WHEN marketing = 1 THEN 1 ELSE 0 END) as marketing_accepted'),
                DB::raw('SUM(CASE WHEN preferences = 1 THEN 1 ELSE 0 END) as preferences_accepted'),
            ])
            ->groupBy(DB::raw('DATE(consented_at)'))
            ->orderBy('date')
            ->get();

        // Get withdrawal trends
        $withdrawals = CookieConsent::forTenant($tenantId)
            ->whereNotNull('withdrawn_at')
            ->whereBetween('withdrawn_at', [$startDate, $endDate])
            ->select([
                DB::raw('DATE(withdrawn_at) as date'),
                DB::raw('COUNT(*) as withdrawals'),
            ])
            ->groupBy(DB::raw('DATE(withdrawn_at)'))
            ->pluck('withdrawals', 'date');

        return $daily->map(function ($day) use ($withdrawals) {
            return [
                'date' => $day->date,
                'total' => $day->total,
                'actions' => [
                    'accept_all' => $day->accept_all,
                    'reject_all' => $day->reject_all,
                    'customize' => $day->customize,
                ],
                'categories' => [
                    'analytics' => $day->analytics_accepted,
                    'marketing' => $day->marketing_accepted,
                    'preferences' => $day->preferences_accepted,
                ],
                'withdrawals' => $withdrawals[$day->date] ?? 0,
            ];
        })->toArray();
    }

    /**
     * Get geographic distribution of consents
     */
    public function getGeographicBreakdown(int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return CookieConsent::forTenant($tenantId)
            ->whereBetween('consented_at', [$startDate, $endDate])
            ->whereNotNull('ip_country')
            ->select([
                'ip_country',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN analytics = 1 THEN 1 ELSE 0 END) as analytics_accepted'),
                DB::raw('SUM(CASE WHEN marketing = 1 THEN 1 ELSE 0 END) as marketing_accepted'),
                DB::raw('AVG(CASE WHEN action = "accept_all" THEN 1 WHEN action = "reject_all" THEN 0 ELSE 0.5 END) as acceptance_rate'),
            ])
            ->groupBy('ip_country')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                return [
                    'country' => $row->ip_country,
                    'total' => $row->total,
                    'analytics_rate' => $row->total > 0 ? round(($row->analytics_accepted / $row->total) * 100, 2) : 0,
                    'marketing_rate' => $row->total > 0 ? round(($row->marketing_accepted / $row->total) * 100, 2) : 0,
                    'acceptance_rate' => round($row->acceptance_rate * 100, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get device breakdown of consents
     */
    public function getDeviceBreakdown(int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return CookieConsent::forTenant($tenantId)
            ->whereBetween('consented_at', [$startDate, $endDate])
            ->whereNotNull('device_type')
            ->select([
                'device_type',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN action = "accept_all" THEN 1 ELSE 0 END) as accept_all'),
                DB::raw('SUM(CASE WHEN action = "reject_all" THEN 1 ELSE 0 END) as reject_all'),
            ])
            ->groupBy('device_type')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                return [
                    'device_type' => $row->device_type,
                    'total' => $row->total,
                    'accept_rate' => $row->total > 0 ? round(($row->accept_all / $row->total) * 100, 2) : 0,
                    'reject_rate' => $row->total > 0 ? round(($row->reject_all / $row->total) * 100, 2) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get consent source breakdown (banner vs settings)
     */
    public function getSourceBreakdown(int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return CookieConsent::forTenant($tenantId)
            ->whereBetween('consented_at', [$startDate, $endDate])
            ->select([
                'consent_source',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN action = "accept_all" THEN 1 ELSE 0 END) as accept_all'),
                DB::raw('SUM(CASE WHEN action = "reject_all" THEN 1 ELSE 0 END) as reject_all'),
                DB::raw('SUM(CASE WHEN action = "customize" THEN 1 ELSE 0 END) as customize'),
            ])
            ->groupBy('consent_source')
            ->get()
            ->map(function ($row) {
                return [
                    'source' => $row->consent_source ?? 'unknown',
                    'total' => $row->total,
                    'accept_all' => $row->accept_all,
                    'reject_all' => $row->reject_all,
                    'customize' => $row->customize,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent consent activity
     */
    public function getRecentActivity(int $tenantId, int $limit = 50): array
    {
        return CookieConsent::forTenant($tenantId)
            ->with(['customer:id,name,email'])
            ->orderByDesc('consented_at')
            ->limit($limit)
            ->get()
            ->map(function ($consent) {
                return [
                    'id' => $consent->id,
                    'visitor_id' => substr($consent->visitor_id, 0, 8) . '...',
                    'customer' => $consent->customer ? [
                        'id' => $consent->customer->id,
                        'name' => $consent->customer->name,
                    ] : null,
                    'action' => $consent->action,
                    'categories' => [
                        'analytics' => $consent->analytics,
                        'marketing' => $consent->marketing,
                        'preferences' => $consent->preferences,
                    ],
                    'source' => $consent->consent_source,
                    'device' => $consent->device_type,
                    'country' => $consent->ip_country,
                    'consented_at' => $consent->consented_at?->toIso8601String(),
                    'is_valid' => $consent->isValid(),
                ];
            })
            ->toArray();
    }

    /**
     * Get consent change history analytics
     */
    public function getChangeAnalytics(int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $consentIds = CookieConsent::forTenant($tenantId)->pluck('id');

        $changes = CookieConsentHistory::whereIn('cookie_consent_id', $consentIds)
            ->whereBetween('changed_at', [$startDate, $endDate])
            ->select([
                'change_type',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('change_type')
            ->pluck('total', 'change_type')
            ->toArray();

        // Calculate opt-in vs opt-out trends
        $optInCount = CookieConsentHistory::whereIn('cookie_consent_id', $consentIds)
            ->whereBetween('changed_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('previous_analytics', false)->where('new_analytics', true);
                })->orWhere(function ($q) {
                    $q->where('previous_marketing', false)->where('new_marketing', true);
                })->orWhere(function ($q) {
                    $q->where('previous_preferences', false)->where('new_preferences', true);
                });
            })
            ->count();

        $optOutCount = CookieConsentHistory::whereIn('cookie_consent_id', $consentIds)
            ->whereBetween('changed_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('previous_analytics', true)->where('new_analytics', false);
                })->orWhere(function ($q) {
                    $q->where('previous_marketing', true)->where('new_marketing', false);
                })->orWhere(function ($q) {
                    $q->where('previous_preferences', true)->where('new_preferences', false);
                });
            })
            ->count();

        return [
            'change_types' => [
                'initial' => $changes['initial'] ?? 0,
                'update' => $changes['update'] ?? 0,
                'withdrawal' => $changes['withdrawal'] ?? 0,
            ],
            'preference_changes' => [
                'opt_ins' => $optInCount,
                'opt_outs' => $optOutCount,
            ],
        ];
    }

    /**
     * Get consents expiring soon for renewal notifications
     */
    public function getExpiringConsents(int $tenantId, int $daysBeforeExpiry = 30): Collection
    {
        return CookieConsent::forTenant($tenantId)
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($daysBeforeExpiry)])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Get summary statistics for dashboard widget
     */
    public function getDashboardWidget(int $tenantId): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $lastWeek = now()->subWeek()->startOfDay();

        // Today's stats
        $todayTotal = CookieConsent::forTenant($tenantId)
            ->where('consented_at', '>=', $today)
            ->count();

        $todayAcceptRate = CookieConsent::forTenant($tenantId)
            ->where('consented_at', '>=', $today)
            ->where('action', CookieConsent::ACTION_ACCEPT_ALL)
            ->count();

        // Yesterday's stats for comparison
        $yesterdayTotal = CookieConsent::forTenant($tenantId)
            ->whereBetween('consented_at', [$yesterday, $today])
            ->count();

        // Last 7 days
        $weekTotal = CookieConsent::forTenant($tenantId)
            ->where('consented_at', '>=', $lastWeek)
            ->count();

        $weekAcceptAll = CookieConsent::forTenant($tenantId)
            ->where('consented_at', '>=', $lastWeek)
            ->where('action', CookieConsent::ACTION_ACCEPT_ALL)
            ->count();

        // Total active
        $totalActive = CookieConsent::forTenant($tenantId)->active()->count();

        // Expiring in next 7 days
        $expiringSoon = CookieConsent::forTenant($tenantId)
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->count();

        return [
            'today' => [
                'total' => $todayTotal,
                'accept_rate' => $todayTotal > 0 ? round(($todayAcceptRate / $todayTotal) * 100, 1) : 0,
                'change_from_yesterday' => $yesterdayTotal > 0
                    ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1)
                    : null,
            ],
            'week' => [
                'total' => $weekTotal,
                'accept_rate' => $weekTotal > 0 ? round(($weekAcceptAll / $weekTotal) * 100, 1) : 0,
            ],
            'overall' => [
                'active_consents' => $totalActive,
                'expiring_soon' => $expiringSoon,
            ],
        ];
    }
}
