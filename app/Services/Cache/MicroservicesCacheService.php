<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Microservices Cache Service
 *
 * Provides intelligent caching for microservices infrastructure:
 * - Catalog caching with warming
 * - Tenant configuration caching
 * - Feature flag caching
 * - Subscription caching
 * - Cache invalidation strategies
 */
class MicroservicesCacheService
{
    /**
     * Cache key prefixes
     */
    const PREFIX_CATALOG = 'ms:catalog';
    const PREFIX_SUBSCRIPTION = 'ms:subscription';
    const PREFIX_CONFIG = 'ms:config';
    const PREFIX_FEATURE_FLAG = 'ms:flag';
    const PREFIX_WEBHOOK = 'ms:webhook';

    /**
     * Get microservices catalog
     *
     * @return array
     */
    public function getCatalog(): array
    {
        $ttl = config('microservices.cache.catalog_ttl', 3600); // 1 hour default

        return Cache::remember(self::PREFIX_CATALOG . ':all', $ttl, function () {
            return DB::table('microservices')
                ->select([
                    'id',
                    'name',
                    'slug',
                    'description',
                    'short_description',
                    'icon',
                    'category',
                    'price_monthly',
                    'price_yearly',
                    'status',
                    'features',
                    'limits',
                    'metadata',
                ])
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get single microservice from catalog
     *
     * @param string $microserviceId
     * @return array|null
     */
    public function getMicroservice(string $microserviceId): ?array
    {
        $catalog = $this->getCatalog();

        foreach ($catalog as $microservice) {
            if ($microservice->id === $microserviceId) {
                return (array) $microservice;
            }
        }

        return null;
    }

    /**
     * Get tenant's active subscriptions
     *
     * @param string $tenantId
     * @return array
     */
    public function getTenantSubscriptions(string $tenantId): array
    {
        $cacheKey = self::PREFIX_SUBSCRIPTION . ":{$tenantId}";
        $ttl = config('microservices.cache.subscription_ttl', 300); // 5 minutes default

        return Cache::remember($cacheKey, $ttl, function () use ($tenantId) {
            return DB::table('tenant_microservices')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->get()
                ->toArray();
        });
    }

    /**
     * Check if tenant has active subscription for a microservice
     *
     * @param string $tenantId
     * @param string $microserviceId
     * @return bool
     */
    public function hasActiveSubscription(string $tenantId, string $microserviceId): bool
    {
        $subscriptions = $this->getTenantSubscriptions($tenantId);

        foreach ($subscriptions as $subscription) {
            if ($subscription->microservice_id === $microserviceId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get tenant configuration
     *
     * @param string $tenantId
     * @param string|null $key Specific config key or null for all
     * @return mixed
     */
    public function getTenantConfig(string $tenantId, ?string $key = null)
    {
        $cacheKey = self::PREFIX_CONFIG . ":{$tenantId}";
        $ttl = config('microservices.cache.config_ttl', 600); // 10 minutes default

        $config = Cache::remember($cacheKey, $ttl, function () use ($tenantId) {
            $configs = DB::table('tenant_configs')
                ->where('tenant_id', $tenantId)
                ->get();

            $result = [];
            foreach ($configs as $config) {
                $result[$config->key] = json_decode($config->value, true);
            }

            return $result;
        });

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * Get feature flag value
     *
     * @param string $flagKey
     * @param string|null $tenantId Null for global flags
     * @param mixed $default Default value if flag not found
     * @return mixed
     */
    public function getFeatureFlag(string $flagKey, ?string $tenantId = null, $default = false)
    {
        $cacheKey = $tenantId
            ? self::PREFIX_FEATURE_FLAG . ":{$tenantId}:{$flagKey}"
            : self::PREFIX_FEATURE_FLAG . ":global:{$flagKey}";

        $ttl = config('feature_flags.cache_ttl', 300); // 5 minutes default

        return Cache::remember($cacheKey, $ttl, function () use ($flagKey, $tenantId, $default) {
            $flag = DB::table('feature_flags')
                ->where('flag_key', $flagKey)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$flag) {
                return $default;
            }

            return $flag->enabled ? ($flag->value ?? true) : $default;
        });
    }

    /**
     * Get tenant webhooks
     *
     * @param string $tenantId
     * @return array
     */
    public function getTenantWebhooks(string $tenantId): array
    {
        $cacheKey = self::PREFIX_WEBHOOK . ":{$tenantId}";
        $ttl = config('microservices.cache.webhook_ttl', 300); // 5 minutes default

        return Cache::remember($cacheKey, $ttl, function () use ($tenantId) {
            return DB::table('tenant_webhooks')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get()
                ->toArray();
        });
    }

    /**
     * Invalidate catalog cache
     *
     * @return void
     */
    public function invalidateCatalog(): void
    {
        Cache::forget(self::PREFIX_CATALOG . ':all');
    }

    /**
     * Invalidate tenant subscription cache
     *
     * @param string $tenantId
     * @return void
     */
    public function invalidateTenantSubscriptions(string $tenantId): void
    {
        $cacheKey = self::PREFIX_SUBSCRIPTION . ":{$tenantId}";
        Cache::forget($cacheKey);
    }

    /**
     * Invalidate tenant configuration cache
     *
     * @param string $tenantId
     * @return void
     */
    public function invalidateTenantConfig(string $tenantId): void
    {
        $cacheKey = self::PREFIX_CONFIG . ":{$tenantId}";
        Cache::forget($cacheKey);
    }

    /**
     * Invalidate feature flag cache
     *
     * @param string $flagKey
     * @param string|null $tenantId
     * @return void
     */
    public function invalidateFeatureFlag(string $flagKey, ?string $tenantId = null): void
    {
        $cacheKey = $tenantId
            ? self::PREFIX_FEATURE_FLAG . ":{$tenantId}:{$flagKey}"
            : self::PREFIX_FEATURE_FLAG . ":global:{$flagKey}";

        Cache::forget($cacheKey);
    }

    /**
     * Invalidate tenant webhooks cache
     *
     * @param string $tenantId
     * @return void
     */
    public function invalidateTenantWebhooks(string $tenantId): void
    {
        $cacheKey = self::PREFIX_WEBHOOK . ":{$tenantId}";
        Cache::forget($cacheKey);
    }

    /**
     * Invalidate all tenant-related caches
     *
     * @param string $tenantId
     * @return void
     */
    public function invalidateAllTenantCaches(string $tenantId): void
    {
        $this->invalidateTenantSubscriptions($tenantId);
        $this->invalidateTenantConfig($tenantId);
        $this->invalidateTenantWebhooks($tenantId);

        // Invalidate all feature flags for this tenant
        Cache::flush(); // Note: This is aggressive, consider using tags for more granular control
    }

    /**
     * Warm up caches for a tenant
     *
     * Pre-loads frequently accessed data into cache
     *
     * @param string $tenantId
     * @return void
     */
    public function warmTenantCache(string $tenantId): void
    {
        // Pre-load subscriptions
        $this->getTenantSubscriptions($tenantId);

        // Pre-load configuration
        $this->getTenantConfig($tenantId);

        // Pre-load webhooks
        $this->getTenantWebhooks($tenantId);

        // Pre-load common feature flags
        $commonFlags = [
            'whatsapp_enabled',
            'efactura_enabled',
            'accounting_enabled',
            'insurance_enabled',
        ];

        foreach ($commonFlags as $flag) {
            $this->getFeatureFlag($flag, $tenantId);
        }
    }

    /**
     * Warm up global caches
     *
     * Pre-loads frequently accessed global data
     *
     * @return void
     */
    public function warmGlobalCache(): void
    {
        // Pre-load microservices catalog
        $this->getCatalog();

        // Pre-load global feature flags
        $globalFlags = DB::table('feature_flags')
            ->whereNull('tenant_id')
            ->get();

        foreach ($globalFlags as $flag) {
            $this->getFeatureFlag($flag->flag_key, null);
        }
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        // Note: This requires a cache driver that supports this (like Redis)
        try {
            $store = Cache::getStore();

            if (method_exists($store, 'connection')) {
                $redis = $store->connection();

                // Get all keys matching our prefixes
                $prefixes = [
                    self::PREFIX_CATALOG,
                    self::PREFIX_SUBSCRIPTION,
                    self::PREFIX_CONFIG,
                    self::PREFIX_FEATURE_FLAG,
                    self::PREFIX_WEBHOOK,
                ];

                $stats = [];
                foreach ($prefixes as $prefix) {
                    $keys = $redis->keys("{$prefix}:*");
                    $stats[$prefix] = count($keys);
                }

                return [
                    'driver' => config('cache.default'),
                    'cached_items' => $stats,
                    'total_keys' => array_sum($stats),
                ];
            }
        } catch (\Exception $e) {
            // Cache stats not available
        }

        return [
            'driver' => config('cache.default'),
            'stats_available' => false,
        ];
    }
}
