<?php

namespace App\Services\AdsCampaign;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignCreative;
use App\Models\AdsCampaign\AdsCampaignTargeting;
use App\Models\AdsCampaign\AdsOptimizationLog;
use App\Models\AdsCampaign\AdsPlatformCampaign;
use App\Models\AdsCampaign\AdsServiceRequest;
use App\Models\User;
use App\Notifications\AdsCampaignNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdsCampaignManager
{
    public function __construct(
        protected FacebookMarketingService $facebookService,
        protected GoogleAdsCampaignService $googleService,
        protected MetricsAggregator $metricsAggregator,
        protected CampaignOptimizer $optimizer,
        protected BudgetAllocator $budgetAllocator,
    ) {}

    // ==========================================
    // CAMPAIGN LIFECYCLE
    // ==========================================

    /**
     * Create a campaign from a service request
     */
    public function createFromServiceRequest(AdsServiceRequest $request, User $admin, array $overrides = []): AdsCampaign
    {
        return DB::transaction(function () use ($request, $admin, $overrides) {
            $campaign = AdsCampaign::create(array_merge([
                'tenant_id' => $request->tenant_id,
                'event_id' => $request->event_id,
                'service_request_id' => $request->id,
                'marketplace_client_id' => $request->marketplace_client_id,
                'name' => $request->name,
                'description' => $request->brief,
                'objective' => 'conversions',
                'total_budget' => $request->budget,
                'currency' => $request->currency,
                'target_platforms' => $request->target_platforms,
                'status' => AdsCampaign::STATUS_DRAFT,
                'auto_optimize' => true,
                'retargeting_enabled' => true,
                'retargeting_config' => [
                    'website_visitors' => true,
                    'cart_abandoners' => true,
                    'past_attendees' => true,
                    'lookalike_percentage' => 2,
                ],
                'utm_source' => 'tixello_ads',
                'utm_medium' => 'paid_social',
                'utm_campaign' => Str::slug($request->name),
                'created_by' => $admin->id,
            ], $overrides));

            // Build default targeting from audience hints
            $this->createDefaultTargeting($campaign, $request->audience_hints ?? []);

            // Update service request status
            $request->update(['status' => AdsServiceRequest::STATUS_IN_PROGRESS]);

            Log::info("Campaign created from service request", [
                'campaign_id' => $campaign->id,
                'request_id' => $request->id,
            ]);

            return $campaign;
        });
    }

    /**
     * Launch campaign across all target platforms
     */
    public function launch(AdsCampaign $campaign): void
    {
        if (!$campaign->canLaunch()) {
            throw new \Exception('Campaign cannot be launched. Ensure creatives are approved and targeting is set.');
        }

        $campaign->update(['status' => AdsCampaign::STATUS_LAUNCHING]);

        // Allocate budget across platforms
        $allocations = $this->budgetAllocator->allocate($campaign);

        // Get approved creatives (for A/B testing, get both variants)
        $creatives = $campaign->creatives()->approved()->get();
        $targeting = $campaign->targeting()->first();

        if (!$targeting) {
            throw new \Exception('No targeting configuration found for campaign.');
        }

        $errors = [];

        foreach ($campaign->target_platforms as $platform) {
            foreach ($creatives as $creative) {
                try {
                    $platformCampaign = match ($platform) {
                        'facebook', 'instagram' => $this->facebookService->createCampaign(
                            $campaign, $targeting, $creative, $platform
                        ),
                        'google' => $this->googleService->createCampaign(
                            $campaign, $targeting, $creative
                        ),
                        default => throw new \Exception("Unsupported platform: {$platform}"),
                    };

                    // Update allocated budget
                    if (isset($allocations[$platform])) {
                        $platformCampaign->update(['budget_allocated' => $allocations[$platform]]);
                    }

                    // Activate the campaign
                    $this->activatePlatformCampaign($platformCampaign);

                } catch (\Exception $e) {
                    $errors[] = "{$platform}: {$e->getMessage()}";
                    Log::error("Failed to launch on {$platform}", [
                        'campaign_id' => $campaign->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Generate tracking URL
        if ($campaign->event) {
            $baseUrl = $campaign->event->event_website_url ?? $campaign->event->website_url ?? '';
            if ($baseUrl) {
                $campaign->update(['tracking_url' => $campaign->generateTrackingUrl($baseUrl)]);
            }
        }

        // Update campaign status
        $activePlatforms = $campaign->platformCampaigns()->where('status', 'active')->count();
        if ($activePlatforms > 0) {
            $campaign->update([
                'status' => AdsCampaign::STATUS_ACTIVE,
                'status_notes' => !empty($errors) ? "Partial launch. Errors: " . implode('; ', $errors) : null,
            ]);

            // Update service request
            if ($campaign->service_request_id) {
                $campaign->serviceRequest->update(['status' => AdsServiceRequest::STATUS_COMPLETED]);
            }

            // Notify the organizer that campaign is live
            $this->notifyOrganizer($campaign, 'campaign_launched');
        } else {
            $campaign->update([
                'status' => AdsCampaign::STATUS_FAILED,
                'status_notes' => "All platforms failed: " . implode('; ', $errors),
            ]);
        }
    }

    /**
     * Pause a running campaign across all platforms
     */
    public function pause(AdsCampaign $campaign, ?User $user = null, ?string $reason = null): void
    {
        foreach ($campaign->platformCampaigns()->active()->get() as $platformCampaign) {
            try {
                match ($platformCampaign->platform) {
                    'facebook', 'instagram' => $this->facebookService->pauseCampaign($platformCampaign),
                    'google' => $this->googleService->pauseCampaign($platformCampaign),
                };
            } catch (\Exception $e) {
                Log::warning("Failed to pause on {$platformCampaign->platform}", ['error' => $e->getMessage()]);
            }
        }

        $campaign->update([
            'status' => AdsCampaign::STATUS_PAUSED,
            'status_notes' => $reason,
        ]);

        AdsOptimizationLog::create([
            'campaign_id' => $campaign->id,
            'action_type' => 'campaign_pause',
            'description' => $reason ?? 'Campaign paused',
            'source' => $user ? 'manual' : 'auto',
            'performed_by' => $user?->id,
        ]);

        $this->notifyOrganizer($campaign, 'campaign_paused', ['reason' => $reason ?? 'Manual pause']);
    }

    /**
     * Resume a paused campaign
     */
    public function resume(AdsCampaign $campaign, ?User $user = null): void
    {
        foreach ($campaign->platformCampaigns()->where('status', 'paused')->get() as $platformCampaign) {
            try {
                $this->activatePlatformCampaign($platformCampaign);
            } catch (\Exception $e) {
                Log::warning("Failed to resume on {$platformCampaign->platform}", ['error' => $e->getMessage()]);
            }
        }

        $campaign->update(['status' => AdsCampaign::STATUS_ACTIVE, 'status_notes' => null]);

        AdsOptimizationLog::create([
            'campaign_id' => $campaign->id,
            'action_type' => 'campaign_resume',
            'description' => 'Campaign resumed',
            'source' => $user ? 'manual' : 'auto',
            'performed_by' => $user?->id,
        ]);
    }

    /**
     * Complete a campaign (stop ads, generate final report)
     */
    public function complete(AdsCampaign $campaign): void
    {
        // Pause all active platform campaigns
        foreach ($campaign->platformCampaigns()->active()->get() as $platformCampaign) {
            try {
                match ($platformCampaign->platform) {
                    'facebook', 'instagram' => $this->facebookService->pauseCampaign($platformCampaign),
                    'google' => $this->googleService->pauseCampaign($platformCampaign),
                };
                $platformCampaign->update(['status' => 'ended']);
            } catch (\Exception $e) {
                Log::warning("Failed to stop platform campaign", ['error' => $e->getMessage()]);
            }
        }

        // Final metrics sync
        $this->metricsAggregator->syncCampaignMetrics($campaign);

        // Recalculate aggregates
        $campaign->recalculateAggregates();

        $campaign->update(['status' => AdsCampaign::STATUS_COMPLETED]);

        $this->notifyOrganizer($campaign, 'campaign_completed');
    }

    // ==========================================
    // OPTIMIZATION LOOP
    // ==========================================

    /**
     * Run optimization for all active campaigns (called by scheduler)
     */
    public function optimizeActiveCampaigns(): void
    {
        $campaigns = AdsCampaign::running()
            ->where('auto_optimize', true)
            ->with(['platformCampaigns', 'metrics'])
            ->get();

        foreach ($campaigns as $campaign) {
            try {
                // Sync latest metrics first
                $this->metricsAggregator->syncCampaignMetrics($campaign);

                // Run optimization
                $this->optimizer->optimize($campaign);

                // Check budget exhaustion
                if ($campaign->isBudgetExhausted()) {
                    $this->notifyOrganizer($campaign, 'budget_alert', [
                        'percent_used' => 100,
                        'remaining' => 0,
                    ]);
                    $this->complete($campaign);
                    continue;
                }

                // Budget warning at 80%
                if ($campaign->total_budget > 0) {
                    $usedPct = ((float) $campaign->spent_budget / (float) $campaign->total_budget) * 100;
                    $remaining = (float) $campaign->total_budget - (float) $campaign->spent_budget;
                    if ($usedPct >= 80 && $usedPct < 100 && !($campaign->status_notes && str_contains($campaign->status_notes, 'budget_warning_sent'))) {
                        $daysRemaining = $campaign->daily_budget > 0 ? ceil($remaining / (float) $campaign->daily_budget) : null;
                        $this->notifyOrganizer($campaign, 'budget_alert', [
                            'percent_used' => round($usedPct),
                            'remaining' => $remaining,
                            'days_remaining' => $daysRemaining,
                        ]);
                        $campaign->update(['status_notes' => ($campaign->status_notes ? $campaign->status_notes . ' | ' : '') . 'budget_warning_sent']);
                    }
                }

                // Check end date
                if ($campaign->end_date && $campaign->end_date->isPast()) {
                    $this->complete($campaign);
                    continue;
                }

                // A/B test evaluation (after sufficient data)
                if ($campaign->ab_testing_enabled && !$campaign->ab_test_winner) {
                    $this->evaluateAbTest($campaign);
                }

                $campaign->update(['last_optimized_at' => now()]);

            } catch (\Exception $e) {
                Log::error("Campaign optimization failed", [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Evaluate A/B test and declare winner
     */
    protected function evaluateAbTest(AdsCampaign $campaign): void
    {
        // Require at least 3 days of data and 1000 impressions per variant
        $metricsA = $campaign->getVariantAMetrics();
        $metricsB = $campaign->getVariantBMetrics();

        $minImpressions = 1000;
        if ($metricsA['impressions'] < $minImpressions || $metricsB['impressions'] < $minImpressions) {
            return; // Not enough data
        }

        $winner = $campaign->determineAbTestWinner();
        if (!$winner) return;

        $loser = $winner === 'A' ? 'B' : 'A';

        // Pause losing variant's platform campaigns
        $campaign->platformCampaigns()
            ->where('variant_label', $loser)
            ->each(function ($pc) {
                try {
                    match ($pc->platform) {
                        'facebook', 'instagram' => $this->facebookService->pauseCampaign($pc),
                        'google' => $this->googleService->pauseCampaign($pc),
                    };
                } catch (\Exception $e) {
                    Log::warning("Failed to pause losing variant", ['error' => $e->getMessage()]);
                }
            });

        // Reallocate budget to winner
        $campaign->platformCampaigns()
            ->where('variant_label', $winner)
            ->each(function ($pc) use ($campaign) {
                $newBudget = $pc->budget_allocated * 2;
                try {
                    match ($pc->platform) {
                        'facebook', 'instagram' => $this->facebookService->updateBudget($pc, $newBudget / 30),
                        'google' => $this->googleService->updateBudget($pc, $newBudget / 30),
                    };
                } catch (\Exception $e) {
                    Log::warning("Failed to update winner budget", ['error' => $e->getMessage()]);
                }
            });

        // Mark winner
        $campaign->update([
            'ab_test_winner' => $winner,
            'ab_test_winner_date' => now(),
        ]);

        $campaign->creatives()->where('variant_label', $winner)->update(['is_winner' => true]);

        AdsOptimizationLog::create([
            'campaign_id' => $campaign->id,
            'action_type' => 'ab_test_winner',
            'description' => "A/B test winner: Variant {$winner}. Budget reallocated from Variant {$loser}.",
            'before_state' => ['variant_a' => $metricsA, 'variant_b' => $metricsB],
            'after_state' => ['winner' => $winner, 'metric' => $campaign->ab_test_metric],
            'source' => 'auto',
        ]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    protected function activatePlatformCampaign(AdsPlatformCampaign $platformCampaign): void
    {
        match ($platformCampaign->platform) {
            'facebook', 'instagram' => $this->facebookService->activateCampaign($platformCampaign),
            'google' => $this->googleService->activateCampaign($platformCampaign),
        };
    }

    protected function createDefaultTargeting(AdsCampaign $campaign, array $audienceHints): void
    {
        $targeting = [
            'campaign_id' => $campaign->id,
            'age_min' => $audienceHints['age_min'] ?? 18,
            'age_max' => $audienceHints['age_max'] ?? 55,
            'genders' => $audienceHints['genders'] ?? ['all'],
            'languages' => $audienceHints['languages'] ?? ['ro', 'en'],
            'automatic_placements' => true,
            'devices' => ['mobile', 'desktop'],
        ];

        // Set locations from hints
        if (!empty($audienceHints['locations'])) {
            $targeting['locations'] = $audienceHints['locations'];
        } else {
            // Default to Romania
            $targeting['locations'] = [
                ['type' => 'country', 'id' => 'RO', 'name' => 'Romania'],
            ];
        }

        // Set interests from hints or defaults based on event
        if (!empty($audienceHints['interests'])) {
            $targeting['interests'] = $audienceHints['interests'];
        }

        AdsCampaignTargeting::create($targeting);
    }

    /**
     * Notify the organizer (tenant owner) about campaign events.
     */
    protected function notifyOrganizer(AdsCampaign $campaign, string $action, array $data = []): void
    {
        try {
            $tenant = $campaign->tenant;
            if (!$tenant) return;

            // Find the tenant owner/primary user to notify
            $notifiable = $tenant->users()->first();
            if (!$notifiable) return;

            $notifiable->notify(new AdsCampaignNotification(
                action: $action,
                campaign: $campaign,
                serviceRequest: $campaign->serviceRequest,
                data: $data,
            ));
        } catch (\Exception $e) {
            Log::warning("Failed to send campaign notification", [
                'campaign_id' => $campaign->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Duplicate a campaign for a new event
     */
    public function duplicate(AdsCampaign $source, ?int $newEventId = null, ?User $admin = null): AdsCampaign
    {
        return DB::transaction(function () use ($source, $newEventId, $admin) {
            $newCampaign = $source->replicate([
                'status', 'spent_budget', 'total_impressions', 'total_clicks',
                'total_conversions', 'total_spend', 'total_revenue',
                'avg_ctr', 'avg_cpc', 'avg_cpm', 'roas', 'cac',
                'ab_test_winner', 'ab_test_winner_date', 'last_optimized_at',
            ]);
            $newCampaign->status = AdsCampaign::STATUS_DRAFT;
            $newCampaign->event_id = $newEventId ?? $source->event_id;
            $newCampaign->name = $source->name . ' (Copy)';
            $newCampaign->created_by = $admin?->id ?? $source->created_by;
            $newCampaign->save();

            // Duplicate targeting
            foreach ($source->targeting as $targeting) {
                $newTargeting = $targeting->replicate();
                $newTargeting->campaign_id = $newCampaign->id;
                $newTargeting->save();
            }

            // Duplicate creatives
            foreach ($source->creatives as $creative) {
                $newCreative = $creative->replicate(['impressions', 'clicks', 'ctr', 'spend', 'conversions', 'is_winner']);
                $newCreative->campaign_id = $newCampaign->id;
                $newCreative->status = 'draft';
                $newCreative->save();
            }

            return $newCampaign;
        });
    }
}
