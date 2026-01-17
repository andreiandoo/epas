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
     */
    public function attributePurchase(CoreCustomerEvent $purchaseEvent): ?EventMilestone
    {
        if ($purchaseEvent->event_type !== CoreCustomerEvent::TYPE_PURCHASE) {
            return null;
        }

        $eventId = $purchaseEvent->event_id;
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
