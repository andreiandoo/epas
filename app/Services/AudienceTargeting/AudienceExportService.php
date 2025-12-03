<?php

namespace App\Services\AudienceTargeting;

use App\Models\AudienceExport;
use App\Models\AudienceSegment;
use App\Models\Tenant;
use App\Services\AudienceTargeting\Providers\AudienceProviderInterface;
use App\Services\AudienceTargeting\Providers\BrevoAudienceProvider;
use App\Services\AudienceTargeting\Providers\GoogleAdsAudienceProvider;
use App\Services\AudienceTargeting\Providers\MetaAudienceProvider;
use App\Services\AudienceTargeting\Providers\TikTokAudienceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AudienceExportService
{
    protected array $providers = [];

    public function __construct()
    {
        // Initialize all providers
        $this->providers = [
            AudienceExport::PLATFORM_META => new MetaAudienceProvider(),
            AudienceExport::PLATFORM_GOOGLE => new GoogleAdsAudienceProvider(),
            AudienceExport::PLATFORM_TIKTOK => new TikTokAudienceProvider(),
            AudienceExport::PLATFORM_BREVO => new BrevoAudienceProvider(),
        ];
    }

    /**
     * Get a specific provider
     */
    public function getProvider(string $platform): AudienceProviderInterface
    {
        if (!isset($this->providers[$platform])) {
            throw new \InvalidArgumentException("Unknown platform: {$platform}");
        }

        return $this->providers[$platform];
    }

    /**
     * Get all available providers
     */
    public function getAvailableProviders(): array
    {
        $available = [];

        foreach ($this->providers as $platform => $provider) {
            $available[$platform] = [
                'platform' => $platform,
                'name' => $provider->getName(),
                'configured' => $provider->isConfigured(),
            ];
        }

        return $available;
    }

    /**
     * Export a segment to a platform
     */
    public function exportSegment(
        AudienceSegment $segment,
        string $platform,
        string $audienceName,
        ?string $description = null,
        ?int $campaignId = null
    ): AudienceExport {
        $provider = $this->getProvider($platform);

        if (!$provider->isConfigured()) {
            throw new \RuntimeException("Provider {$platform} is not configured");
        }

        // Create export record
        $export = AudienceExport::create([
            'tenant_id' => $segment->tenant_id,
            'segment_id' => $segment->id,
            'campaign_id' => $campaignId,
            'platform' => $platform,
            'export_type' => AudienceExport::TYPE_CUSTOM_AUDIENCE,
            'customer_count' => $segment->customer_count,
            'status' => AudienceExport::STATUS_PENDING,
        ]);

        try {
            $export->markProcessing();

            // Create audience on the platform
            $result = $provider->createCustomAudience($segment, $audienceName, $description);

            // Calculate expiration (platform-specific)
            $expiresAt = $this->calculateExpiration($platform);

            $export->markCompleted(
                $result['external_id'],
                $result['name'] ?? $audienceName,
                $result['matched_count'] ?? null,
                $expiresAt
            );

            Log::info('Audience exported successfully', [
                'export_id' => $export->id,
                'platform' => $platform,
                'segment_id' => $segment->id,
                'external_id' => $result['external_id'],
            ]);
        } catch (\Exception $e) {
            $export->markFailed($e->getMessage());

            Log::error('Audience export failed', [
                'export_id' => $export->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $export;
    }

    /**
     * Export to Meta (Facebook/Instagram)
     */
    public function exportToMeta(
        AudienceSegment $segment,
        string $audienceName,
        ?string $description = null
    ): AudienceExport {
        return $this->exportSegment(
            $segment,
            AudienceExport::PLATFORM_META,
            $audienceName,
            $description
        );
    }

    /**
     * Export to Google Ads
     */
    public function exportToGoogle(
        AudienceSegment $segment,
        string $audienceName,
        ?string $description = null
    ): AudienceExport {
        return $this->exportSegment(
            $segment,
            AudienceExport::PLATFORM_GOOGLE,
            $audienceName,
            $description
        );
    }

    /**
     * Export to TikTok Ads
     */
    public function exportToTikTok(
        AudienceSegment $segment,
        string $audienceName,
        ?string $description = null
    ): AudienceExport {
        return $this->exportSegment(
            $segment,
            AudienceExport::PLATFORM_TIKTOK,
            $audienceName,
            $description
        );
    }

    /**
     * Export to Brevo (email list)
     */
    public function exportToBrevo(
        AudienceSegment $segment,
        string $listName,
        ?string $description = null
    ): AudienceExport {
        return $this->exportSegment(
            $segment,
            AudienceExport::PLATFORM_BREVO,
            $listName,
            $description
        );
    }

    /**
     * Sync/update an existing export
     */
    public function syncExport(AudienceExport $export): AudienceExport
    {
        if (!$export->isCompleted() || !$export->external_audience_id) {
            throw new \RuntimeException('Cannot sync an export that is not completed');
        }

        $provider = $this->getProvider($export->platform);
        $segment = $export->segment;

        if (!$segment) {
            throw new \RuntimeException('Segment not found');
        }

        try {
            $export->markProcessing();

            // Get current customers from segment
            $customers = $segment->customers()->with('customer')->get()->pluck('customer');

            // Update the audience on the platform
            $result = $provider->updateCustomAudience(
                $export->external_audience_id,
                $customers
            );

            $export->update([
                'status' => AudienceExport::STATUS_COMPLETED,
                'customer_count' => $customers->count(),
                'matched_count' => $result['matched_count'] ?? null,
                'exported_at' => now(),
            ]);

            if (isset($result['matched_count']) && $export->customer_count > 0) {
                $export->update([
                    'match_rate' => round(($result['matched_count'] / $export->customer_count) * 100, 2),
                ]);
            }

            Log::info('Audience export synced', [
                'export_id' => $export->id,
                'platform' => $export->platform,
            ]);
        } catch (\Exception $e) {
            $export->markFailed($e->getMessage());

            Log::error('Audience export sync failed', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $export->fresh();
    }

    /**
     * Create a lookalike audience from an export
     */
    public function createLookalikeAudience(
        AudienceExport $sourceExport,
        string $audienceName,
        array $options = []
    ): AudienceExport {
        if (!$sourceExport->isCompleted() || !$sourceExport->external_audience_id) {
            throw new \RuntimeException('Source export must be completed');
        }

        $provider = $this->getProvider($sourceExport->platform);

        // Create export record for lookalike
        $export = AudienceExport::create([
            'tenant_id' => $sourceExport->tenant_id,
            'segment_id' => $sourceExport->segment_id,
            'platform' => $sourceExport->platform,
            'export_type' => AudienceExport::TYPE_LOOKALIKE,
            'customer_count' => 0, // Lookalikes don't have a source count
            'status' => AudienceExport::STATUS_PENDING,
        ]);

        try {
            $export->markProcessing();

            $result = $provider->createLookalikeAudience(
                $sourceExport->external_audience_id,
                $audienceName,
                $options
            );

            $export->markCompleted(
                $result['external_id'],
                $result['name'] ?? $audienceName,
                null,
                $this->calculateExpiration($sourceExport->platform)
            );

            Log::info('Lookalike audience created', [
                'export_id' => $export->id,
                'source_export_id' => $sourceExport->id,
            ]);
        } catch (\Exception $e) {
            $export->markFailed($e->getMessage());

            Log::error('Lookalike audience creation failed', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $export;
    }

    /**
     * Delete an audience from the platform
     */
    public function deleteExport(AudienceExport $export): bool
    {
        if (!$export->external_audience_id) {
            // No external audience to delete
            $export->delete();
            return true;
        }

        $provider = $this->getProvider($export->platform);

        try {
            $deleted = $provider->deleteCustomAudience($export->external_audience_id);

            if ($deleted) {
                $export->delete();

                Log::info('Audience export deleted', [
                    'export_id' => $export->id,
                    'platform' => $export->platform,
                    'external_id' => $export->external_audience_id,
                ]);
            }

            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to delete audience export', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get export statistics
     */
    public function getExportStats(Tenant $tenant): array
    {
        $exports = AudienceExport::forTenant($tenant->id)->get();

        $byPlatform = [];
        foreach ($this->providers as $platform => $provider) {
            $platformExports = $exports->where('platform', $platform);

            $byPlatform[$platform] = [
                'name' => $provider->getName(),
                'configured' => $provider->isConfigured(),
                'total_exports' => $platformExports->count(),
                'completed' => $platformExports->where('status', AudienceExport::STATUS_COMPLETED)->count(),
                'pending' => $platformExports->where('status', AudienceExport::STATUS_PENDING)->count(),
                'failed' => $platformExports->where('status', AudienceExport::STATUS_FAILED)->count(),
                'total_customers' => $platformExports->sum('customer_count'),
                'total_matched' => $platformExports->sum('matched_count'),
            ];
        }

        return [
            'total_exports' => $exports->count(),
            'by_platform' => $byPlatform,
        ];
    }

    /**
     * Get audience details from platform
     */
    public function getAudienceDetails(AudienceExport $export): ?array
    {
        if (!$export->external_audience_id) {
            return null;
        }

        $provider = $this->getProvider($export->platform);

        return $provider->getAudienceDetails($export->external_audience_id);
    }

    /**
     * Calculate expiration date based on platform
     */
    protected function calculateExpiration(string $platform): ?\DateTime
    {
        // Different platforms have different audience retention policies
        $daysUntilExpiration = match ($platform) {
            AudienceExport::PLATFORM_META => 180, // Meta audiences expire after ~180 days of inactivity
            AudienceExport::PLATFORM_GOOGLE => 540, // Google allows up to 540 days
            AudienceExport::PLATFORM_TIKTOK => 365, // TikTok default is 365 days
            AudienceExport::PLATFORM_BREVO => null, // Brevo lists don't expire
            default => 365,
        };

        if ($daysUntilExpiration === null) {
            return null;
        }

        return now()->addDays($daysUntilExpiration);
    }

    /**
     * Check and refresh expiring exports
     */
    public function refreshExpiringExports(int $daysBeforeExpiration = 30): int
    {
        $expiringExports = AudienceExport::completed()
            ->notExpired()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($daysBeforeExpiration))
            ->get();

        $refreshed = 0;

        foreach ($expiringExports as $export) {
            try {
                $this->syncExport($export);
                $refreshed++;
            } catch (\Exception $e) {
                Log::warning('Failed to refresh expiring export', [
                    'export_id' => $export->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $refreshed;
    }
}
