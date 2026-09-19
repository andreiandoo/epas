<?php

namespace App\Models\Gamification;

use App\Models\MarketplaceClient;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_client_id',
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
        'auto_rewards_enabled',
        'referral_min_order',
        'referral_max_per_year',
        'points_confirm_days',
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
        'auto_rewards_enabled' => 'boolean',
        'referral_min_order' => 'decimal:2',
        'referral_max_per_year' => 'integer',
        'points_confirm_days' => 'integer',
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
     * Points for an order amount given in CENTS (bani) — the tenant checkout's unit. With the usual 1 point = 0.01
     * this gives the same result as pointsForLei() (earn_percentage = % of the order value returned in points).
     * Marketplaces use pointsForLei(); the old marketplace sync read this field as "points per 100 lei", 100× less,
     * which is why the same setting used to mean two different things.
     */
    public function calculateEarnedPoints(float $amount): int
    {
        if ($amount < ($this->min_order_for_earning ?? 0)) {
            return 0;
        }

        return (int) floor($amount * $this->earn_percentage / 100);
    }

    /**
     * Points earned for an amount in lei. earn_percentage is the share of that amount returned as points value:
     * 0.5 with 1 point = 0.01 lei gives 50 points (0.50 lei) for 100 lei. Rounded down.
     */
    public function pointsForLei(float $amountLei): int
    {
        $pointValue = (float) ($this->point_value ?? 0.01);
        if ($pointValue <= 0 || $amountLei <= 0 || $amountLei < (float) ($this->min_order_for_earning ?? 0)) {
            return 0;
        }

        return (int) floor(round($amountLei * (float) $this->earn_percentage / 100 / $pointValue, 6));
    }

    /**
     * Points earned for 100 lei, for the "X puncte la 100 lei" line customers see.
     */
    public function pointsPer100Lei(): int
    {
        $pointValue = (float) ($this->point_value ?? 0.01);

        return $pointValue > 0 ? (int) floor(round(100 * (float) $this->earn_percentage / 100 / $pointValue, 6)) : 0;
    }

    /**
     * Human rule for earning, in Romanian: "5 puncte la fiecare 10 lei" / "1 punct la fiecare 2 lei" /
     * "50 de puncte la fiecare 100 de lei". Empty when nothing is earned.
     */
    public function earnRateLabel(): string
    {
        $per100 = $this->pointsPer100Lei();
        if ($per100 <= 0) {
            return '';
        }
        $ro = static function (int $n, string $one, string $many): string {
            if ($n === 1) {
                return '1 ' . $one;
            }
            $rem = $n % 100;
            return $n . (($n >= 20 && !($rem >= 1 && $rem <= 19)) ? ' de ' : ' ') . $many;
        };
        // the smallest round amount that earns a whole number of points
        foreach ([1, 2, 5, 10, 20, 50, 100] as $lei) {
            $pts = $per100 * $lei / 100;
            if ($pts >= 1 && abs($pts - round($pts)) < 1e-9) {
                return $ro((int) round($pts), 'punct', 'puncte') . ' la fiecare ' . ($lei === 1 ? 'leu' : $ro($lei, 'leu', 'lei'));
            }
        }

        return $ro($per100, 'punct', 'puncte') . ' la fiecare 100 de lei';
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
                'referral_bonus_points' => 100,
                'referred_bonus_points' => 50,
                'points_name' => 'puncte',
                'points_name_singular' => 'punct',
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create config for a marketplace client
     */
    public static function getOrCreateForMarketplace(int $marketplaceClientId): self
    {
        return self::firstOrCreate(
            ['marketplace_client_id' => $marketplaceClientId],
            [
                'point_value' => 0.01,
                'currency' => 'RON',
                'earn_percentage' => 5.00,
                'min_order_for_earning' => 0,
                'min_redeem_points' => 100,
                'max_redeem_percentage' => 50.00,
                'birthday_bonus_points' => 100,
                'signup_bonus_points' => 50,
                'referral_bonus_points' => 100,
                'referred_bonus_points' => 50,
                'points_name' => 'puncte',
                'points_name_singular' => 'punct',
                'is_active' => true,
            ]
        );
    }
    /**
     * Get the marketplace client that owns this record
     */
    public function marketplaceClient()
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

}
