<?php

namespace App\Models\Gamification;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'point_value',
        'currency',
        'earn_percentage',
        'earn_on_subtotal',
        'min_order_for_earning',
        'min_redeem_points',
        'max_redeem_percentage',
        'max_redeem_points_per_order',
        'birthday_bonus_points',
        'signup_bonus_points',
        'referral_bonus_points',
        'referred_bonus_points',
        'points_expire_days',
        'expire_on_inactivity',
        'inactivity_days',
        'points_name',
        'points_name_singular',
        'icon',
        'tiers',
        'is_active',
    ];

    protected $casts = [
        'point_value' => 'decimal:2',
        'earn_percentage' => 'decimal:2',
        'earn_on_subtotal' => 'boolean',
        'min_order_for_earning' => 'decimal:2',
        'max_redeem_percentage' => 'decimal:2',
        'expire_on_inactivity' => 'boolean',
        'tiers' => 'array',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(GamificationAction::class, 'tenant_id', 'tenant_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Calculate points earned from an order amount
     */
    public function calculateEarnedPoints(float $amount): int
    {
        if ($amount < ($this->min_order_for_earning ?? 0)) {
            return 0;
        }

        return (int) floor($amount * $this->earn_percentage / 100);
    }

    /**
     * Calculate the monetary value of points
     */
    public function getPointsValue(int $points): float
    {
        return round($points * ($this->point_value ?? 0.01), 2);
    }

    /**
     * Calculate how many points are needed for a given amount
     */
    public function getPointsForAmount(float $amount): int
    {
        if (!$this->point_value || $this->point_value <= 0) {
            return 0;
        }

        return (int) ceil($amount / $this->point_value);
    }

    /**
     * Get maximum redeemable points for an order
     */
    public function getMaxRedeemablePoints(float $orderTotal, int $availablePoints): int
    {
        // Calculate max based on percentage
        $maxByPercentage = $orderTotal * $this->max_redeem_percentage / 100;

        // Convert to points
        $maxPointsByPercentage = $this->getPointsForAmount($maxByPercentage);

        // Apply per-order cap if set
        $maxPoints = $maxPointsByPercentage;
        if ($this->max_redeem_points_per_order) {
            $maxPoints = min($maxPoints, $this->max_redeem_points_per_order);
        }

        // Cannot redeem more than available
        $maxPoints = min($maxPoints, $availablePoints);

        // Must meet minimum redemption threshold
        if ($maxPoints < $this->min_redeem_points) {
            return 0;
        }

        return $maxPoints;
    }

    /**
     * Get tier for given points
     */
    public function getTierForPoints(int $points): ?array
    {
        if (empty($this->tiers)) {
            return null;
        }

        $currentTier = null;
        foreach ($this->tiers as $tier) {
            if ($points >= ($tier['min_points'] ?? 0)) {
                $currentTier = $tier;
            }
        }

        return $currentTier;
    }

    /**
     * Get next tier for given points
     */
    public function getNextTier(int $points): ?array
    {
        if (empty($this->tiers)) {
            return null;
        }

        foreach ($this->tiers as $tier) {
            if ($points < ($tier['min_points'] ?? 0)) {
                return $tier;
            }
        }

        return null; // Already at highest tier
    }

    /**
     * Format points name based on count
     */
    public function formatPointsName(int $points): string
    {
        if ($points === 1) {
            return $this->points_name_singular;
        }

        return $this->points_name;
    }

    /**
     * Get or create config for a tenant
     */
    public static function getOrCreateForTenant(int $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'point_value' => 0.01,
                'currency' => 'RON',
                'earn_percentage' => 5.00,
                'min_order_for_earning' => 0,
                'min_redeem_points' => 100,
                'max_redeem_percentage' => 50.00,
                'birthday_bonus_points' => 100,
                'signup_bonus_points' => 50,
                'referral_bonus_points' => 200,
                'referred_bonus_points' => 100,
                'points_name' => 'puncte',
                'points_name_singular' => 'punct',
                'is_active' => true,
            ]
        );
    }
}
