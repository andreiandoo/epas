<?php

namespace App\Services\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\PlatformConversion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttributionModelService
{
    // Attribution models
    const MODEL_FIRST_TOUCH = 'first_touch';
    const MODEL_LAST_TOUCH = 'last_touch';
    const MODEL_LINEAR = 'linear';
    const MODEL_TIME_DECAY = 'time_decay';
    const MODEL_POSITION_BASED = 'position_based';
    const MODEL_DATA_DRIVEN = 'data_driven';

    // Default attribution window in days
    protected int $attributionWindow = 30;

    // Time decay half-life in days
    protected float $decayHalfLife = 7;

    // Position-based weights (first, middle, last)
    protected array $positionWeights = [0.4, 0.2, 0.4];

    public function setAttributionWindow(int $days): self
    {
        $this->attributionWindow = $days;
        return $this;
    }

    public function setDecayHalfLife(float $days): self
    {
        $this->decayHalfLife = $days;
        return $this;
    }

    /**
     * Calculate attribution for a single conversion
     */
    public function calculateAttributionForConversion(
        CoreCustomerEvent $conversionEvent,
        string $model = self::MODEL_LAST_TOUCH
    ): array {
        // Get all touchpoints before conversion
        $touchpoints = $this->getTouchpointsForConversion($conversionEvent);

        if ($touchpoints->isEmpty()) {
            return [
                'model' => $model,
                'conversion_value' => $conversionEvent->conversion_value ?? 0,
                'touchpoints' => [],
                'attributed' => [],
            ];
        }

        return match ($model) {
            self::MODEL_FIRST_TOUCH => $this->attributeFirstTouch($touchpoints, $conversionEvent),
            self::MODEL_LAST_TOUCH => $this->attributeLastTouch($touchpoints, $conversionEvent),
            self::MODEL_LINEAR => $this->attributeLinear($touchpoints, $conversionEvent),
            self::MODEL_TIME_DECAY => $this->attributeTimeDecay($touchpoints, $conversionEvent),
            self::MODEL_POSITION_BASED => $this->attributePositionBased($touchpoints, $conversionEvent),
            self::MODEL_DATA_DRIVEN => $this->attributeDataDriven($touchpoints, $conversionEvent),
            default => $this->attributeLastTouch($touchpoints, $conversionEvent),
        };
    }

    /**
     * Compare attribution models for a date range
     */
    public function compareModels(
        Carbon $startDate,
        Carbon $endDate,
        ?int $tenantId = null
    ): array {
        $conversions = CoreCustomerEvent::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_converted', true)
            ->where('conversion_value', '>', 0)
            ->get();

        $models = [
            self::MODEL_FIRST_TOUCH,
            self::MODEL_LAST_TOUCH,
            self::MODEL_LINEAR,
            self::MODEL_TIME_DECAY,
            self::MODEL_POSITION_BASED,
        ];

        $results = [];

        foreach ($models as $model) {
            $channelAttribution = [];
            $sourceAttribution = [];
            $campaignAttribution = [];

            foreach ($conversions as $conversion) {
                $attribution = $this->calculateAttributionForConversion($conversion, $model);

                foreach ($attribution['attributed'] as $attr) {
                    // By channel/platform
                    $channel = $this->getChannelFromTouchpoint($attr['touchpoint']);
                    if (!isset($channelAttribution[$channel])) {
                        $channelAttribution[$channel] = ['value' => 0, 'conversions' => 0];
                    }
                    $channelAttribution[$channel]['value'] += $attr['attributed_value'];
                    $channelAttribution[$channel]['conversions'] += $attr['attributed_conversion'];

                    // By source
                    $source = $attr['touchpoint']['utm_source'] ?? $attr['touchpoint']['source'] ?? 'direct';
                    if (!isset($sourceAttribution[$source])) {
                        $sourceAttribution[$source] = ['value' => 0, 'conversions' => 0];
                    }
                    $sourceAttribution[$source]['value'] += $attr['attributed_value'];
                    $sourceAttribution[$source]['conversions'] += $attr['attributed_conversion'];

                    // By campaign
                    $campaign = $attr['touchpoint']['utm_campaign'] ?? 'none';
                    if (!isset($campaignAttribution[$campaign])) {
                        $campaignAttribution[$campaign] = ['value' => 0, 'conversions' => 0];
                    }
                    $campaignAttribution[$campaign]['value'] += $attr['attributed_value'];
                    $campaignAttribution[$campaign]['conversions'] += $attr['attributed_conversion'];
                }
            }

            $results[$model] = [
                'model' => $model,
                'model_name' => $this->getModelDisplayName($model),
                'total_conversions' => $conversions->count(),
                'total_value' => $conversions->sum('conversion_value'),
                'by_channel' => $this->sortByValue($channelAttribution),
                'by_source' => $this->sortByValue($sourceAttribution),
                'by_campaign' => $this->sortByValue($campaignAttribution),
            ];
        }

        return [
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'attribution_window' => $this->attributionWindow,
            'total_conversions' => $conversions->count(),
            'total_value' => $conversions->sum('conversion_value'),
            'models' => $results,
            'comparison' => $this->generateComparison($results),
        ];
    }

