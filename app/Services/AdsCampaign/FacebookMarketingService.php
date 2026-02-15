<?php

namespace App\Services\AdsCampaign;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignCreative;
use App\Models\AdsCampaign\AdsCampaignTargeting;
use App\Models\AdsCampaign\AdsPlatformCampaign;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookMarketingService
{
    protected string $apiVersion = 'v21.0';
    protected string $baseUrl = 'https://graph.facebook.com';

    /**
     * Map Tixello objectives to Facebook campaign objectives
     */
    protected array $objectiveMap = [
        'conversions' => 'OUTCOME_SALES',
        'traffic' => 'OUTCOME_TRAFFIC',
        'awareness' => 'OUTCOME_AWARENESS',
        'engagement' => 'OUTCOME_ENGAGEMENT',
        'leads' => 'OUTCOME_LEADS',
    ];

    /**
     * Map CTA types to Facebook CTA values
     */
    protected array $ctaMap = [
        'GET_TICKETS' => 'GET_SHOWTIMES',
        'BOOK_NOW' => 'BOOK_TRAVEL',
        'LEARN_MORE' => 'LEARN_MORE',
        'SIGN_UP' => 'SIGN_UP',
        'SHOP_NOW' => 'SHOP_NOW',
        'WATCH_MORE' => 'WATCH_MORE',
        'GET_OFFER' => 'GET_OFFER',
    ];

    // ==========================================
    // CAMPAIGN CREATION
    // ==========================================

    /**
     * Create a full campaign on Facebook/Instagram (Campaign → Ad Set → Ad)
     */
    public function createCampaign(
        AdsCampaign $campaign,
        AdsCampaignTargeting $targeting,
        AdsCampaignCreative $creative,
        string $platform = 'facebook'
    ): AdsPlatformCampaign {
        $settings = Setting::current();
        $accessToken = $settings->facebook_access_token;
        $adAccountId = $settings->meta['facebook_ad_account_id'] ?? null;

        if (!$accessToken || !$adAccountId) {
            throw new \Exception('Facebook Marketing API not configured. Set access token and ad account ID in settings.');
        }

        $platformCampaign = AdsPlatformCampaign::create([
            'campaign_id' => $campaign->id,
            'platform' => $platform,
            'variant_label' => $creative->variant_label,
            'status' => 'pending_creation',
            'budget_allocated' => $this->calculatePlatformBudget($campaign, $platform),
            'daily_budget' => $campaign->daily_budget ? $campaign->daily_budget / count($campaign->target_platforms) : null,
        ]);

        try {
            // Step 1: Create Campaign
            $fbCampaignId = $this->createFbCampaign($campaign, $adAccountId, $accessToken);
            $platformCampaign->update(['platform_campaign_id' => $fbCampaignId]);

            // Step 2: Create Ad Set (targeting + budget + schedule)
            $fbAdSetId = $this->createFbAdSet(
                $campaign,
                $targeting,
                $fbCampaignId,
                $adAccountId,
                $accessToken,
                $platform,
                $platformCampaign
            );
            $platformCampaign->update(['platform_adset_id' => $fbAdSetId]);

            // Step 3: Upload Creative
            $fbCreativeId = $this->createFbAdCreative($creative, $adAccountId, $accessToken, $campaign);
            $platformCampaign->update(['platform_creative_id' => $fbCreativeId]);

            // Step 4: Create Ad (links creative to ad set)
            $fbAdId = $this->createFbAd($campaign, $fbAdSetId, $fbCreativeId, $adAccountId, $accessToken);
            $platformCampaign->update([
                'platform_ad_id' => $fbAdId,
                'status' => 'active',
                'launched_at' => now(),
            ]);

            Log::info("Facebook campaign created successfully", [
                'campaign_id' => $campaign->id,
                'platform' => $platform,
                'fb_campaign_id' => $fbCampaignId,
                'fb_adset_id' => $fbAdSetId,
                'fb_ad_id' => $fbAdId,
            ]);

        } catch (\Exception $e) {
            $platformCampaign->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("Facebook campaign creation failed", [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $platformCampaign->fresh();
    }

    /**
     * Create Facebook campaign object
     */
    protected function createFbCampaign(AdsCampaign $campaign, string $adAccountId, string $accessToken): string
    {
        $objective = $this->objectiveMap[$campaign->objective] ?? 'OUTCOME_SALES';

        $payload = [
            'name' => "Tixello: {$campaign->name}",
            'objective' => $objective,
            'status' => 'PAUSED', // Create paused, activate after full setup
            'special_ad_categories' => [],
        ];

        // Campaign budget optimization (CBO)
        if ($campaign->daily_budget) {
            $payload['daily_budget'] = (int) ($campaign->daily_budget * 100); // Facebook uses cents
        }

        // A/B test campaigns
        if ($campaign->ab_testing_enabled) {
            $payload['name'] .= " [A/B Test]";
        }

        $response = Http::post("{$this->baseUrl}/{$this->apiVersion}/act_{$adAccountId}/campaigns", array_merge($payload, [
            'access_token' => $accessToken,
        ]));

        if (!$response->successful()) {
            throw new \Exception('Failed to create Facebook campaign: ' . $this->getErrorMessage($response));
        }

        return $response->json('id');
    }

    /**
     * Create Facebook ad set (targeting + budget + schedule)
     */
    protected function createFbAdSet(
        AdsCampaign $campaign,
        AdsCampaignTargeting $targeting,
        string $fbCampaignId,
        string $adAccountId,
        string $accessToken,
        string $platform,
        AdsPlatformCampaign $platformCampaign
    ): string {
        $payload = [
            'name' => "Tixello: {$campaign->name} - " . ucfirst($platform),
            'campaign_id' => $fbCampaignId,
            'status' => 'PAUSED',
            'targeting' => $targeting->toFacebookTargetingSpec(),
            'optimization_goal' => $this->getOptimizationGoal($campaign->objective),
            'billing_event' => 'IMPRESSIONS',
        ];

        // Budget at ad set level (if not using CBO)
        if (!$campaign->daily_budget && $platformCampaign->budget_allocated) {
            $payload['lifetime_budget'] = (int) ($platformCampaign->budget_allocated * 100);
        }

        // Schedule
        if ($campaign->start_date) {
            $payload['start_time'] = $campaign->start_date->toIso8601String();
        }
        if ($campaign->end_date) {
            $payload['end_time'] = $campaign->end_date->toIso8601String();
        }

        // Platform-specific placement
        if ($platform === 'instagram') {
            $payload['targeting']['publisher_platforms'] = ['instagram'];
            $payload['targeting']['instagram_positions'] = ['stream', 'story', 'explore', 'reels'];
        } elseif ($platform === 'facebook') {
            $payload['targeting']['publisher_platforms'] = ['facebook'];
            $payload['targeting']['facebook_positions'] = ['feed', 'story', 'video_feeds', 'marketplace', 'right_hand_column', 'search'];
        }

        // Custom placements override
        if (!$targeting->automatic_placements && $targeting->placements) {
            $platformPlacements = $targeting->placements[$platform] ?? [];
            if (!empty($platformPlacements)) {
                $payload['targeting']["{$platform}_positions"] = $platformPlacements;
            }
        }

        // Custom audiences for retargeting
        if ($targeting->custom_audience_ids) {
            $payload['targeting']['custom_audiences'] = array_map(
                fn ($id) => ['id' => $id],
                $targeting->custom_audience_ids
            );
        }

        // Bid strategy
        $payload['bid_strategy'] = $this->getBidStrategy($campaign);

        // Conversion pixel and event for conversion campaigns
        if ($campaign->objective === 'conversions') {
            $payload['promoted_object'] = [
                'pixel_id' => $this->getPixelId($campaign),
                'custom_event_type' => 'PURCHASE',
            ];
        }

        $response = Http::post("{$this->baseUrl}/{$this->apiVersion}/act_{$adAccountId}/adsets", array_merge($payload, [
            'access_token' => $accessToken,
        ]));

        if (!$response->successful()) {
            throw new \Exception('Failed to create Facebook ad set: ' . $this->getErrorMessage($response));
        }

        return $response->json('id');
    }

    /**
     * Create ad creative (image/video/carousel)
     */
    protected function createFbAdCreative(
        AdsCampaignCreative $creative,
        string $adAccountId,
        string $accessToken,
        AdsCampaign $campaign
    ): string {
        $payload = [
            'name' => "Creative: {$campaign->name} - {$creative->variant_label}",
        ];

        $content = $creative->getContentForPlatform('facebook');
        $ctaType = $this->ctaMap[$content['cta_type'] ?? 'LEARN_MORE'] ?? 'LEARN_MORE';

        if ($creative->isImage()) {
            // Upload image first if we have a local path
            $imageHash = $creative->media_url;
            if ($creative->media_path) {
                $imageHash = $this->uploadImage($creative->media_path, $adAccountId, $accessToken);
            }

            $payload['object_story_spec'] = [
                'page_id' => $this->getPageId($campaign),
                'link_data' => [
                    'image_hash' => $imageHash,
                    'link' => $content['cta_url'] ?? $campaign->tracking_url,
                    'message' => $content['primary_text'] ?? '',
                    'name' => $content['headline'] ?? '',
                    'description' => $content['description'] ?? '',
                    'call_to_action' => [
                        'type' => $ctaType,
                        'value' => ['link' => $content['cta_url'] ?? $campaign->tracking_url],
                    ],
                ],
            ];
        } elseif ($creative->isVideo()) {
            // Upload video
            $videoId = $this->uploadVideo($creative->media_path, $adAccountId, $accessToken);

            $payload['object_story_spec'] = [
                'page_id' => $this->getPageId($campaign),
                'video_data' => [
                    'video_id' => $videoId,
                    'message' => $content['primary_text'] ?? '',
                    'title' => $content['headline'] ?? '',
                    'link_description' => $content['description'] ?? '',
                    'call_to_action' => [
                        'type' => $ctaType,
                        'value' => ['link' => $content['cta_url'] ?? $campaign->tracking_url],
                    ],
                ],
            ];

            if ($creative->thumbnail_path) {
                $payload['object_story_spec']['video_data']['image_url'] = $creative->thumbnail_path;
            }
        } elseif ($creative->isCarousel()) {
            $childAttachments = [];
            foreach ($creative->carousel_items ?? [] as $item) {
                $childAttachments[] = [
                    'link' => $item['url'] ?? $content['cta_url'],
                    'name' => $item['headline'] ?? '',
                    'description' => $item['description'] ?? '',
                    'image_hash' => $item['image_path'] ? $this->uploadImage($item['image_path'], $adAccountId, $accessToken) : null,
                ];
            }

            $payload['object_story_spec'] = [
                'page_id' => $this->getPageId($campaign),
                'link_data' => [
                    'message' => $content['primary_text'] ?? '',
                    'link' => $content['cta_url'] ?? $campaign->tracking_url,
                    'child_attachments' => $childAttachments,
                    'multi_share_optimized' => true,
                ],
            ];
        }

        $response = Http::post("{$this->baseUrl}/{$this->apiVersion}/act_{$adAccountId}/adcreatives", array_merge($payload, [
            'access_token' => $accessToken,
        ]));

        if (!$response->successful()) {
            throw new \Exception('Failed to create ad creative: ' . $this->getErrorMessage($response));
        }

        return $response->json('id');
    }

    /**
     * Create the actual ad (links ad set + creative)
     */
    protected function createFbAd(
        AdsCampaign $campaign,
        string $adSetId,
        string $creativeId,
        string $adAccountId,
        string $accessToken
    ): string {
        $response = Http::post("{$this->baseUrl}/{$this->apiVersion}/act_{$adAccountId}/ads", [
            'name' => "Ad: {$campaign->name}",
            'adset_id' => $adSetId,
            'creative' => ['creative_id' => $creativeId],
            'status' => 'PAUSED',
            'access_token' => $accessToken,
            'tracking_specs' => [
                [
                    'action.type' => ['offsite_conversion'],
                    'fb_pixel' => [$this->getPixelId($campaign)],
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create ad: ' . $this->getErrorMessage($response));
        }

        return $response->json('id');
    }

    // ==========================================
    // CAMPAIGN MANAGEMENT
    // ==========================================

    /**
     * Activate a paused campaign on Facebook
     */
    public function activateCampaign(AdsPlatformCampaign $platformCampaign): void
    {
        $accessToken = Setting::current()->facebook_access_token;

        // Activate campaign, ad set, and ad
        foreach (['platform_campaign_id', 'platform_adset_id', 'platform_ad_id'] as $field) {
            if ($platformCampaign->$field) {
                Http::post("{$this->baseUrl}/{$this->apiVersion}/{$platformCampaign->$field}", [
                    'status' => 'ACTIVE',
                    'access_token' => $accessToken,
                ]);
            }
        }

        $platformCampaign->update(['status' => 'active', 'launched_at' => now()]);
    }

    /**
     * Pause a running campaign
     */
    public function pauseCampaign(AdsPlatformCampaign $platformCampaign): void
    {
        $accessToken = Setting::current()->facebook_access_token;

        if ($platformCampaign->platform_campaign_id) {
            Http::post("{$this->baseUrl}/{$this->apiVersion}/{$platformCampaign->platform_campaign_id}", [
                'status' => 'PAUSED',
                'access_token' => $accessToken,
            ]);
        }

        $platformCampaign->update(['status' => 'paused']);
    }

    /**
     * Update ad set budget
     */
    public function updateBudget(AdsPlatformCampaign $platformCampaign, float $newDailyBudget): void
    {
        $accessToken = Setting::current()->facebook_access_token;

        if ($platformCampaign->platform_adset_id) {
            Http::post("{$this->baseUrl}/{$this->apiVersion}/{$platformCampaign->platform_adset_id}", [
                'daily_budget' => (int) ($newDailyBudget * 100),
                'access_token' => $accessToken,
            ]);

            $platformCampaign->update(['daily_budget' => $newDailyBudget]);
        }
    }

    /**
     * Delete campaign from Facebook
     */
    public function deleteCampaign(AdsPlatformCampaign $platformCampaign): void
    {
        $accessToken = Setting::current()->facebook_access_token;

        if ($platformCampaign->platform_campaign_id) {
            Http::delete("{$this->baseUrl}/{$this->apiVersion}/{$platformCampaign->platform_campaign_id}", [
                'access_token' => $accessToken,
            ]);
        }

        $platformCampaign->update(['status' => 'deleted']);
    }

    // ==========================================
    // METRICS FETCHING
    // ==========================================

    /**
     * Fetch campaign insights from Facebook
     */
    public function fetchInsights(AdsPlatformCampaign $platformCampaign, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $accessToken = Setting::current()->facebook_access_token;

        if (!$platformCampaign->platform_campaign_id) {
            return [];
        }

        $params = [
            'fields' => implode(',', [
                'impressions', 'reach', 'clicks', 'ctr', 'cpc', 'cpm',
                'spend', 'actions', 'action_values', 'frequency',
                'video_avg_time_watched_actions', 'video_p25_watched_actions',
                'video_p50_watched_actions', 'video_p75_watched_actions',
                'video_p100_watched_actions', 'cost_per_action_type',
                'social_spend', 'quality_ranking', 'engagement_rate_ranking',
                'conversion_rate_ranking',
            ]),
            'access_token' => $accessToken,
            'time_increment' => 1, // Daily breakdown
        ];

        if ($dateFrom && $dateTo) {
            $params['time_range'] = json_encode([
                'since' => $dateFrom,
                'until' => $dateTo,
            ]);
        }

        // Fetch at campaign level
        $response = Http::get(
            "{$this->baseUrl}/{$this->apiVersion}/{$platformCampaign->platform_campaign_id}/insights",
            $params
        );

        if (!$response->successful()) {
            Log::warning('Failed to fetch Facebook insights', [
                'platform_campaign_id' => $platformCampaign->id,
                'error' => $response->body(),
            ]);
            return [];
        }

        return $this->parseInsights($response->json('data') ?? []);
    }

    /**
     * Parse Facebook insights response into normalized metrics
     */
    protected function parseInsights(array $data): array
    {
        $metrics = [];

        foreach ($data as $row) {
            $date = $row['date_start'] ?? now()->toDateString();

            $conversions = 0;
            $revenue = 0;

            // Extract conversions from actions
            foreach ($row['actions'] ?? [] as $action) {
                if (in_array($action['action_type'], ['offsite_conversion.fb_pixel_purchase', 'purchase', 'omni_purchase'])) {
                    $conversions += (int) $action['value'];
                }
            }

            // Extract revenue from action values
            foreach ($row['action_values'] ?? [] as $actionValue) {
                if (in_array($actionValue['action_type'], ['offsite_conversion.fb_pixel_purchase', 'purchase', 'omni_purchase'])) {
                    $revenue += (float) $actionValue['value'];
                }
            }

            // Video metrics
            $videoViews = 0;
            foreach ($row['video_p25_watched_actions'] ?? [] as $v) {
                $videoViews = max($videoViews, (int) ($v['value'] ?? 0));
            }

            $metrics[] = [
                'date' => $date,
                'impressions' => (int) ($row['impressions'] ?? 0),
                'reach' => (int) ($row['reach'] ?? 0),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'spend' => (float) ($row['spend'] ?? 0),
                'frequency' => (int) ($row['frequency'] ?? 0),
                'conversions' => $conversions,
                'revenue' => $revenue,
                'video_views' => $videoViews,
                'quality_score' => $this->rankingToScore($row['quality_ranking'] ?? null),
                'relevance_score' => $this->rankingToScore($row['engagement_rate_ranking'] ?? null),
            ];
        }

        return $metrics;
    }

    // ==========================================
    // MEDIA UPLOAD
    // ==========================================

    protected function uploadImage(string $imagePath, string $adAccountId, string $accessToken): string
    {
        $response = Http::attach('filename', file_get_contents($imagePath), basename($imagePath))
            ->post("{$this->baseUrl}/{$this->apiVersion}/act_{$adAccountId}/adimages", [
                'access_token' => $accessToken,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to upload image: ' . $this->getErrorMessage($response));
        }

        $images = $response->json('images') ?? [];
        return array_values($images)[0]['hash'] ?? '';
    }

    protected function uploadVideo(string $videoPath, string $adAccountId, string $accessToken): string
    {
        $response = Http::attach('source', file_get_contents($videoPath), basename($videoPath))
            ->post("{$this->baseUrl}/{$this->apiVersion}/act_{$adAccountId}/advideos", [
                'access_token' => $accessToken,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to upload video: ' . $this->getErrorMessage($response));
        }

        return $response->json('id');
    }

    // ==========================================
    // HELPERS
    // ==========================================

    protected function calculatePlatformBudget(AdsCampaign $campaign, string $platform): float
    {
        $platformCount = count($campaign->target_platforms ?? []);
        if ($platformCount <= 0) return (float) $campaign->total_budget;

        if ($campaign->budget_allocation === 'equal') {
            return (float) $campaign->total_budget / $platformCount;
        }

        // Default: allocate proportionally (can be refined with performance data later)
        return (float) $campaign->total_budget / $platformCount;
    }

    protected function getOptimizationGoal(string $objective): string
    {
        return match ($objective) {
            'conversions' => 'OFFSITE_CONVERSIONS',
            'traffic' => 'LINK_CLICKS',
            'awareness' => 'REACH',
            'engagement' => 'POST_ENGAGEMENT',
            'leads' => 'LEAD_GENERATION',
            default => 'OFFSITE_CONVERSIONS',
        };
    }

    protected function getBidStrategy(AdsCampaign $campaign): string
    {
        return match ($campaign->optimization_goal) {
            'conversions' => 'LOWEST_COST_WITHOUT_CAP',
            'clicks' => 'LOWEST_COST_WITHOUT_CAP',
            'roas' => 'LOWEST_COST_WITHOUT_CAP',
            default => 'LOWEST_COST_WITHOUT_CAP',
        };
    }

    protected function getPixelId(AdsCampaign $campaign): ?string
    {
        $settings = Setting::current();
        return $settings->meta['facebook_pixel_id'] ?? null;
    }

    protected function getPageId(AdsCampaign $campaign): ?string
    {
        $settings = Setting::current();
        return $settings->meta['facebook_page_id'] ?? null;
    }

    protected function getErrorMessage($response): string
    {
        $error = $response->json('error') ?? [];
        return $error['message'] ?? $response->body();
    }

    protected function rankingToScore(?string $ranking): ?float
    {
        if (!$ranking) return null;

        return match ($ranking) {
            'ABOVE_AVERAGE_35' => 9.0,
            'ABOVE_AVERAGE_20' => 8.0,
            'AVERAGE' => 5.0,
            'BELOW_AVERAGE_35' => 3.0,
            'BELOW_AVERAGE_20' => 2.0,
            'BELOW_AVERAGE_10' => 1.0,
            default => 5.0,
        };
    }
}
