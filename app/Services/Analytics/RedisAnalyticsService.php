<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisAnalyticsService
{
    protected string $connection = 'analytics';

    // TTL values in seconds
    protected const TTL_LIVE_VISITOR = 300;      // 5 minutes
    protected const TTL_ACTIVITY = 600;          // 10 minutes
    protected const TTL_HOURLY_STATS = 172800;   // 48 hours

    /**
     * Get Redis connection
     */
    protected function redis()
    {
        return Redis::connection($this->connection);
    }

    /**
     * Track a visitor for live analytics
     */
    public function trackVisitor(int $eventId, string $visitorId, array $geo, string $action = 'page_view'): void
    {
        try {
            $redis = $this->redis();
            $now = now();

            // Add to live visitors set
            $visitorsKey = "live:event:{$eventId}:visitors";
            $redis->sadd($visitorsKey, $visitorId);
            $redis->expire($visitorsKey, self::TTL_LIVE_VISITOR);

            // Store geo data for this visitor
            if (!empty($geo['latitude']) && !empty($geo['longitude'])) {
                $geoKey = "live:event:{$eventId}:geo:{$visitorId}";
                $redis->setex($geoKey, self::TTL_LIVE_VISITOR, json_encode([
                    'lat' => (float) $geo['latitude'],
                    'lng' => (float) $geo['longitude'],
                    'city' => $geo['city'] ?? null,
                    'country' => $geo['country_code'] ?? null,
                    'time' => $now->toIso8601String(),
                ]));
            }

            // Add to activity feed (last 20 actions)
            $activityKey = "live:event:{$eventId}:activity";
            $activityData = json_encode([
                'visitor_id' => substr($visitorId, 0, 8),
                'action' => $this->formatAction($action),
                'city' => $geo['city'] ?? 'Unknown',
                'country' => $geo['country_code'] ?? 'Unknown',
                'flag' => $this->getCountryFlag($geo['country_code'] ?? null),
                'time' => $now->toIso8601String(),
            ]);

            $redis->lpush($activityKey, $activityData);
            $redis->ltrim($activityKey, 0, 19); // Keep only last 20
            $redis->expire($activityKey, self::TTL_ACTIVITY);

        } catch (\Exception $e) {
            Log::warning('Redis analytics tracking failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - Redis is nice-to-have, not critical
        }
    }

    /**
     * Track a specific action (add to cart, checkout, purchase)
     */
    public function trackAction(int $eventId, string $visitorId, string $action, ?float $value = null): void
    {
        try {
            $redis = $this->redis();
            $now = now();

            // Update activity feed
            $activityKey = "live:event:{$eventId}:activity";

            // Get visitor's geo from their last page view
            $geoKey = "live:event:{$eventId}:geo:{$visitorId}";
            $geoData = $redis->get($geoKey);
            $geo = $geoData ? json_decode($geoData, true) : [];

            $activityData = json_encode([
                'visitor_id' => substr($visitorId, 0, 8),
                'action' => $this->formatAction($action, $value),
                'city' => $geo['city'] ?? 'Unknown',
                'country' => $geo['country'] ?? 'Unknown',
                'flag' => $this->getCountryFlag($geo['country'] ?? null),
                'time' => $now->toIso8601String(),
            ]);

            $redis->lpush($activityKey, $activityData);
            $redis->ltrim($activityKey, 0, 19);
            $redis->expire($activityKey, self::TTL_ACTIVITY);

            // Increment hourly stats
            $hour = $now->format('Y-m-d-H');
            $statsKey = "stats:event:{$eventId}:hourly:{$hour}";

            $field = match ($action) {
                'page_view' => 'views',
                'add_to_cart' => 'carts',
                'begin_checkout' => 'checkouts',
                'purchase' => 'purchases',
                default => null,
            };

            if ($field) {
                $redis->hincrby($statsKey, $field, 1);
                if ($action === 'purchase' && $value) {
                    $redis->hincrbyfloat($statsKey, 'revenue', $value);
                }
                $redis->expire($statsKey, self::TTL_HOURLY_STATS);
            }

        } catch (\Exception $e) {
            Log::warning('Redis analytics action tracking failed', [
                'event_id' => $eventId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get live visitors count and locations for globe display
     */
    public function getLiveVisitors(int $eventId): array
    {
        try {
            $redis = $this->redis();

            // Get all live visitor IDs
            $visitorsKey = "live:event:{$eventId}:visitors";
            $visitorIds = $redis->smembers($visitorsKey);

            $count = count($visitorIds);
            $locations = [];

            // Get geo data for each visitor
            foreach ($visitorIds as $visitorId) {
                $geoKey = "live:event:{$eventId}:geo:{$visitorId}";
                $geoData = $redis->get($geoKey);

                if ($geoData) {
                    $geo = json_decode($geoData, true);
                    if (!empty($geo['lat']) && !empty($geo['lng'])) {
                        $locations[] = [
                            'lat' => $geo['lat'],
                            'lng' => $geo['lng'],
                            'city' => $geo['city'] ?? 'Unknown',
                            'country' => $geo['country'] ?? '',
                            'time' => isset($geo['time'])
                                ? \Carbon\Carbon::parse($geo['time'])->diffForHumans(short: true)
                                : 'now',
                        ];
                    }
                }
            }

            return [
                'count' => $count,
                'locations' => $locations,
            ];

        } catch (\Exception $e) {
            Log::warning('Redis getLiveVisitors failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            // Return empty data on failure
            return [
                'count' => 0,
                'locations' => [],
            ];
        }
    }

    /**
     * Get live activity feed
     */
    public function getLiveActivity(int $eventId, int $limit = 20): array
    {
        try {
            $redis = $this->redis();

            $activityKey = "live:event:{$eventId}:activity";
            $activities = $redis->lrange($activityKey, 0, $limit - 1);

            return array_map(function ($item) {
                $data = json_decode($item, true);

                // Format time for display
                if (!empty($data['time'])) {
                    $data['time'] = \Carbon\Carbon::parse($data['time'])->diffForHumans(short: true);
                }

                return $data;
            }, $activities);

        } catch (\Exception $e) {
            Log::warning('Redis getLiveActivity failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get combined live data for globe modal
     */
    public function getLiveDataForGlobe(int $eventId): array
    {
        $visitors = $this->getLiveVisitors($eventId);
        $activity = $this->getLiveActivity($eventId);

        return [
            'count' => $visitors['count'],
            'globeData' => $visitors['locations'],
            'activity' => $activity,
        ];
    }

    /**
     * Check if Redis is available
     */
    public function isAvailable(): bool
    {
        try {
            $this->redis()->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Format action for display
     */
    protected function formatAction(string $action, ?float $value = null): string
    {
        return match ($action) {
            'page_view' => 'Viewing event page',
            'view_tickets', 'view_item' => 'Viewing tickets',
            'add_to_cart' => 'Added tickets to cart',
            'begin_checkout' => 'Started checkout',
            'purchase' => $value ? sprintf('Completed purchase (%.0f RON)', $value) : 'Completed purchase',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    /**
     * Get country flag emoji from country code
     */
    protected function getCountryFlag(?string $countryCode): string
    {
        if (!$countryCode || strlen($countryCode) !== 2) {
            return '🌍';
        }

        // Convert country code to flag emoji
        $code = strtoupper($countryCode);
        $flag = '';

        for ($i = 0; $i < 2; $i++) {
            $flag .= mb_chr(ord($code[$i]) + 127397);
        }

        return $flag;
    }
}
