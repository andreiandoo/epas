<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;

class AnalyticsCacheService
{
    protected int $ttl;

    public function __construct()
    {
        $this->ttl = config('analytics.cache.ttl', 300);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        return Cache::remember($key, $ttl ?? $this->ttl, $callback);
    }

    public function getSummary(string $tenantId, ?int $eventId = null): ?array
    {
        $key = $this->buildKey('summary', $tenantId, $eventId);
        return Cache::get($key);
    }

    public function putSummary(string $tenantId, array $data, ?int $eventId = null): void
    {
        $key = $this->buildKey('summary', $tenantId, $eventId);
        Cache::put($key, $data, $this->ttl);
    }

    public function getRealtime(string $tenantId, ?int $eventId = null): ?array
    {
        $key = $this->buildKey('realtime', $tenantId, $eventId);
        return Cache::get($key);
    }

    public function putRealtime(string $tenantId, array $data, ?int $eventId = null): void
    {
        $key = $this->buildKey('realtime', $tenantId, $eventId);
        Cache::put($key, $data, 60); // 1 minute for realtime
    }

    public function invalidate(string $tenantId, ?int $eventId = null): void
    {
        $patterns = ['summary', 'realtime', 'widget'];

        foreach ($patterns as $pattern) {
            $key = $this->buildKey($pattern, $tenantId, $eventId);
            Cache::forget($key);
        }
    }

    protected function buildKey(string $type, string $tenantId, ?int $eventId = null): string
    {
        $key = "analytics:{$type}:{$tenantId}";
        if ($eventId) {
            $key .= ":{$eventId}";
        }
        return $key;
    }
}