    /**
     * Get channel-level attribution report
     */
    public function getChannelAttributionReport(
        string $model,
        Carbon $startDate,
        Carbon $endDate,
        ?int $tenantId = null
    ): array {
        $conversions = CoreCustomerEvent::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_converted', true)
            ->get();

        $channelData = [];

        foreach ($conversions as $conversion) {
            $attribution = $this->calculateAttributionForConversion($conversion, $model);

            foreach ($attribution['attributed'] as $attr) {
                $channel = $this->getChannelFromTouchpoint($attr['touchpoint']);

                if (!isset($channelData[$channel])) {
                    $channelData[$channel] = [
                        'channel' => $channel,
                        'conversions' => 0,
                        'attributed_conversions' => 0,
                        'revenue' => 0,
                        'touchpoints' => 0,
                        'first_touch_conversions' => 0,
                        'last_touch_conversions' => 0,
                        'assisted_conversions' => 0,
                    ];
                }

                $channelData[$channel]['attributed_conversions'] += $attr['attributed_conversion'];
                $channelData[$channel]['revenue'] += $attr['attributed_value'];
                $channelData[$channel]['touchpoints']++;

                if ($attr['position'] === 'first') {
                    $channelData[$channel]['first_touch_conversions'] += 1;
                }
                if ($attr['position'] === 'last') {
                    $channelData[$channel]['last_touch_conversions'] += 1;
                }
                if ($attr['position'] === 'middle') {
                    $channelData[$channel]['assisted_conversions'] += 1;
                }
            }
        }

        // Calculate derived metrics
        foreach ($channelData as &$channel) {
            $channel['conversions'] = round($channel['attributed_conversions'], 2);
            $channel['avg_touchpoints_per_conversion'] = $channel['conversions'] > 0
                ? round($channel['touchpoints'] / $channel['conversions'], 2)
                : 0;
            $channel['roas'] = $channel['revenue'] > 0 ? round($channel['revenue'] / max(1, $channel['touchpoints']), 2) : 0;
        }

        return [
            'model' => $model,
            'model_name' => $this->getModelDisplayName($model),
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'channels' => collect($channelData)->sortByDesc('revenue')->values()->toArray(),
        ];
    }

    /**
     * Get customer journey analysis
     */
    public function analyzeCustomerJourney(int $customerId): array
    {
        $customer = CoreCustomer::find($customerId);
        if (!$customer) {
            return ['error' => 'Customer not found'];
        }

        $touchpoints = CoreCustomerEvent::where('core_customer_id', $customerId)
            ->orderBy('created_at')
            ->get();

        $conversions = $touchpoints->filter(fn($t) => $t->is_converted);

        $journeyPath = $touchpoints->map(function ($tp) {
            return [
                'timestamp' => $tp->created_at->toIso8601String(),
                'event_type' => $tp->event_type,
                'channel' => $this->getChannelFromEvent($tp),
                'source' => $tp->utm_source ?? 'direct',
                'campaign' => $tp->utm_campaign,
                'is_conversion' => $tp->is_converted,
                'value' => $tp->conversion_value,
            ];
        });

        // Calculate channel contribution
        $channelCounts = $touchpoints->groupBy(fn($tp) => $this->getChannelFromEvent($tp))
            ->map->count();

        return [
            'customer_id' => $customerId,
            'customer_uuid' => $customer->uuid,
            'total_touchpoints' => $touchpoints->count(),
            'total_conversions' => $conversions->count(),
            'total_revenue' => $conversions->sum('conversion_value'),
            'first_touch' => $journeyPath->first(),
            'last_touch' => $journeyPath->last(),
            'journey_path' => $journeyPath->toArray(),
            'channel_distribution' => $channelCounts->toArray(),
            'avg_time_to_conversion' => $this->calculateAvgTimeToConversion($touchpoints, $conversions),
            'journey_length' => $touchpoints->count(),
        ];
    }

