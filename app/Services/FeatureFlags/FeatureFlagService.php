<?php

namespace App\Services\FeatureFlags;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Feature Flag Service
 *
 * Manages feature flags for gradual rollouts, A/B testing,
 * and tenant-specific feature enabling/disabling.
 */
class FeatureFlagService
{
    /**
     * Cache TTL for feature flags (in seconds)
     */
    protected const CACHE_TTL = 300; // 5 minutes

    /**
     * Check if a feature is enabled
     *
     * @param string $featureKey Feature key (e.g., 'microservices.whatsapp.enabled')
     * @param string|null $tenantId Tenant ID for tenant-specific checks
     * @param array $context Additional context for evaluation (user_id, email, etc.)
     * @return bool
     */
    public function isEnabled(string $featureKey, ?string $tenantId = null, array $context = []): bool
    {
        // Check tenant-specific override first
        if ($tenantId) {
            $tenantFlag = $this->getTenantFeatureFlag($tenantId, $featureKey);

            if ($tenantFlag) {
                return $tenantFlag->is_enabled;
            }
        }

        // Check global feature flag
        $feature = $this->getFeatureFlag($featureKey);

        if (!$feature) {
            // Feature doesn't exist, default to disabled
            return false;
        }

        if (!$feature->is_enabled) {
            return false;
        }

        // Apply rollout strategy
        return $this->evaluateRolloutStrategy($feature, $tenantId, $context);
    }

