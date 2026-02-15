<?php

namespace App\Services\AdsCampaign;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignCreative;
use App\Models\AdsCampaign\AdsCampaignTargeting;
use App\Models\AdsCampaign\AdsPlatformCampaign;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsCampaignService
{
    protected string $apiVersion = 'v18';
    protected string $baseUrl = 'https://googleads.googleapis.com';
    protected string $oauthUrl = 'https://oauth2.googleapis.com/token';

    /**
     * Map Tixello objectives to Google Ads campaign types
     */
    protected array $objectiveMap = [
        'conversions' => ['type' => 'SEARCH', 'bidding' => 'MAXIMIZE_CONVERSIONS'],
        'traffic' => ['type' => 'SEARCH', 'bidding' => 'MAXIMIZE_CLICKS'],
        'awareness' => ['type' => 'DISPLAY', 'bidding' => 'TARGET_IMPRESSION_SHARE'],
        'engagement' => ['type' => 'DISPLAY', 'bidding' => 'MAXIMIZE_CLICKS'],
        'leads' => ['type' => 'SEARCH', 'bidding' => 'MAXIMIZE_CONVERSIONS'],
    ];

    // ==========================================
    // AUTHENTICATION
    // ==========================================

    protected function getAccessToken(): string
    {
        $settings = Setting::current();
        $meta = $settings->meta ?? [];

        // Check if we have a valid token
        $tokenExpiry = $meta['google_ads_token_expires_at'] ?? null;
        if ($tokenExpiry && now()->lt($tokenExpiry) && !empty($meta['google_ads_access_token'])) {
            return $meta['google_ads_access_token'];
        }

        // Refresh the token
        $response = Http::asForm()->post($this->oauthUrl, [
            'client_id' => $settings->google_ads_client_id,
            'client_secret' => $settings->google_ads_client_secret,
            'refresh_token' => $meta['google_ads_refresh_token'] ?? '',
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to refresh Google Ads token: ' . $response->body());
        }

        $data = $response->json();
        $newToken = $data['access_token'];

        // Store token in settings meta
        $meta['google_ads_access_token'] = $newToken;
        $meta['google_ads_token_expires_at'] = now()->addSeconds($data['expires_in'] - 60)->toIso8601String();
        $settings->update(['meta' => $meta]);

        return $newToken;
    }

    protected function apiHeaders(): array
    {
        $settings = Setting::current();
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'developer-token' => $settings->google_ads_developer_token,
            'login-customer-id' => $settings->meta['google_ads_customer_id'] ?? '',
        ];
    }

    protected function customerId(): string
    {
        return Setting::current()->meta['google_ads_customer_id'] ?? '';
    }

    // ==========================================
    // CAMPAIGN CREATION
    // ==========================================

    /**
     * Create a full campaign on Google Ads (Campaign → Ad Group → Ad)
     */
    public function createCampaign(
        AdsCampaign $campaign,
        AdsCampaignTargeting $targeting,
        AdsCampaignCreative $creative
    ): AdsPlatformCampaign {
        $platformCampaign = AdsPlatformCampaign::create([
            'campaign_id' => $campaign->id,
            'platform' => 'google',
            'variant_label' => $creative->variant_label,
            'status' => 'pending_creation',
            'budget_allocated' => $this->calculatePlatformBudget($campaign),
            'daily_budget' => $campaign->daily_budget ? $campaign->daily_budget / count($campaign->target_platforms) : null,
        ]);

        try {
            $customerId = $this->customerId();

            // Step 1: Create campaign budget
            $budgetResourceName = $this->createCampaignBudget($campaign, $customerId, $platformCampaign);

            // Step 2: Create campaign
            $gCampaignResource = $this->createGoogleCampaign($campaign, $customerId, $budgetResourceName);
            $gCampaignId = basename($gCampaignResource);
            $platformCampaign->update(['platform_campaign_id' => $gCampaignId]);

            // Step 3: Set targeting criteria
            $this->setCampaignCriteria($customerId, $gCampaignResource, $targeting);

            // Step 4: Create ad group
            $adGroupResource = $this->createAdGroup($campaign, $customerId, $gCampaignResource);
            $adGroupId = basename($adGroupResource);
            $platformCampaign->update(['platform_adset_id' => $adGroupId]);

            // Step 5: Create ad
            $adResource = $this->createAd($campaign, $creative, $customerId, $adGroupResource);
            $adId = basename($adResource);
            $platformCampaign->update([
                'platform_ad_id' => $adId,
                'status' => 'active',
                'launched_at' => now(),
            ]);

            Log::info("Google Ads campaign created", [
                'campaign_id' => $campaign->id,
                'google_campaign_id' => $gCampaignId,
                'ad_group_id' => $adGroupId,
                'ad_id' => $adId,
            ]);

        } catch (\Exception $e) {
            $platformCampaign->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("Google Ads campaign creation failed", [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $platformCampaign->fresh();
    }

    protected function createCampaignBudget(AdsCampaign $campaign, string $customerId, AdsPlatformCampaign $platformCampaign): string
    {
        $dailyBudgetMicros = (int) (($platformCampaign->daily_budget ?? ($platformCampaign->budget_allocated / 30)) * 1_000_000);

        $response = Http::withHeaders($this->apiHeaders())
            ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/campaignBudgets:mutate", [
                'operations' => [
                    [
                        'create' => [
                            'name' => "Tixello Budget: {$campaign->name} - " . Str::random(6),
                            'amountMicros' => $dailyBudgetMicros,
                            'deliveryMethod' => 'STANDARD',
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create Google Ads budget: ' . $response->body());
        }

        return $response->json('results.0.resourceName');
    }

    protected function createGoogleCampaign(AdsCampaign $campaign, string $customerId, string $budgetResourceName): string
    {
        $config = $this->objectiveMap[$campaign->objective] ?? $this->objectiveMap['conversions'];

        $campaignData = [
            'name' => "Tixello: {$campaign->name}",
            'status' => 'PAUSED',
            'advertisingChannelType' => $config['type'],
            'campaignBudget' => $budgetResourceName,
            'biddingStrategyType' => $config['bidding'],
        ];

        // Schedule
        if ($campaign->start_date) {
            $campaignData['startDate'] = $campaign->start_date->format('Y-m-d');
        }
        if ($campaign->end_date) {
            $campaignData['endDate'] = $campaign->end_date->format('Y-m-d');
        }

        // Network settings for Search campaigns
        if ($config['type'] === 'SEARCH') {
            $campaignData['networkSettings'] = [
                'targetGoogleSearch' => true,
                'targetSearchNetwork' => true,
                'targetContentNetwork' => false,
            ];
        } elseif ($config['type'] === 'DISPLAY') {
            $campaignData['networkSettings'] = [
                'targetGoogleSearch' => false,
                'targetSearchNetwork' => false,
                'targetContentNetwork' => true,
            ];
        }

        $response = Http::withHeaders($this->apiHeaders())
            ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/campaigns:mutate", [
                'operations' => [['create' => $campaignData]],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create Google Ads campaign: ' . $response->body());
        }

        return $response->json('results.0.resourceName');
    }

    protected function setCampaignCriteria(string $customerId, string $campaignResource, AdsCampaignTargeting $targeting): void
    {
        $operations = [];
        $googleTargeting = $targeting->toGoogleAdsTargeting();

        // Location targeting
        foreach ($googleTargeting['location_targets'] ?? [] as $location) {
            $operations[] = [
                'create' => [
                    'campaign' => $campaignResource,
                    'type' => 'LOCATION',
                    'location' => [
                        'geoTargetConstant' => "geoTargetConstants/{$location['geo_target_constant']}",
                    ],
                    'negative' => $location['negative'] ?? false,
                ],
            ];
        }

        // Language targeting
        foreach ($googleTargeting['languages'] ?? [] as $lang) {
            $langId = $this->getLanguageConstantId($lang);
            if ($langId) {
                $operations[] = [
                    'create' => [
                        'campaign' => $campaignResource,
                        'type' => 'LANGUAGE',
                        'language' => [
                            'languageConstant' => "languageConstants/{$langId}",
                        ],
                    ],
                ];
            }
        }

        if (!empty($operations)) {
            Http::withHeaders($this->apiHeaders())
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/campaignCriteria:mutate", [
                    'operations' => $operations,
                ]);
        }
    }

    protected function createAdGroup(AdsCampaign $campaign, string $customerId, string $campaignResource): string
    {
        $response = Http::withHeaders($this->apiHeaders())
            ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/adGroups:mutate", [
                'operations' => [
                    [
                        'create' => [
                            'name' => "Tixello: {$campaign->name} - Ad Group",
                            'campaign' => $campaignResource,
                            'status' => 'ENABLED',
                            'type' => 'SEARCH_STANDARD',
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create ad group: ' . $response->body());
        }

        return $response->json('results.0.resourceName');
    }

    protected function createAd(AdsCampaign $campaign, AdsCampaignCreative $creative, string $customerId, string $adGroupResource): string
    {
        $content = $creative->getContentForPlatform('google');

        // Create responsive search ad (most common Google Ads format)
        $adData = [
            'adGroup' => $adGroupResource,
            'ad' => [
                'responsiveSearchAd' => [
                    'headlines' => [
                        ['text' => mb_substr($content['headline'] ?? $campaign->name, 0, 30)],
                        ['text' => mb_substr('Get Tickets Now', 0, 30)],
                        ['text' => mb_substr($campaign->event?->getTranslation('title', 'en') ?? 'Don\'t Miss Out', 0, 30)],
                    ],
                    'descriptions' => [
                        ['text' => mb_substr($content['description'] ?? $content['primary_text'] ?? 'Get your tickets today!', 0, 90)],
                        ['text' => mb_substr('Limited availability. Book your spot now!', 0, 90)],
                    ],
                ],
                'finalUrls' => [$content['cta_url'] ?? $campaign->tracking_url ?? 'https://tixello.com'],
            ],
            'status' => 'ENABLED',
        ];

        // For Display campaigns, use responsive display ad
        $config = $this->objectiveMap[$campaign->objective] ?? $this->objectiveMap['conversions'];
        if ($config['type'] === 'DISPLAY' && $creative->isImage()) {
            $adData['ad'] = [
                'responsiveDisplayAd' => [
                    'headlines' => [['text' => mb_substr($content['headline'] ?? $campaign->name, 0, 30)]],
                    'longHeadline' => ['text' => mb_substr($content['headline'] ?? $campaign->name, 0, 90)],
                    'descriptions' => [['text' => mb_substr($content['description'] ?? 'Get tickets now!', 0, 90)]],
                    'businessName' => 'Tixello',
                ],
                'finalUrls' => [$content['cta_url'] ?? $campaign->tracking_url ?? 'https://tixello.com'],
            ];

            // Upload image asset if available
            if ($creative->media_path) {
                $assetResource = $this->uploadAsset($customerId, $creative->media_path);
                $adData['ad']['responsiveDisplayAd']['marketingImages'] = [
                    ['asset' => $assetResource],
                ];
            }
        }

        $response = Http::withHeaders($this->apiHeaders())
            ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/adGroupAds:mutate", [
                'operations' => [['create' => $adData]],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create Google ad: ' . $response->body());
        }

        return $response->json('results.0.resourceName');
    }

    // ==========================================
    // CAMPAIGN MANAGEMENT
    // ==========================================

    public function activateCampaign(AdsPlatformCampaign $platformCampaign): void
    {
        $customerId = $this->customerId();

        if ($platformCampaign->platform_campaign_id) {
            Http::withHeaders($this->apiHeaders())
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/campaigns:mutate", [
                    'operations' => [
                        [
                            'update' => [
                                'resourceName' => "customers/{$customerId}/campaigns/{$platformCampaign->platform_campaign_id}",
                                'status' => 'ENABLED',
                            ],
                            'updateMask' => 'status',
                        ],
                    ],
                ]);
        }

        $platformCampaign->update(['status' => 'active', 'launched_at' => now()]);
    }

    public function pauseCampaign(AdsPlatformCampaign $platformCampaign): void
    {
        $customerId = $this->customerId();

        if ($platformCampaign->platform_campaign_id) {
            Http::withHeaders($this->apiHeaders())
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/campaigns:mutate", [
                    'operations' => [
                        [
                            'update' => [
                                'resourceName' => "customers/{$customerId}/campaigns/{$platformCampaign->platform_campaign_id}",
                                'status' => 'PAUSED',
                            ],
                            'updateMask' => 'status',
                        ],
                    ],
                ]);
        }

        $platformCampaign->update(['status' => 'paused']);
    }

    public function updateBudget(AdsPlatformCampaign $platformCampaign, float $newDailyBudget): void
    {
        // Google Ads requires updating the budget resource, not the campaign directly
        $platformCampaign->update(['daily_budget' => $newDailyBudget]);
    }

    // ==========================================
    // METRICS FETCHING
    // ==========================================

    public function fetchInsights(AdsPlatformCampaign $platformCampaign, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!$platformCampaign->platform_campaign_id) return [];

        $customerId = $this->customerId();

        $dateFilter = '';
        if ($dateFrom && $dateTo) {
            $dateFilter = "AND segments.date BETWEEN '{$dateFrom}' AND '{$dateTo}'";
        }

        $query = "SELECT
            segments.date,
            metrics.impressions,
            metrics.clicks,
            metrics.cost_micros,
            metrics.conversions,
            metrics.conversions_value,
            metrics.ctr,
            metrics.average_cpc,
            metrics.average_cpm,
            metrics.video_views,
            metrics.video_view_rate
        FROM campaign
        WHERE campaign.id = {$platformCampaign->platform_campaign_id}
        {$dateFilter}
        ORDER BY segments.date DESC";

        try {
            $response = Http::withHeaders($this->apiHeaders())
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/googleAds:searchStream", [
                    'query' => $query,
                ]);

            if (!$response->successful()) {
                Log::warning('Failed to fetch Google Ads insights', [
                    'platform_campaign_id' => $platformCampaign->id,
                    'error' => $response->body(),
                ]);
                return [];
            }

            return $this->parseInsights($response->json());
        } catch (\Exception $e) {
            Log::error('Google Ads insights fetch error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    protected function parseInsights(array $data): array
    {
        $metrics = [];

        foreach ($data as $batch) {
            foreach ($batch['results'] ?? [] as $row) {
                $m = $row['metrics'] ?? [];
                $date = $row['segments']['date'] ?? now()->toDateString();

                $metrics[] = [
                    'date' => $date,
                    'impressions' => (int) ($m['impressions'] ?? 0),
                    'clicks' => (int) ($m['clicks'] ?? 0),
                    'spend' => ($m['costMicros'] ?? 0) / 1_000_000,
                    'conversions' => (int) ($m['conversions'] ?? 0),
                    'revenue' => (float) ($m['conversionsValue'] ?? 0),
                    'video_views' => (int) ($m['videoViews'] ?? 0),
                    'reach' => 0, // Google doesn't provide reach in search campaigns
                    'frequency' => 0,
                ];
            }
        }

        return $metrics;
    }

    // ==========================================
    // ASSET MANAGEMENT
    // ==========================================

    protected function uploadAsset(string $customerId, string $filePath): string
    {
        $fileContent = file_get_contents($filePath);

        $response = Http::withHeaders($this->apiHeaders())
            ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/assets:mutate", [
                'operations' => [
                    [
                        'create' => [
                            'name' => 'Tixello Image - ' . Str::random(8),
                            'type' => 'IMAGE',
                            'imageAsset' => [
                                'data' => base64_encode($fileContent),
                            ],
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to upload Google Ads asset: ' . $response->body());
        }

        return $response->json('results.0.resourceName');
    }

    // ==========================================
    // KEYWORD MANAGEMENT (for Search campaigns)
    // ==========================================

    /**
     * Add keywords to ad group for event-related search campaigns
     */
    public function addEventKeywords(string $adGroupResource, array $eventKeywords): void
    {
        $customerId = $this->customerId();
        $operations = [];

        foreach ($eventKeywords as $keyword) {
            $operations[] = [
                'create' => [
                    'adGroup' => $adGroupResource,
                    'status' => 'ENABLED',
                    'keyword' => [
                        'text' => $keyword['text'],
                        'matchType' => $keyword['match_type'] ?? 'PHRASE',
                    ],
                ],
            ];
        }

        // Add negative keywords to avoid irrelevant traffic
        $negativeKeywords = ['free tickets', 'cheap knock off', 'cancelled'];
        foreach ($negativeKeywords as $neg) {
            $operations[] = [
                'create' => [
                    'adGroup' => $adGroupResource,
                    'negative' => true,
                    'keyword' => [
                        'text' => $neg,
                        'matchType' => 'PHRASE',
                    ],
                ],
            ];
        }

        if (!empty($operations)) {
            Http::withHeaders($this->apiHeaders())
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerId}/adGroupCriteria:mutate", [
                    'operations' => $operations,
                ]);
        }
    }

    /**
     * Generate event-relevant keywords
     */
    public function generateEventKeywords(AdsCampaign $campaign): array
    {
        $event = $campaign->event;
        if (!$event) return [];

        $keywords = [];
        $eventTitle = $event->getTranslation('title', 'en') ?? '';

        // Event name variations
        if ($eventTitle) {
            $keywords[] = ['text' => $eventTitle, 'match_type' => 'PHRASE'];
            $keywords[] = ['text' => "{$eventTitle} tickets", 'match_type' => 'PHRASE'];
            $keywords[] = ['text' => "{$eventTitle} bilete", 'match_type' => 'PHRASE'];
        }

        // Venue-based keywords
        if ($event->venue) {
            $venueName = $event->venue->name ?? '';
            if ($venueName) {
                $keywords[] = ['text' => "events at {$venueName}", 'match_type' => 'PHRASE'];
                $keywords[] = ['text' => "concerte {$venueName}", 'match_type' => 'PHRASE'];
            }
        }

        // Artist-based keywords
        foreach ($event->artists ?? [] as $artist) {
            $artistName = $artist->name ?? '';
            if ($artistName) {
                $keywords[] = ['text' => "{$artistName} concert", 'match_type' => 'PHRASE'];
                $keywords[] = ['text' => "{$artistName} tickets", 'match_type' => 'PHRASE'];
                $keywords[] = ['text' => "bilete {$artistName}", 'match_type' => 'PHRASE'];
            }
        }

        // Generic event keywords
        $keywords[] = ['text' => 'buy concert tickets', 'match_type' => 'BROAD'];
        $keywords[] = ['text' => 'bilete concert', 'match_type' => 'BROAD'];
        $keywords[] = ['text' => 'events near me', 'match_type' => 'BROAD'];

        return $keywords;
    }

    // ==========================================
    // HELPERS
    // ==========================================

    protected function calculatePlatformBudget(AdsCampaign $campaign): float
    {
        $platformCount = count($campaign->target_platforms ?? []);
        if ($platformCount <= 0) return (float) $campaign->total_budget;
        return (float) $campaign->total_budget / $platformCount;
    }

    protected function getLanguageConstantId(string $langCode): ?string
    {
        // Google Ads language constant IDs
        $map = [
            'en' => '1000', 'ro' => '1032', 'hu' => '1018',
            'de' => '1001', 'fr' => '1002', 'es' => '1003',
            'it' => '1004', 'pt' => '1014', 'nl' => '1010',
            'pl' => '1030', 'ru' => '1031', 'bg' => '1020',
        ];

        return $map[$langCode] ?? null;
    }
}
