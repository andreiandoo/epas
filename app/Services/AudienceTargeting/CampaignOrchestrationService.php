<?php

namespace App\Services\AudienceTargeting;

use App\Models\AudienceCampaign;
use App\Models\AudienceExport;
use App\Models\AudienceSegment;
use App\Models\Event;
use App\Models\EventRecommendation;
use App\Models\Tenant;
use App\Services\AudienceTargeting\Providers\BrevoAudienceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CampaignOrchestrationService
{
    public function __construct(
        protected AudienceExportService $exportService,
        protected EventMatchingService $eventMatchingService
    ) {}

    /**
     * Create a new campaign
     */
    public function createCampaign(
        Tenant $tenant,
        string $name,
        string $type,
        AudienceSegment $segment,
        ?Event $event = null,
        array $settings = []
    ): AudienceCampaign {
        $campaign = AudienceCampaign::create([
            'tenant_id' => $tenant->id,
            'segment_id' => $segment->id,
            'event_id' => $event?->id,
            'name' => $name,
            'campaign_type' => $type,
            'status' => AudienceCampaign::STATUS_DRAFT,
            'settings' => $settings,
        ]);

        return $campaign;
    }

    /**
     * Create an email campaign
     */
    public function createEmailCampaign(
        Tenant $tenant,
        AudienceSegment $segment,
        string $name,
        string $subject,
        string $htmlContent,
        string $senderName,
        string $senderEmail,
        ?Event $event = null,
        ?\DateTimeInterface $scheduledAt = null
    ): AudienceCampaign {
        return $this->createCampaign(
            $tenant,
            $name,
            AudienceCampaign::TYPE_EMAIL,
            $segment,
            $event,
            [
                'subject' => $subject,
                'html_content' => $htmlContent,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'scheduled_at' => $scheduledAt?->format('Y-m-d\TH:i:s\Z'),
            ]
        );
    }

    /**
     * Create a Meta Ads campaign
     */
    public function createMetaAdsCampaign(
        Tenant $tenant,
        AudienceSegment $segment,
        string $name,
        string $objective,
        int $budgetCents,
        int $durationDays,
        ?Event $event = null,
        array $additionalSettings = []
    ): AudienceCampaign {
        return $this->createCampaign(
            $tenant,
            $name,
            AudienceCampaign::TYPE_META_ADS,
            $segment,
            $event,
            array_merge([
                'objective' => $objective,
                'budget_cents' => $budgetCents,
                'duration_days' => $durationDays,
            ], $additionalSettings)
        );
    }

    /**
     * Schedule a campaign
     */
    public function scheduleCampaign(
        AudienceCampaign $campaign,
        \DateTimeInterface $scheduledAt
    ): AudienceCampaign {
        if (!$campaign->canLaunch()) {
            throw new \RuntimeException('Campaign cannot be scheduled in its current state');
        }

        $campaign->update([
            'status' => AudienceCampaign::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
        ]);

        return $campaign;
    }

    /**
     * Launch a campaign
     */
    public function launchCampaign(AudienceCampaign $campaign): AudienceCampaign
    {
        if (!$campaign->canLaunch()) {
            throw new \RuntimeException('Campaign cannot be launched in its current state');
        }

        try {
            $campaign->markStarted();

            // Execute based on campaign type
            $result = match ($campaign->campaign_type) {
                AudienceCampaign::TYPE_EMAIL => $this->executeEmailCampaign($campaign),
                AudienceCampaign::TYPE_META_ADS => $this->executeMetaAdsCampaign($campaign),
                AudienceCampaign::TYPE_GOOGLE_ADS => $this->executeGoogleAdsCampaign($campaign),
                AudienceCampaign::TYPE_TIKTOK_ADS => $this->executeTikTokAdsCampaign($campaign),
                AudienceCampaign::TYPE_MULTI_CHANNEL => $this->executeMultiChannelCampaign($campaign),
                default => throw new \RuntimeException('Unknown campaign type'),
            };

            // For ad campaigns, they remain active until completed manually
            // For email campaigns, they complete immediately
            if ($campaign->isEmailCampaign()) {
                $campaign->markCompleted($result);
            }

            // Mark customers as notified
            $this->markCustomersNotified($campaign);

            Log::info('Campaign launched', [
                'campaign_id' => $campaign->id,
                'type' => $campaign->campaign_type,
            ]);
        } catch (\Exception $e) {
            $campaign->markFailed($e->getMessage());

            Log::error('Campaign launch failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $campaign->fresh();
    }

    /**
     * Execute email campaign
     */
    protected function executeEmailCampaign(AudienceCampaign $campaign): array
    {
        $settings = $campaign->settings;
        $segment = $campaign->segment;

        if (!$segment) {
            throw new \RuntimeException('Segment not found');
        }

        // Export to Brevo first if not already exported
        $export = $this->getOrCreateExport($segment, AudienceExport::PLATFORM_BREVO, $campaign);

        // Get Brevo provider to send the campaign
        /** @var BrevoAudienceProvider $brevoProvider */
        $brevoProvider = $this->exportService->getProvider(AudienceExport::PLATFORM_BREVO);

        $result = $brevoProvider->sendEmailCampaign(
            $export->external_audience_id,
            $settings['subject'],
            $settings['html_content'],
            $settings['sender_name'],
            $settings['sender_email'],
            $settings['scheduled_at'] ?? null
        );

        return [
            'brevo_campaign_id' => $result['campaign_id'],
            'status' => $result['status'],
            'sent' => $segment->customer_count,
        ];
    }

    /**
     * Execute Meta Ads campaign
     */
    protected function executeMetaAdsCampaign(AudienceCampaign $campaign): array
    {
        $segment = $campaign->segment;

        if (!$segment) {
            throw new \RuntimeException('Segment not found');
        }

        // Export to Meta first
        $export = $this->getOrCreateExport($segment, AudienceExport::PLATFORM_META, $campaign);

        // Note: Actually creating ad campaigns requires additional Meta Marketing API integration
        // This is a placeholder that creates the custom audience for manual campaign creation

        return [
            'meta_audience_id' => $export->external_audience_id,
            'audience_size' => $export->customer_count,
            'matched_count' => $export->matched_count,
            'note' => 'Custom audience created. Create ad campaign in Meta Ads Manager.',
        ];
    }

    /**
     * Execute Google Ads campaign
     */
    protected function executeGoogleAdsCampaign(AudienceCampaign $campaign): array
    {
        $segment = $campaign->segment;

        if (!$segment) {
            throw new \RuntimeException('Segment not found');
        }

        // Export to Google Ads first
        $export = $this->getOrCreateExport($segment, AudienceExport::PLATFORM_GOOGLE, $campaign);

        return [
            'google_audience_id' => $export->external_audience_id,
            'audience_size' => $export->customer_count,
            'matched_count' => $export->matched_count,
            'note' => 'Customer list created. Create campaign in Google Ads.',
        ];
    }

    /**
     * Execute TikTok Ads campaign
     */
    protected function executeTikTokAdsCampaign(AudienceCampaign $campaign): array
    {
        $segment = $campaign->segment;

        if (!$segment) {
            throw new \RuntimeException('Segment not found');
        }

        // Export to TikTok first
        $export = $this->getOrCreateExport($segment, AudienceExport::PLATFORM_TIKTOK, $campaign);

        return [
            'tiktok_audience_id' => $export->external_audience_id,
            'audience_size' => $export->customer_count,
            'matched_count' => $export->matched_count,
            'note' => 'Custom audience created. Create campaign in TikTok Ads Manager.',
        ];
    }

    /**
     * Execute multi-channel campaign
     */
    protected function executeMultiChannelCampaign(AudienceCampaign $campaign): array
    {
        $settings = $campaign->settings;
        $channels = $settings['channels'] ?? [];
        $results = [];

        foreach ($channels as $channel) {
            try {
                $result = match ($channel) {
                    'email' => $this->executeEmailCampaign($campaign),
                    'meta' => $this->executeMetaAdsCampaign($campaign),
                    'google' => $this->executeGoogleAdsCampaign($campaign),
                    'tiktok' => $this->executeTikTokAdsCampaign($campaign),
                    default => null,
                };

                if ($result) {
                    $results[$channel] = $result;
                }
            } catch (\Exception $e) {
                $results[$channel] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Get or create an export for a segment and platform
     */
    protected function getOrCreateExport(
        AudienceSegment $segment,
        string $platform,
        AudienceCampaign $campaign
    ): AudienceExport {
        // Check for existing recent export
        $existingExport = AudienceExport::where('segment_id', $segment->id)
            ->where('platform', $platform)
            ->where('status', AudienceExport::STATUS_COMPLETED)
            ->where('exported_at', '>=', now()->subHours(24))
            ->first();

        if ($existingExport) {
            return $existingExport;
        }

        // Create new export
        $audienceName = "{$segment->name} - {$campaign->name}";

        return $this->exportService->exportSegment(
            $segment,
            $platform,
            $audienceName,
            "Exported for campaign: {$campaign->name}",
            $campaign->id
        );
    }

    /**
     * Mark customers as notified for event recommendations
     */
    protected function markCustomersNotified(AudienceCampaign $campaign): void
    {
        if (!$campaign->event_id) {
            return;
        }

        $channel = match ($campaign->campaign_type) {
            AudienceCampaign::TYPE_EMAIL => 'email',
            AudienceCampaign::TYPE_META_ADS => 'meta_ads',
            AudienceCampaign::TYPE_GOOGLE_ADS => 'google_ads',
            AudienceCampaign::TYPE_TIKTOK_ADS => 'tiktok_ads',
            default => 'other',
        };

        $customerIds = $campaign->segment?->customers()->pluck('customers.id');

        if ($customerIds && $customerIds->isNotEmpty()) {
            EventRecommendation::where('event_id', $campaign->event_id)
                ->whereIn('customer_id', $customerIds)
                ->each(function ($rec) use ($channel) {
                    $rec->markNotified($channel);
                });
        }
    }

    /**
     * Pause a campaign
     */
    public function pauseCampaign(AudienceCampaign $campaign): AudienceCampaign
    {
        if (!$campaign->canPause()) {
            throw new \RuntimeException('Campaign cannot be paused');
        }

        $campaign->update(['status' => AudienceCampaign::STATUS_PAUSED]);

        return $campaign;
    }

    /**
     * Resume a paused campaign
     */
    public function resumeCampaign(AudienceCampaign $campaign): AudienceCampaign
    {
        if (!$campaign->canResume()) {
            throw new \RuntimeException('Campaign cannot be resumed');
        }

        $campaign->update(['status' => AudienceCampaign::STATUS_ACTIVE]);

        return $campaign;
    }

    /**
     * Complete a campaign and record results
     */
    public function completeCampaign(AudienceCampaign $campaign, array $results = []): AudienceCampaign
    {
        $campaign->markCompleted($results);

        return $campaign;
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStats(AudienceCampaign $campaign): array
    {
        $results = $campaign->results ?? [];

        // If email campaign, try to fetch updated stats from Brevo
        if ($campaign->isEmailCampaign() && isset($results['brevo_campaign_id'])) {
            /** @var BrevoAudienceProvider $brevoProvider */
            $brevoProvider = $this->exportService->getProvider(AudienceExport::PLATFORM_BREVO);
            $brevoStats = $brevoProvider->getCampaignStats($results['brevo_campaign_id']);

            if ($brevoStats) {
                $results = array_merge($results, $brevoStats);
            }
        }

        // Add calculated metrics
        if (isset($results['sent']) && $results['sent'] > 0) {
            $results['open_rate'] = isset($results['opens'])
                ? round(($results['opens'] / $results['sent']) * 100, 2)
                : null;

            $results['click_rate'] = isset($results['clicks'])
                ? round(($results['clicks'] / $results['sent']) * 100, 2)
                : null;
        }

        if (isset($results['clicks']) && $results['clicks'] > 0 && isset($results['conversions'])) {
            $results['conversion_rate'] = round(($results['conversions'] / $results['clicks']) * 100, 2);
        }

        if (isset($results['cost_cents']) && $results['cost_cents'] > 0 && isset($results['revenue_cents'])) {
            $results['roas'] = round($results['revenue_cents'] / $results['cost_cents'], 2);
        }

        return $results;
    }

    /**
     * Process scheduled campaigns that are due
     */
    public function processScheduledCampaigns(): int
    {
        $dueCampaigns = AudienceCampaign::scheduledForNow()->get();
        $processed = 0;

        foreach ($dueCampaigns as $campaign) {
            try {
                $this->launchCampaign($campaign);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to launch scheduled campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Create a quick campaign for an event
     */
    public function createEventCampaign(
        Event $event,
        string $campaignType,
        int $minMatchScore = 60,
        array $settings = []
    ): AudienceCampaign {
        // Create/get segment for this event
        $segment = $this->eventMatchingService->createEventTargetSegment(
            $event,
            $minMatchScore,
            "Target audience for: {$event->title}"
        );

        return $this->createCampaign(
            $event->tenant,
            "Promote: {$event->title}",
            $campaignType,
            $segment,
            $event,
            $settings
        );
    }

    /**
     * Get campaigns for a tenant
     */
    public function getTenantCampaigns(Tenant $tenant, ?string $status = null): Collection
    {
        $query = AudienceCampaign::forTenant($tenant->id)
            ->with(['segment', 'event', 'exports'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->withStatus($status);
        }

        return $query->get();
    }

    /**
     * Get campaign dashboard summary
     */
    public function getDashboardSummary(Tenant $tenant): array
    {
        $campaigns = AudienceCampaign::forTenant($tenant->id)->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $totalConversions = 0;

        foreach ($campaigns as $campaign) {
            $results = $campaign->results ?? [];
            $totalRevenue += $results['revenue_cents'] ?? 0;
            $totalCost += $results['cost_cents'] ?? 0;
            $totalConversions += $results['conversions'] ?? 0;
        }

        return [
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->where('status', AudienceCampaign::STATUS_ACTIVE)->count(),
            'completed_campaigns' => $campaigns->where('status', AudienceCampaign::STATUS_COMPLETED)->count(),
            'total_revenue_cents' => $totalRevenue,
            'total_cost_cents' => $totalCost,
            'total_conversions' => $totalConversions,
            'overall_roas' => $totalCost > 0 ? round($totalRevenue / $totalCost, 2) : null,
            'by_type' => [
                'email' => $campaigns->where('campaign_type', AudienceCampaign::TYPE_EMAIL)->count(),
                'meta_ads' => $campaigns->where('campaign_type', AudienceCampaign::TYPE_META_ADS)->count(),
                'google_ads' => $campaigns->where('campaign_type', AudienceCampaign::TYPE_GOOGLE_ADS)->count(),
                'tiktok_ads' => $campaigns->where('campaign_type', AudienceCampaign::TYPE_TIKTOK_ADS)->count(),
            ],
        ];
    }
}