    /**
     * Get touchpoints leading to a conversion
     */
    protected function getTouchpointsForConversion(CoreCustomerEvent $conversion): Collection
    {
        $windowStart = $conversion->created_at->copy()->subDays($this->attributionWindow);

        return CoreCustomerEvent::where('core_customer_id', $conversion->core_customer_id)
            ->where('created_at', '<', $conversion->created_at)
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'timestamp' => $event->created_at,
                    'event_type' => $event->event_type,
                    'utm_source' => $event->utm_source,
                    'utm_medium' => $event->utm_medium,
                    'utm_campaign' => $event->utm_campaign,
                    'gclid' => $event->gclid,
                    'fbclid' => $event->fbclid,
                    'ttclid' => $event->ttclid,
                    'li_fat_id' => $event->li_fat_id,
                    'referrer' => $event->referrer,
                    'source' => $event->getAttributionSource(),
                ];
            });
    }

    /**
     * First-touch attribution
     */
    protected function attributeFirstTouch(Collection $touchpoints, CoreCustomerEvent $conversion): array
    {
        $value = $conversion->conversion_value ?? 0;
        $first = $touchpoints->first();

        return [
            'model' => self::MODEL_FIRST_TOUCH,
            'conversion_value' => $value,
            'touchpoints' => $touchpoints->toArray(),
            'attributed' => [
                [
                    'touchpoint' => $first,
                    'weight' => 1.0,
                    'attributed_value' => $value,
                    'attributed_conversion' => 1,
                    'position' => 'first',
                ],
            ],
        ];
    }

    /**
     * Last-touch attribution
     */
    protected function attributeLastTouch(Collection $touchpoints, CoreCustomerEvent $conversion): array
    {
        $value = $conversion->conversion_value ?? 0;
        $last = $touchpoints->last();

        return [
            'model' => self::MODEL_LAST_TOUCH,
            'conversion_value' => $value,
            'touchpoints' => $touchpoints->toArray(),
            'attributed' => [
                [
                    'touchpoint' => $last,
                    'weight' => 1.0,
                    'attributed_value' => $value,
                    'attributed_conversion' => 1,
                    'position' => 'last',
                ],
            ],
        ];
    }

    /**
     * Linear attribution (equal credit)
     */
    protected function attributeLinear(Collection $touchpoints, CoreCustomerEvent $conversion): array
    {
        $value = $conversion->conversion_value ?? 0;
        $count = $touchpoints->count();
        $weightPerTouch = $count > 0 ? 1 / $count : 0;
        $valuePerTouch = $count > 0 ? $value / $count : 0;

        $attributed = $touchpoints->map(function ($tp, $index) use ($weightPerTouch, $valuePerTouch, $count) {
            $position = match (true) {
                $index === 0 => 'first',
                $index === $count - 1 => 'last',
                default => 'middle',
            };

            return [
                'touchpoint' => $tp,
                'weight' => $weightPerTouch,
                'attributed_value' => $valuePerTouch,
                'attributed_conversion' => $weightPerTouch,
                'position' => $position,
            ];
        });

        return [
            'model' => self::MODEL_LINEAR,
            'conversion_value' => $value,
            'touchpoints' => $touchpoints->toArray(),
            'attributed' => $attributed->toArray(),
        ];
    }

    /**
     * Time-decay attribution
     */
    protected function attributeTimeDecay(Collection $touchpoints, CoreCustomerEvent $conversion): array
    {
        $value = $conversion->conversion_value ?? 0;
        $conversionTime = $conversion->created_at;

        // Calculate decay weights
        $weights = $touchpoints->map(function ($tp) use ($conversionTime) {
            $daysBeforeConversion = $conversionTime->diffInDays($tp['timestamp']);
            // Exponential decay: weight = 2^(-days/halfLife)
            return pow(2, -$daysBeforeConversion / $this->decayHalfLife);
        });

        $totalWeight = $weights->sum();

        $attributed = $touchpoints->map(function ($tp, $index) use ($weights, $totalWeight, $value, $touchpoints) {
            $weight = $totalWeight > 0 ? $weights[$index] / $totalWeight : 0;
            $position = match (true) {
                $index === 0 => 'first',
                $index === $touchpoints->count() - 1 => 'last',
                default => 'middle',
            };

            return [
                'touchpoint' => $tp,
                'weight' => $weight,
                'attributed_value' => $value * $weight,
                'attributed_conversion' => $weight,
                'position' => $position,
            ];
        });

        return [
            'model' => self::MODEL_TIME_DECAY,
            'conversion_value' => $value,
            'touchpoints' => $touchpoints->toArray(),
            'attributed' => $attributed->toArray(),
        ];
    }

    /**
     * Position-based attribution (U-shaped)
     */
    protected function attributePositionBased(Collection $touchpoints, CoreCustomerEvent $conversion): array
    {
        $value = $conversion->conversion_value ?? 0;
        $count = $touchpoints->count();

        if ($count === 1) {
            return $this->attributeFirstTouch($touchpoints, $conversion);
        }

        if ($count === 2) {
            $halfValue = $value / 2;
            return [
                'model' => self::MODEL_POSITION_BASED,
                'conversion_value' => $value,
                'touchpoints' => $touchpoints->toArray(),
                'attributed' => [
                    [
                        'touchpoint' => $touchpoints->first(),
                        'weight' => 0.5,
                        'attributed_value' => $halfValue,
                        'attributed_conversion' => 0.5,
                        'position' => 'first',
                    ],
                    [
                        'touchpoint' => $touchpoints->last(),
                        'weight' => 0.5,
                        'attributed_value' => $halfValue,
                        'attributed_conversion' => 0.5,
                        'position' => 'last',
                    ],
                ],
            ];
        }

        // U-shaped: 40% first, 20% middle (distributed), 40% last
        $firstWeight = $this->positionWeights[0];
        $middleWeight = $this->positionWeights[1];
        $lastWeight = $this->positionWeights[2];

        $middleCount = $count - 2;
        $middleWeightPerTouch = $middleCount > 0 ? $middleWeight / $middleCount : 0;

        $attributed = $touchpoints->map(function ($tp, $index) use ($firstWeight, $middleWeightPerTouch, $lastWeight, $value, $count) {
            $weight = match (true) {
                $index === 0 => $firstWeight,
                $index === $count - 1 => $lastWeight,
                default => $middleWeightPerTouch,
            };

            $position = match (true) {
                $index === 0 => 'first',
                $index === $count - 1 => 'last',
                default => 'middle',
            };

            return [
                'touchpoint' => $tp,
                'weight' => $weight,
                'attributed_value' => $value * $weight,
                'attributed_conversion' => $weight,
                'position' => $position,
            ];
        });

        return [
            'model' => self::MODEL_POSITION_BASED,
            'conversion_value' => $value,
            'touchpoints' => $touchpoints->toArray(),
            'attributed' => $attributed->toArray(),
        ];
    }

    /**
     * Data-driven attribution (simplified Shapley value)
     */
    protected function attributeDataDriven(Collection $touchpoints, CoreCustomerEvent $conversion): array
    {
        // Simplified data-driven model using historical conversion rates
        // In production, this would use more sophisticated ML models

        $value = $conversion->conversion_value ?? 0;

        // Calculate channel effectiveness scores
        $channelScores = $touchpoints->map(function ($tp) {
            $channel = $this->getChannelFromTouchpoint($tp);
            return $this->getChannelEffectivenessScore($channel);
        });

        $totalScore = $channelScores->sum();

        $attributed = $touchpoints->map(function ($tp, $index) use ($channelScores, $totalScore, $value, $touchpoints) {
            $weight = $totalScore > 0 ? $channelScores[$index] / $totalScore : 1 / $touchpoints->count();
            $position = match (true) {
                $index === 0 => 'first',
                $index === $touchpoints->count() - 1 => 'last',
                default => 'middle',
            };

            return [
                'touchpoint' => $tp,
                'weight' => $weight,
                'attributed_value' => $value * $weight,
                'attributed_conversion' => $weight,
                'position' => $position,
            ];
        });

        return [
            'model' => self::MODEL_DATA_DRIVEN,
            'conversion_value' => $value,
            'touchpoints' => $touchpoints->toArray(),
            'attributed' => $attributed->toArray(),
        ];
    }

    /**
     * Get channel effectiveness score based on historical data
     */
    protected function getChannelEffectivenessScore(string $channel): float
    {
        // Base scores - in production, calculate from historical data
        $baseScores = [
            'google_ads' => 1.2,
            'facebook_ads' => 1.1,
            'tiktok_ads' => 1.0,
            'linkedin_ads' => 1.0,
            'email' => 1.3,
            'organic_search' => 0.9,
            'organic_social' => 0.8,
            'referral' => 1.0,
            'direct' => 0.7,
        ];

        return $baseScores[$channel] ?? 1.0;
    }

    /**
     * Get channel from touchpoint data
     */
    protected function getChannelFromTouchpoint(array $touchpoint): string
    {
        if (!empty($touchpoint['gclid'])) return 'google_ads';
        if (!empty($touchpoint['fbclid'])) return 'facebook_ads';
        if (!empty($touchpoint['ttclid'])) return 'tiktok_ads';
        if (!empty($touchpoint['li_fat_id'])) return 'linkedin_ads';

        $source = $touchpoint['utm_source'] ?? '';
        $medium = $touchpoint['utm_medium'] ?? '';

        if (str_contains(strtolower($medium), 'email')) return 'email';
        if (str_contains(strtolower($medium), 'cpc') || str_contains(strtolower($medium), 'paid')) return 'paid_search';
        if (str_contains(strtolower($source), 'google') && $medium === 'organic') return 'organic_search';
        if (in_array(strtolower($source), ['facebook', 'twitter', 'instagram', 'linkedin', 'tiktok'])) return 'organic_social';
        if (!empty($touchpoint['referrer'])) return 'referral';

        return 'direct';
    }

    /**
     * Get channel from event
     */
    protected function getChannelFromEvent(CoreCustomerEvent $event): string
    {
        return $this->getChannelFromTouchpoint([
            'gclid' => $event->gclid,
            'fbclid' => $event->fbclid,
            'ttclid' => $event->ttclid,
            'li_fat_id' => $event->li_fat_id,
            'utm_source' => $event->utm_source,
            'utm_medium' => $event->utm_medium,
            'referrer' => $event->referrer,
        ]);
    }

    /**
     * Calculate average time to conversion
     */
    protected function calculateAvgTimeToConversion(Collection $touchpoints, Collection $conversions): ?string
    {
        if ($conversions->isEmpty() || $touchpoints->isEmpty()) {
            return null;
        }

        $firstTouch = $touchpoints->first();
        $firstConversion = $conversions->first();

        if (!$firstTouch || !$firstConversion) {
            return null;
        }

        $diffInHours = $firstTouch->created_at->diffInHours($firstConversion->created_at);

        if ($diffInHours < 24) {
            return $diffInHours . ' hours';
        }

        return round($diffInHours / 24, 1) . ' days';
    }

    /**
     * Sort array by value descending
     */
    protected function sortByValue(array $data): array
    {
        usort($data, fn($a, $b) => $b['value'] <=> $a['value']);
        return array_values($data);
    }

    /**
     * Get display name for model
     */
    public function getModelDisplayName(string $model): string
    {
        return match ($model) {
            self::MODEL_FIRST_TOUCH => 'First Touch',
            self::MODEL_LAST_TOUCH => 'Last Touch',
            self::MODEL_LINEAR => 'Linear',
            self::MODEL_TIME_DECAY => 'Time Decay',
            self::MODEL_POSITION_BASED => 'Position Based (U-Shaped)',
            self::MODEL_DATA_DRIVEN => 'Data Driven',
            default => ucfirst(str_replace('_', ' ', $model)),
        };
    }

    /**
     * Generate comparison insights
     */
    protected function generateComparison(array $modelResults): array
    {
        $channels = [];

        // Aggregate channel data across models
        foreach ($modelResults as $model => $result) {
            foreach ($result['by_channel'] as $channel) {
                $channelName = array_key_first($channel) !== 0 ? array_key_first($channel) : $channel['channel'] ?? 'unknown';
                if (!isset($channels[$channelName])) {
                    $channels[$channelName] = [];
                }
                $channels[$channelName][$model] = $channel['value'] ?? 0;
            }
        }

        // Calculate variance between models
        $insights = [];
        foreach ($channels as $channel => $values) {
            if (count($values) > 1) {
                $min = min($values);
                $max = max($values);
                $variance = $max > 0 ? (($max - $min) / $max) * 100 : 0;

                if ($variance > 20) {
                    $insights[] = [
                        'channel' => $channel,
                        'variance_pct' => round($variance, 1),
                        'message' => "Channel '{$channel}' shows " . round($variance, 1) . "% variance between attribution models",
                        'recommendation' => $variance > 50
                            ? 'Consider using multiple models for budgeting decisions'
                            : 'Models are relatively consistent',
                    ];
                }
            }
        }

        return [
            'channel_variance' => $channels,
            'insights' => $insights,
        ];
    }

    /**
     * Get available attribution models
     */
    public static function getAvailableModels(): array
    {
        return [
            self::MODEL_FIRST_TOUCH => 'First Touch',
            self::MODEL_LAST_TOUCH => 'Last Touch',
            self::MODEL_LINEAR => 'Linear',
            self::MODEL_TIME_DECAY => 'Time Decay',
            self::MODEL_POSITION_BASED => 'Position Based (U-Shaped)',
            self::MODEL_DATA_DRIVEN => 'Data Driven',
        ];
    }
}