    /**
     * Enable a feature globally
     *
     * @param string $featureKey Feature key
     * @return bool Success
     */
    public function enableFeature(string $featureKey): bool
    {
        try {
            DB::table('feature_flags')
                ->where('key', $featureKey)
                ->update([
                    'is_enabled' => true,
                    'updated_at' => now(),
                ]);

            $this->clearCache($featureKey);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to enable feature', [
                'feature_key' => $featureKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Disable a feature globally
     *
     * @param string $featureKey Feature key
     * @return bool Success
     */
    public function disableFeature(string $featureKey): bool
    {
        try {
            DB::table('feature_flags')
                ->where('key', $featureKey)
                ->update([
                    'is_enabled' => false,
                    'updated_at' => now(),
                ]);

            $this->clearCache($featureKey);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to disable feature', [
                'feature_key' => $featureKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Enable a feature for a specific tenant
     *
     * @param string $tenantId Tenant ID
     * @param string $featureKey Feature key
     * @param string|null $enabledBy Who enabled it
     * @return bool Success
     */
    public function enableForTenant(string $tenantId, string $featureKey, ?string $enabledBy = null): bool
    {
        try {
            DB::table('tenant_feature_flags')
                ->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'feature_key' => $featureKey,
                    ],
                    [
                        'is_enabled' => true,
                        'enabled_at' => now(),
                        'enabled_by' => $enabledBy,
                        'updated_at' => now(),
                    ]
                );

            $this->clearCache($featureKey, $tenantId);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to enable feature for tenant', [
                'tenant_id' => $tenantId,
                'feature_key' => $featureKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Disable a feature for a specific tenant
     *
     * @param string $tenantId Tenant ID
     * @param string $featureKey Feature key
     * @param string|null $disabledBy Who disabled it
     * @return bool Success
     */
    public function disableForTenant(string $tenantId, string $featureKey, ?string $disabledBy = null): bool
    {
        try {
            DB::table('tenant_feature_flags')
                ->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'feature_key' => $featureKey,
                    ],
                    [
                        'is_enabled' => false,
                        'disabled_at' => now(),
                        'disabled_by' => $disabledBy,
                        'updated_at' => now(),
                    ]
                );

            $this->clearCache($featureKey, $tenantId);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to disable feature for tenant', [
                'tenant_id' => $tenantId,
                'feature_key' => $featureKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create a new feature flag
     *
     * @param array $data Feature flag configuration
     * @return array {success: bool, feature_id: int|null, message: string}
     */
    public function createFeature(array $data): array
    {
        try {
            if (empty($data['key'])) {
                return [
                    'success' => false,
                    'feature_id' => null,
                    'message' => 'Feature key is required',
                ];
            }

            // Check if feature already exists
            $existing = DB::table('feature_flags')
                ->where('key', $data['key'])
                ->first();

            if ($existing) {
                return [
                    'success' => false,
                    'feature_id' => null,
                    'message' => 'Feature already exists',
                ];
            }

            $featureId = DB::table('feature_flags')->insertGetId([
                'key' => $data['key'],
                'name' => $data['name'] ?? $data['key'],
                'description' => $data['description'] ?? null,
                'is_enabled' => $data['is_enabled'] ?? false,
                'rollout_strategy' => $data['rollout_strategy'] ?? 'all',
                'rollout_percentage' => $data['rollout_percentage'] ?? null,
                'whitelist' => !empty($data['whitelist']) ? json_encode($data['whitelist']) : null,
                'conditions' => !empty($data['conditions']) ? json_encode($data['conditions']) : null,
                'metadata' => !empty($data['metadata']) ? json_encode($data['metadata']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => true,
                'feature_id' => $featureId,
                'message' => 'Feature created successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create feature', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'feature_id' => null,
                'message' => 'Failed to create feature: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update feature flag configuration
     *
     * @param string $featureKey Feature key
     * @param array $data Updated configuration
     * @return bool Success
     */
    public function updateFeature(string $featureKey, array $data): bool
    {
        try {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (isset($data['is_enabled'])) {
                $updateData['is_enabled'] = $data['is_enabled'];
            }

            if (isset($data['rollout_strategy'])) {
                $updateData['rollout_strategy'] = $data['rollout_strategy'];
            }

            if (isset($data['rollout_percentage'])) {
                $updateData['rollout_percentage'] = $data['rollout_percentage'];
            }

            if (isset($data['whitelist'])) {
                $updateData['whitelist'] = json_encode($data['whitelist']);
            }

            if (isset($data['conditions'])) {
                $updateData['conditions'] = json_encode($data['conditions']);
            }

            $updateData['updated_at'] = now();

            DB::table('feature_flags')
                ->where('key', $featureKey)
                ->update($updateData);

            $this->clearCache($featureKey);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update feature', [
                'feature_key' => $featureKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all enabled features for a tenant
     *
     * @param string $tenantId Tenant ID
     * @return array Array of enabled feature keys
     */
    public function getEnabledFeatures(string $tenantId): array
    {
        $enabledFeatures = [];

        // Get all global features
        $globalFeatures = DB::table('feature_flags')
            ->where('is_enabled', true)
            ->get();

        foreach ($globalFeatures as $feature) {
            if ($this->isEnabled($feature->key, $tenantId)) {
                $enabledFeatures[] = $feature->key;
            }
        }

        // Get tenant-specific overrides
        $tenantFeatures = DB::table('tenant_feature_flags')
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->get();

        foreach ($tenantFeatures as $tenantFeature) {
            if (!in_array($tenantFeature->feature_key, $enabledFeatures)) {
                $enabledFeatures[] = $tenantFeature->feature_key;
            }
        }

        return $enabledFeatures;
    }

    /**
     * Get feature flag from database (with caching)
     */
    protected function getFeatureFlag(string $featureKey)
    {
        return Cache::remember(
            "feature_flag:{$featureKey}",
            self::CACHE_TTL,
            function () use ($featureKey) {
                return DB::table('feature_flags')
                    ->where('key', $featureKey)
                    ->first();
            }
        );
    }

    /**
     * Get tenant-specific feature flag (with caching)
     */
    protected function getTenantFeatureFlag(string $tenantId, string $featureKey)
    {
        return Cache::remember(
            "tenant_feature_flag:{$tenantId}:{$featureKey}",
            self::CACHE_TTL,
            function () use ($tenantId, $featureKey) {
                return DB::table('tenant_feature_flags')
                    ->where('tenant_id', $tenantId)
                    ->where('feature_key', $featureKey)
                    ->first();
            }
        );
    }

    /**
     * Evaluate rollout strategy
     */
    protected function evaluateRolloutStrategy($feature, ?string $tenantId, array $context): bool
    {
        switch ($feature->rollout_strategy) {
            case 'all':
                return true;

            case 'percentage':
                return $this->evaluatePercentageRollout($feature, $tenantId);

            case 'whitelist':
                return $this->evaluateWhitelist($feature, $tenantId, $context);

            case 'custom':
                return $this->evaluateCustomConditions($feature, $tenantId, $context);

            default:
                return false;
        }
    }

    /**
     * Evaluate percentage-based rollout
     */
    protected function evaluatePercentageRollout($feature, ?string $tenantId): bool
    {
        if (!$tenantId || !$feature->rollout_percentage) {
            return false;
        }

        // Consistent hashing to ensure same tenant always gets same result
        $hash = crc32($tenantId . $feature->key);
        $percentage = ($hash % 100) + 1;

        return $percentage <= $feature->rollout_percentage;
    }

    /**
     * Evaluate whitelist-based rollout
     */
    protected function evaluateWhitelist($feature, ?string $tenantId, array $context): bool
    {
        if (!$feature->whitelist) {
            return false;
        }

        $whitelist = json_decode($feature->whitelist, true);

        // Check if tenant ID is in whitelist
        if ($tenantId && in_array($tenantId, $whitelist)) {
            return true;
        }

        // Check if user ID is in whitelist
        if (!empty($context['user_id']) && in_array($context['user_id'], $whitelist)) {
            return true;
        }

        return false;
    }

    /**
     * Evaluate custom conditions
     */
    protected function evaluateCustomConditions($feature, ?string $tenantId, array $context): bool
    {
        if (!$feature->conditions) {
            return false;
        }

        $conditions = json_decode($feature->conditions, true);

        // Simple condition evaluation (can be extended)
        foreach ($conditions as $key => $value) {
            if (!isset($context[$key]) || $context[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear feature flag cache
     */
    protected function clearCache(string $featureKey, ?string $tenantId = null): void
    {
        Cache::forget("feature_flag:{$featureKey}");

        if ($tenantId) {
            Cache::forget("tenant_feature_flag:{$tenantId}:{$featureKey}");
        }
    }
}
