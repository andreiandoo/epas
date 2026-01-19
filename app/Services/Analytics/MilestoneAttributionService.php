<?php

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\EventMilestone;
use App\Models\Order;
use App\Models\Platform\CoreCustomerEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MilestoneAttributionService
{
    /**
     * Attribute a purchase event to a milestone using time-windowed last-click attribution
     * Accepts either a CoreCustomerEvent or an Order object
     */
    public function attributePurchase(CoreCustomerEvent|Order $purchaseEvent): ?EventMilestone
    {
        // Handle Order objects - convert to attribution data
        if ($purchaseEvent instanceof Order) {
            return $this->attributeOrder($purchaseEvent);
        }

        if ($purchaseEvent->event_type !== CoreCustomerEvent::TYPE_PURCHASE) {
            return null;
        }

        $eventId = $purchaseEvent->event_id ?? $purchaseEvent->marketplace_event_id;
        if (!$eventId) {
            return null;
        }

        // Get active milestones for this event
        $milestones = EventMilestone::forEvent($eventId)
            ->where('is_active', true)
            ->get();

        if ($milestones->isEmpty()) {
            return null;
        }

        $purchaseDate = $purchaseEvent->occurred_at ?? $purchaseEvent->created_at;

        // Priority 1: Match by UTM campaign (most specific)
        if ($purchaseEvent->utm_campaign) {
            foreach ($milestones as $milestone) {
                if ($milestone->matchesUtmParameters(['utm_campaign' => $purchaseEvent->utm_campaign])
                    && $milestone->isWithinAttributionWindow($purchaseDate)) {
                    return $this->assignAttribution($purchaseEvent, $milestone);
                }
            }
        }

        // Priority 2: Match by click ID platform (for paid ads)
        $clickIdPlatform = $purchaseEvent->getClickIdPlatform();
        if ($clickIdPlatform) {
            foreach ($milestones as $milestone) {
                if ($milestone->matchesClickIdPlatform($clickIdPlatform)
                    && $milestone->isWithinAttributionWindow($purchaseDate)) {
                    return $this->assignAttribution($purchaseEvent, $milestone);
                }
            }
        }

        // Priority 3: Match by UTM source
        if ($purchaseEvent->utm_source) {
            foreach ($milestones as $milestone) {
                if ($milestone->matchesUtmParameters(['utm_source' => $purchaseEvent->utm_source])
                    && $milestone->isWithinAttributionWindow($purchaseDate)) {
                    return $this->assignAttribution($purchaseEvent, $milestone);
                }
            }
        }

        // Priority 4: Match by source type for email milestones
        if ($purchaseEvent->utm_medium === 'email' || $purchaseEvent->source === 'email') {
            foreach ($milestones as $milestone) {
                if ($milestone->type === EventMilestone::TYPE_EMAIL
                    && $milestone->isWithinAttributionWindow($purchaseDate)) {
                    return $this->assignAttribution($purchaseEvent, $milestone);
                }
            }
        }

        return null;
    }

    /**
     * Attribute an Order to a milestone
     * Finds the visitor's first touch (page view with UTM/click ID) and uses that for attribution
     */
    protected function attributeOrder(Order $order): ?EventMilestone
    {
        $eventId = $order->marketplace_event_id ?? $order->event_id;
        if (!$eventId) {
            return null;
        }

        // Get active milestones for this event
        $milestones = EventMilestone::forEvent($eventId)
            ->where('is_active', true)
            ->get();

        if ($milestones->isEmpty()) {
            return null;
        }

        $purchaseDate = $order->paid_at ?? $order->created_at;

        // Try to find visitor's tracking data from their session
        // First check order meta for stored UTM/click IDs
        $meta = $order->meta ?? [];
        $utmCampaign = $meta['utm_campaign'] ?? null;
        $utmSource = $meta['utm_source'] ?? null;
        $utmMedium = $meta['utm_medium'] ?? null;
        $gclid = $meta['gclid'] ?? null;
        $fbclid = $meta['fbclid'] ?? null;
        $ttclid = $meta['ttclid'] ?? null;
        $li_fat_id = $meta['li_fat_id'] ?? null;

        // If no UTM in order meta, try to find from visitor's tracking events
        if (!$utmCampaign && !$utmSource && !$gclid && !$fbclid && !$ttclid) {
            // Find the visitor's page view with tracking data for this event
            $visitorTracking = CoreCustomerEvent::where(function ($q) use ($eventId) {
                    $q->where('event_id', $eventId)
                      ->orWhere('marketplace_event_id', $eventId);
                })
                ->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                ->where(function ($q) {
                    $q->whereNotNull('utm_campaign')
                      ->orWhereNotNull('utm_source')
                      ->orWhereNotNull('gclid')
                      ->orWhereNotNull('fbclid')
                      ->orWhereNotNull('ttclid')
                      ->orWhereNotNull('li_fat_id');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            if ($visitorTracking) {
                $utmCampaign = $utmCampaign ?: $visitorTracking->utm_campaign;
                $utmSource = $utmSource ?: $visitorTracking->utm_source;
                $utmMedium = $utmMedium ?: $visitorTracking->utm_medium;
                $gclid = $gclid ?: $visitorTracking->gclid;
                $fbclid = $fbclid ?: $visitorTracking->fbclid;
                $ttclid = $ttclid ?: $visitorTracking->ttclid;
                $li_fat_id = $li_fat_id ?: $visitorTracking->li_fat_id;
            }
        }

        // Priority 1: Match by UTM campaign (most specific)
        if ($utmCampaign) {
            foreach ($milestones as $milestone) {
                if ($milestone->matchesUtmParameters(['utm_campaign' => $utmCampaign])
                    && $milestone->isWithinAttributionWindow($purchaseDate)) {
                    return $this->assignOrderAttribution($order, $milestone);
                }
            }
        }

        // Priority 2: Match by click ID platform (for paid ads)
        $clickIdPlatform = null;
        if ($gclid) $clickIdPlatform = 'google';
        elseif ($fbclid) $clickIdPlatform = 'facebook';
        elseif ($ttclid) $clickIdPlatform = 'tiktok';
        elseif ($li_fat_id) $clickIdPlatform = 'linkedin';

        if ($clickIdPlatform) {
            foreach ($milestones as $milestone) {
                if ($milestone->matchesClickIdPlatform($clickIdPlatform)
                    && $milestone->isWithinAttributionWindow($purchaseDate)) {
                    return $this->assignOrderAttribution($order, $milestone);
                }
            }
        }

        // Priority 3: Match by UTM source
        if ($utmSource) {
            foreach ($milestones as $milestone) {
                if ($milestone->matchesUtmParameters(['utm_source' => $utmSource])
                    && $milestone->isWithinAttributionWindow($purchaseDate)) {
                    return $this->assignOrderAttribution($order, $milestone);
                }
            }
        }

        // Priority 4: Match by source type for email milestones
        if ($utmMedium === 'email') {
            foreach ($milestones as $milestone) {
                if ($milestone->type === EventMilestone::TYPE_EMAIL
                    && $milestone->isWithinAttributionWindow($purchaseDate)) {
                    return $this->assignOrderAttribution($order, $milestone);
                }
            }
        }

        return null;
    }

    /**
     * Assign attribution from Order and update milestone metrics
     */
    protected function assignOrderAttribution(Order $order, EventMilestone $milestone): EventMilestone
    {
        // Store attribution in order meta
        $meta = $order->meta ?? [];
        $meta['attributed_milestone_id'] = $milestone->id;
        $order->meta = $meta;
        $order->save();

        // Update milestone metrics
        $this->updateMilestoneMetricsFromOrders($milestone);

        return $milestone;
    }

    /**
     * Assign attribution and update milestone metrics
     */
    protected function assignAttribution(CoreCustomerEvent $event, EventMilestone $milestone): EventMilestone
    {
        // Update the event with milestone attribution
        $event->attributed_milestone_id = $milestone->id;
        $event->save();

        // Update milestone metrics
        $this->updateMilestoneMetrics($milestone);

        return $milestone;
    }

    /**
     * Update all metrics for a milestone
     */
    public function updateMilestoneMetrics(EventMilestone $milestone): void
    {
        // Count conversions attributed to this milestone
        $attributedEvents = CoreCustomerEvent::where('attributed_milestone_id', $milestone->id)
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->get();

        $conversions = $attributedEvents->count();
        $attributedRevenue = $attributedEvents->sum('event_value');

        // Update milestone
        $milestone->conversions = $conversions;
        $milestone->attributed_revenue = $attributedRevenue;

        // Calculate CAC if budget is set
        if ($milestone->hasBudget()) {
            $milestone->cac = $conversions > 0 ? round($milestone->budget / $conversions, 2) : null;
            $milestone->roi = $milestone->calculateROI();
            $milestone->roas = $milestone->calculateROAS();
        }

        $milestone->metrics_updated_at = now();
        $milestone->save();
    }

    /**
     * Update milestone metrics from Orders (used when attributing directly from Order)
     */
    public function updateMilestoneMetricsFromOrders(EventMilestone $milestone): void
    {
        $eventId = $milestone->event_id;

        // Get orders attributed to this milestone via meta JSON
        $attributedOrders = Order::where(function ($q) use ($eventId) {
                $q->where('event_id', $eventId)
                  ->orWhere('marketplace_event_id', $eventId);
            })
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereJsonContains('meta->attributed_milestone_id', $milestone->id)
            ->get();

        // Also count CoreCustomerEvents for backwards compatibility
        $attributedEvents = CoreCustomerEvent::where('attributed_milestone_id', $milestone->id)
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->get();

        $conversionsFromOrders = $attributedOrders->count();
        $revenueFromOrders = $attributedOrders->sum('total');

        $conversionsFromEvents = $attributedEvents->count();
        $revenueFromEvents = $attributedEvents->sum('event_value');

        // Combine both sources (avoid double counting if both exist)
        $milestone->conversions = max($conversionsFromOrders, $conversionsFromEvents);
        $milestone->attributed_revenue = max($revenueFromOrders, $revenueFromEvents);

        // Calculate CAC if budget is set
        if ($milestone->hasBudget() && $milestone->conversions > 0) {
            $milestone->cac = round($milestone->budget / $milestone->conversions, 2);
            $milestone->roi = $milestone->calculateROI();
            $milestone->roas = $milestone->calculateROAS();
        } else {
            $milestone->cac = null;
            $milestone->roi = null;
            $milestone->roas = null;
        }

        $milestone->metrics_updated_at = now();
        $milestone->save();
    }

    /**
     * Recalculate all milestone metrics for an event
     */
    public function recalculateEventMilestones(Event $event): void
    {
        $milestones = EventMilestone::forEvent($event->id)->get();

        foreach ($milestones as $milestone) {
            $this->updateMilestoneMetrics($milestone);
        }
    }

    /**
     * Run attribution for all unattributed purchase events
     */
    public function attributeUnattributedPurchases(int $eventId, ?int $limit = 1000): int
    {
        $purchases = CoreCustomerEvent::where('event_id', $eventId)
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->whereNull('attributed_milestone_id')
            ->limit($limit)
            ->get();

        $attributedCount = 0;

        foreach ($purchases as $purchase) {
            $milestone = $this->attributePurchase($purchase);
            if ($milestone) {
                $attributedCount++;
            }
        }

        return $attributedCount;
    }

    /**
     * Calculate impact metrics for non-ad milestones (announcements, price changes, etc.)
     */
    public function calculateMilestoneImpact(EventMilestone $milestone): array
    {
        if ($milestone->isAdCampaign()) {
            return $this->calculateAdCampaignImpact($milestone);
        }

        $eventId = $milestone->event_id;
        $startDate = $milestone->start_date;

        // Get baseline: 7 days before milestone
        $baselineStart = $startDate->copy()->subDays(7);
        $baselineEnd = $startDate->copy()->subDay();

        // Get post-milestone: 7 days after milestone
        $postStart = $startDate;
        $postEnd = $startDate->copy()->addDays(7);

        // Calculate baseline metrics
        $baselineMetrics = $this->getPeriodMetrics($eventId, $baselineStart, $baselineEnd);
        $postMetrics = $this->getPeriodMetrics($eventId, $postStart, $postEnd);

        // Calculate changes
        $trafficChange = $baselineMetrics['visitors'] > 0
            ? round((($postMetrics['visitors'] - $baselineMetrics['visitors']) / $baselineMetrics['visitors']) * 100, 0)
            : 0;

        $salesChange = $baselineMetrics['sales'] > 0
            ? round((($postMetrics['sales'] - $baselineMetrics['sales']) / $baselineMetrics['sales']) * 100, 0)
            : 0;

        $revenueChange = $baselineMetrics['revenue'] > 0
            ? round((($postMetrics['revenue'] - $baselineMetrics['revenue']) / $baselineMetrics['revenue']) * 100, 0)
            : 0;

        // Update milestone with impact
        $impactMetric = null;
        if (abs($trafficChange) >= abs($salesChange) && abs($trafficChange) >= abs($revenueChange)) {
            $impactMetric = ($trafficChange >= 0 ? '+' : '') . $trafficChange . '% traffic';
        } elseif (abs($salesChange) >= abs($revenueChange)) {
            $impactMetric = ($salesChange >= 0 ? '+' : '') . $salesChange . ' sales';
        } else {
            $impactMetric = ($revenueChange >= 0 ? '+' : '') . $revenueChange . '% revenue';
        }

        $milestone->baseline_value = $baselineMetrics['revenue'];
        $milestone->post_value = $postMetrics['revenue'];
        $milestone->impact_metric = $impactMetric;
        $milestone->metrics_updated_at = now();
        $milestone->save();

        return [
            'baseline' => $baselineMetrics,
            'post' => $postMetrics,
            'changes' => [
                'traffic' => $trafficChange,
                'sales' => $salesChange,
                'revenue' => $revenueChange,
            ],
            'impact_metric' => $impactMetric,
        ];
    }

    /**
     * Calculate impact for ad campaigns
     */
    protected function calculateAdCampaignImpact(EventMilestone $milestone): array
    {
        // For ad campaigns, we use attributed conversions
        $this->updateMilestoneMetrics($milestone);

        return [
            'conversions' => $milestone->conversions,
            'attributed_revenue' => $milestone->attributed_revenue,
            'budget' => $milestone->budget,
            'cac' => $milestone->cac,
            'roi' => $milestone->roi,
            'roas' => $milestone->roas,
        ];
    }

    /**
     * Get metrics for a period
     */
    protected function getPeriodMetrics(int $eventId, Carbon $startDate, Carbon $endDate): array
    {
        $visitors = CoreCustomerEvent::where('event_id', $eventId)
            ->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('visitor_id')
            ->count('visitor_id');

        $sales = Order::where('marketplace_event_id', $eventId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();

        $revenue = Order::where('marketplace_event_id', $eventId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('total');

        return [
            'visitors' => $visitors,
            'sales' => $sales,
            'revenue' => $revenue,
        ];
    }

    /**
     * Get campaign performance comparison
     */
    public function getCampaignComparison(Event $event): array
    {
        $campaigns = EventMilestone::forEvent($event->id)
            ->adCampaigns()
            ->withBudget()
            ->get();

        return $campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'type' => $campaign->type,
                'platform' => $campaign->getTypeLabel(),
                'budget' => $campaign->budget,
                'spend' => $campaign->budget, // Assuming full spend
                'revenue' => $campaign->attributed_revenue,
                'conversions' => $campaign->conversions,
                'cac' => $campaign->cac,
                'roi' => $campaign->roi,
                'roas' => $campaign->roas,
                'is_active' => $campaign->is_active,
                'start_date' => $campaign->start_date->format('M d, Y'),
                'end_date' => $campaign->end_date?->format('M d, Y'),
            ];
        })->sortByDesc('roi')->values()->toArray();
    }

    /**
     * Get total ad spend for an event
     */
    public function getTotalAdSpend(Event $event): float
    {
        return EventMilestone::forEvent($event->id)
            ->adCampaigns()
            ->withBudget()
            ->sum('budget');
    }

    /**
     * Get total attributed revenue from ad campaigns
     */
    public function getTotalAttributedRevenue(Event $event): float
    {
        return EventMilestone::forEvent($event->id)
            ->adCampaigns()
            ->withBudget()
            ->sum('attributed_revenue');
    }

    /**
     * Get blended ROI across all campaigns
     */
    public function getBlendedROI(Event $event): ?float
    {
        $totalSpend = $this->getTotalAdSpend($event);
        $totalRevenue = $this->getTotalAttributedRevenue($event);

        if ($totalSpend <= 0) {
            return null;
        }

        return round((($totalRevenue - $totalSpend) / $totalSpend) * 100, 2);
    }

    /**
     * Suggest optimal budget allocation based on campaign performance
     */
    public function suggestBudgetAllocation(Event $event, float $totalBudget): array
    {
        $campaigns = EventMilestone::forEvent($event->id)
            ->adCampaigns()
            ->withBudget()
            ->where('roi', '>', 0)
            ->orderByDesc('roi')
            ->get();

        if ($campaigns->isEmpty()) {
            return [];
        }

        // Weight by ROI
        $totalRoi = $campaigns->sum('roi');

        return $campaigns->map(function ($campaign) use ($totalBudget, $totalRoi) {
            $weight = $totalRoi > 0 ? ($campaign->roi / $totalRoi) : (1 / $campaigns->count());
            $suggestedBudget = round($totalBudget * $weight, 2);

            return [
                'campaign_id' => $campaign->id,
                'title' => $campaign->title,
                'current_budget' => $campaign->budget,
                'current_roi' => $campaign->roi,
                'suggested_budget' => $suggestedBudget,
                'expected_revenue' => $campaign->roas ? round($suggestedBudget * $campaign->roas, 2) : null,
            ];
        })->toArray();
    }
}
