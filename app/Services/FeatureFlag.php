<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Feature Flag Service
 *
 * Manages per-tenant feature toggles with in-memory caching.
 * Reads from tenants.features JSONB column with fallback to global config.
 */
class FeatureFlag
{
    /**
     * In-memory cache for current request
     */
    private static array $memoryCache = [];

    /**
     * Cache TTL in seconds
     */
    private int $cacheTtl = 3600; // 1 hour

    /**
     * Check if a feature is enabled for a specific tenant
     *
     * @param string $feature Feature key (e.g., 'seating.enabled', 'seating.dynamic_pricing.enabled')
     * @param int|null $tenantId Tenant ID (null for global check)
     * @param mixed $default Default value if not found
     * @return mixed Feature value (usually bool, but can be string/int/array)
     */
    public function isEnabled(string $feature, ?int $tenantId = null, mixed $default = false): mixed
    {
        // If no tenant specified, return global config
        if ($tenantId === null) {
            return $this->getGlobalFeature($feature, $default);
        }

        // Check in-memory cache first
        $cacheKey = "tenant_{$tenantId}_{$feature}";
        if (isset(self::$memoryCache[$cacheKey])) {
            return self::$memoryCache[$cacheKey];
        }

        // Check Laravel cache
        $cachedValue = Cache::remember(
            $this->getCacheKey($tenantId, $feature),
            $this->cacheTtl,
            function () use ($tenantId, $feature, $default) {
                return $this->resolveFeatureFlag($tenantId, $feature, $default);
            }
        );

        // Store in memory cache
        self::$memoryCache[$cacheKey] = $cachedValue;

        return $cachedValue;
    }

    /**
     * Check if a feature is disabled for a specific tenant
     *
     * @param string $feature Feature key
     * @param int|null $tenantId Tenant ID
     * @return bool
     */
    public function isDisabled(string $feature, ?int $tenantId = null): bool
    {
        return !$this->isEnabled($feature, $tenantId, false);
    }

    /**
     * Get feature value (not just boolean)
     *
     * @param string $feature Feature key
     * @param int|null $tenantId Tenant ID
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $feature, ?int $tenantId = null, mixed $default = null): mixed
    {
        return $this->isEnabled($feature, $tenantId, $default);
    }

    /**
     * Resolve feature flag from tenant settings
     *
     * @param int $tenantId
     * @param string $feature
     * @param mixed $default
     * @return mixed
     */
    private function resolveFeatureFlag(int $tenantId, string $feature, mixed $default): mixed
    {
        try {
            $tenant = Tenant::find($tenantId);

            if (!$tenant) {
                Log::warning("FeatureFlag: Tenant {$tenantId} not found, using default");
                return $default;
            }

            // Check tenant features JSON column
            $features = $tenant->features ?? [];

            // Support nested keys (e.g., 'seating.enabled')
            $value = data_get($features, $feature);

            // If not found in tenant features, check tenant-level settings
            if ($value === null && isset($tenant->settings)) {
                $value = data_get($tenant->settings, $feature);
            }

            // Fall back to global config if not found
            if ($value === null) {
                return $this->getGlobalFeature($feature, $default);
            }

            return $value;
        } catch (\Exception $e) {
            Log::error("FeatureFlag: Error resolving feature '{$feature}' for tenant {$tenantId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $default;
        }
    }

    /**
     * Get feature from global config
     *
     * @param string $feature
     * @param mixed $default
     * @return mixed
     */
    private function getGlobalFeature(string $feature, mixed $default): mixed
    {
        // Convert dot notation to config key
        // e.g., 'seating.enabled' -> config('seating.enabled')
        return config($feature, $default);
    }

    /**
     * Get cache key for a feature flag
     *
     * @param int $tenantId
     * @param string $feature
     * @return string
     */
    private function getCacheKey(int $tenantId, string $feature): string
    {
        return "feature_flag:tenant_{$tenantId}:" . str_replace('.', '_', $feature);
    }

    /**
     * Clear cache for a specific tenant
     *
     * @param int $tenantId
     * @return void
     */
    public function clearTenantCache(int $tenantId): void
    {
        // Clear Laravel cache with wildcard pattern
        $pattern = "feature_flag:tenant_{$tenantId}:*";

        try {
            // For Redis, use scan and delete
            if (config('cache.default') === 'redis') {
                $redis = Cache::getRedis();
                $keys = $redis->keys($pattern);
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            }
        } catch (\Exception $e) {
            Log::warning("FeatureFlag: Failed to clear cache for tenant {$tenantId}", [
                'error' => $e->getMessage(),
            ]);
        }

        // Clear in-memory cache for this tenant
        foreach (self::$memoryCache as $key => $value) {
            if (str_starts_with($key, "tenant_{$tenantId}_")) {
                unset(self::$memoryCache[$key]);
            }
        }
    }

    /**
     * Clear all feature flag cache
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        try {
            Cache::flush();
        } catch (\Exception $e) {
            Log::warning("FeatureFlag: Failed to clear all cache", [
                'error' => $e->getMessage(),
            ]);
        }

        self::$memoryCache = [];
    }

    /**
     * Set a feature flag value for a tenant (persists to database)
     *
     * @param int $tenantId
     * @param string $feature
     * @param mixed $value
     * @return bool Success
     */
    public function setFeature(int $tenantId, string $feature, mixed $value): bool
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);

            $features = $tenant->features ?? [];

            // Set nested key
            data_set($features, $feature, $value);

            $tenant->features = $features;
            $tenant->save();

            // Clear cache for this tenant
            $this->clearTenantCache($tenantId);

            Log::info("FeatureFlag: Set feature '{$feature}' for tenant {$tenantId}", [
                'value' => $value,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("FeatureFlag: Failed to set feature '{$feature}' for tenant {$tenantId}", [
                'error' => $e->getMessage(),
                'value' => $value,
            ]);

            return false;
        }
    }

    /**
     * Remove a feature flag for a tenant
     *
     * @param int $tenantId
     * @param string $feature
     * @return bool Success
     */
    public function removeFeature(int $tenantId, string $feature): bool
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);

            $features = $tenant->features ?? [];

            // Remove nested key
            data_forget($features, $feature);

            $tenant->features = $features;
            $tenant->save();

            // Clear cache for this tenant
            $this->clearTenantCache($tenantId);

            Log::info("FeatureFlag: Removed feature '{$feature}' for tenant {$tenantId}");

            return true;
        } catch (\Exception $e) {
            Log::error("FeatureFlag: Failed to remove feature '{$feature}' for tenant {$tenantId}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all features for a tenant
     *
     * @param int $tenantId
     * @return array
     */
    public function getAllFeatures(int $tenantId): array
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);

            return $tenant->features ?? [];
        } catch (\Exception $e) {
            Log::error("FeatureFlag: Failed to get all features for tenant {$tenantId}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
