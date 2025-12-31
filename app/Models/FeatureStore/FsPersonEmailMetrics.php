<?php

namespace App\Models\FeatureStore;

use App\Models\Platform\CoreCustomer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FsPersonEmailMetrics extends Model
{
    protected $table = 'fs_person_email_metrics';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'sent_last_7_days',
        'sent_last_30_days',
        'sent_last_90_days',
        'opened_last_30_days',
        'clicked_last_30_days',
        'engagement_trend',
        'open_rate_30d',
        'open_rate_90d',
        'click_rate_30d',
        'fatigue_score',
        'optimal_frequency_per_week',
        'preferred_send_hours',
        'preferred_send_days',
        'last_engagement_at',
    ];

    protected $casts = [
        'sent_last_7_days' => 'integer',
        'sent_last_30_days' => 'integer',
        'sent_last_90_days' => 'integer',
        'opened_last_30_days' => 'integer',
        'clicked_last_30_days' => 'integer',
        'open_rate_30d' => 'float',
        'open_rate_90d' => 'float',
        'click_rate_30d' => 'float',
        'fatigue_score' => 'float',
        'optimal_frequency_per_week' => 'float',
        'preferred_send_hours' => 'array',
        'preferred_send_days' => 'array',
        'last_engagement_at' => 'datetime',
    ];

    /**
     * Engagement trends.
     */
    public const TRENDS = [
        'increasing' => 'Increasing',
        'stable' => 'Stable',
        'declining' => 'Declining',
        'inactive' => 'Inactive',
    ];

    /**
     * Fatigue thresholds.
     */
    public const FATIGUE_LEVELS = [
        'low' => [0, 30],
        'moderate' => [31, 60],
        'high' => [61, 80],
        'critical' => [81, 100],
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'person_id');
    }

    // Scopes

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForPerson($query, int $personId)
    {
        return $query->where('person_id', $personId);
    }

    public function scopeLowFatigue($query)
    {
        return $query->where('fatigue_score', '<=', 30);
    }

    public function scopeModerateFatigue($query)
    {
        return $query->whereBetween('fatigue_score', [31, 60]);
    }

    public function scopeHighFatigue($query)
    {
        return $query->where('fatigue_score', '>', 60);
    }

    public function scopeEngaged($query, int $days = 30)
    {
        return $query->where('last_engagement_at', '>=', now()->subDays($days));
    }

    public function scopeWithTrend($query, string $trend)
    {
        return $query->where('engagement_trend', $trend);
    }

    // Helpers

    /**
     * Get fatigue level label.
     */
    public function getFatigueLevel(): string
    {
        $score = $this->fatigue_score ?? 0;

        foreach (self::FATIGUE_LEVELS as $level => $range) {
            if ($score >= $range[0] && $score <= $range[1]) {
                return $level;
            }
        }

        return 'low';
    }

    /**
     * Check if email should be sent based on fatigue.
     */
    public function shouldSendEmail(bool $isUrgent = false): bool
    {
        // Always send urgent emails
        if ($isUrgent) {
            return true;
        }

        // Check fatigue level
        if ($this->fatigue_score > 80) {
            return false; // Don't send to critically fatigued users
        }

        // Check recent send frequency
        if ($this->sent_last_7_days >= 5) {
            return false; // Already sent too many this week
        }

        return true;
    }

    /**
     * Get recommended send day/time.
     */
    public function getRecommendedSendWindow(): ?array
    {
        if (empty($this->preferred_send_hours) && empty($this->preferred_send_days)) {
            return null;
        }

        return [
            'hours' => $this->preferred_send_hours ?? [],
            'days' => $this->preferred_send_days ?? [],
            'best_hour' => !empty($this->preferred_send_hours) ? $this->preferred_send_hours[0] : null,
            'best_day' => !empty($this->preferred_send_days) ? $this->preferred_send_days[0] : null,
        ];
    }

    /**
     * Calculate fatigue score.
     *
     * Components:
     * - Declining open rate (30%)
     * - High send frequency (25%)
     * - Days since engagement (25%)
     * - Low click-through (20%)
     */
    public static function calculateFatigueScore(
        float $openRate30d,
        float $openRate90d,
        int $sentLast7Days,
        int $daysSinceEngagement,
        float $clickRate30d
    ): float {
        $score = 0;

        // Open rate decline component (0-30 points)
        if ($openRate90d > 0) {
            $decline = ($openRate90d - $openRate30d) / $openRate90d;
            $score += min(30, max(0, $decline * 100));
        }

        // Send frequency component (0-25 points)
        // More than 3 per week is high frequency
        $frequencyScore = min(25, ($sentLast7Days / 3) * 25);
        $score += $frequencyScore;

        // Days since engagement (0-25 points)
        // 30+ days without engagement is concerning
        $engagementScore = min(25, ($daysSinceEngagement / 30) * 25);
        $score += $engagementScore;

        // Low click rate (0-20 points)
        // Below 2% click rate is concerning
        if ($clickRate30d < 0.02) {
            $score += 20 * (1 - ($clickRate30d / 0.02));
        }

        return min(100, max(0, $score));
    }

    /**
     * Determine engagement trend.
     */
    public static function determineTrend(float $rate30d, float $rate90d, int $daysSinceEngagement): string
    {
        if ($daysSinceEngagement > 60) {
            return 'inactive';
        }

        if ($rate90d === 0) {
            return 'stable';
        }

        $change = ($rate30d - $rate90d) / $rate90d;

        if ($change > 0.1) {
            return 'increasing';
        } elseif ($change < -0.1) {
            return 'declining';
        }

        return 'stable';
    }

    // Static helpers

    /**
     * Get email metrics profile for a person.
     */
    public static function getProfile(int $tenantId, int $personId): ?array
    {
        $metrics = static::forTenant($tenantId)->forPerson($personId)->first();

        if (!$metrics) {
            return null;
        }

        return [
            'sent_last_7_days' => $metrics->sent_last_7_days,
            'sent_last_30_days' => $metrics->sent_last_30_days,
            'open_rate_30d' => round($metrics->open_rate_30d * 100, 1) . '%',
            'click_rate_30d' => round($metrics->click_rate_30d * 100, 2) . '%',
            'engagement_trend' => $metrics->engagement_trend,
            'fatigue_score' => $metrics->fatigue_score,
            'fatigue_level' => $metrics->getFatigueLevel(),
            'should_send' => $metrics->shouldSendEmail(),
            'optimal_frequency' => $metrics->optimal_frequency_per_week,
            'recommended_window' => $metrics->getRecommendedSendWindow(),
            'last_engagement' => $metrics->last_engagement_at?->diffForHumans(),
        ];
    }

    /**
     * Find users with low fatigue for email campaigns.
     */
    public static function findEmailableUsers(int $tenantId, int $limit = 1000): array
    {
        return static::forTenant($tenantId)
            ->lowFatigue()
            ->orderBy('fatigue_score')
            ->limit($limit)
            ->pluck('person_id')
            ->toArray();
    }
}
