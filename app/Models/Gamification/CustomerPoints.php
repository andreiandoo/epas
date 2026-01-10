<?php

namespace App\Models\Gamification;

use App\Models\MarketplaceClient;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomerPoints extends Model
{
    use HasFactory;

    protected $table = 'customer_points';

    protected $fillable = [
        'marketplace_client_id',
        'tenant_id',
        'customer_id',
        'marketplace_customer_id',
        'total_earned',
        'total_spent',
        'total_expired',
        'current_balance',
        'pending_points',
        'current_tier',
        'tier_points',
        'tier_updated_at',
        'last_earned_at',
        'last_spent_at',
        'points_expire_at',
        'referral_code',
        'referral_count',
        'referral_points_earned',
    ];

    protected $casts = [
        'tier_updated_at' => 'datetime',
        'last_earned_at' => 'datetime',
        'last_spent_at' => 'datetime',
        'points_expire_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class, 'customer_id', 'customer_id')
            ->where('tenant_id', $this->tenant_id);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_customer_id', 'customer_id')
            ->where('tenant_id', $this->tenant_id);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeWithBalance($query)
    {
        return $query->where('current_balance', '>', 0);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get or create customer points record
     */
    public static function getOrCreate(int $tenantId, int $customerId): self
    {
        return self::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
            ],
            [
                'total_earned' => 0,
                'total_spent' => 0,
                'total_expired' => 0,
                'current_balance' => 0,
                'pending_points' => 0,
                'tier_points' => 0,
                'referral_code' => self::generateReferralCode(),
                'referral_count' => 0,
                'referral_points_earned' => 0,
            ]
        );
    }

    /**
     * Generate unique referral code
     */
    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Add earned points
     */
    public function addPoints(int $points, string $actionType, array $options = []): PointsTransaction
    {
        $config = GamificationConfig::where('tenant_id', $this->tenant_id)->first();

        // Calculate expiration
        $expiresAt = null;
        if ($config && $config->points_expire_days) {
            $expiresAt = now()->addDays($config->points_expire_days);
        }

        // Create transaction
        $transaction = PointsTransaction::create([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'type' => 'earned',
            'points' => $points,
            'balance_after' => $this->current_balance + $points,
            'action_type' => $actionType,
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'description' => $options['description'] ?? ['en' => "Earned {$points} points", 'ro' => "Ai castigat {$points} puncte"],
            'metadata' => $options['metadata'] ?? null,
            'expires_at' => $expiresAt,
            'created_by' => $options['created_by'] ?? null,
        ]);

        // Update balances
        $this->total_earned += $points;
        $this->current_balance += $points;
        $this->tier_points += $points;
        $this->last_earned_at = now();

        // Update expiration date if needed
        if ($expiresAt && (!$this->points_expire_at || $expiresAt->lt($this->points_expire_at))) {
            $this->points_expire_at = $expiresAt;
        }

        // Update tier
        $this->updateTier();

        $this->save();

        return $transaction;
    }

    /**
     * Spend points
     */
    public function spendPoints(int $points, array $options = []): ?PointsTransaction
    {
        if ($points > $this->current_balance) {
            return null; // Insufficient balance
        }

        // Create transaction
        $transaction = PointsTransaction::create([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'type' => 'spent',
            'points' => -$points,
            'balance_after' => $this->current_balance - $points,
            'action_type' => $options['action_type'] ?? 'redemption',
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'description' => $options['description'] ?? ['en' => "Spent {$points} points", 'ro' => "Ai folosit {$points} puncte"],
            'metadata' => $options['metadata'] ?? null,
            'created_by' => $options['created_by'] ?? null,
        ]);

        // Update balances
        $this->total_spent += $points;
        $this->current_balance -= $points;
        $this->last_spent_at = now();

        $this->save();

        return $transaction;
    }

    /**
     * Refund spent points (e.g., order cancelled)
     */
    public function refundPoints(PointsTransaction $originalTransaction, array $options = []): ?PointsTransaction
    {
        if ($originalTransaction->type !== 'spent') {
            return null;
        }

        $points = abs($originalTransaction->points);

        $transaction = PointsTransaction::create([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'type' => 'refunded',
            'points' => $points,
            'balance_after' => $this->current_balance + $points,
            'action_type' => 'refund',
            'reference_type' => $originalTransaction->reference_type,
            'reference_id' => $originalTransaction->reference_id,
            'description' => $options['description'] ?? ['en' => "Refunded {$points} points", 'ro' => "S-au returnat {$points} puncte"],
            'reversed_transaction_id' => $originalTransaction->id,
            'created_by' => $options['created_by'] ?? null,
        ]);

        // Update balances
        $this->total_spent -= $points;
        $this->current_balance += $points;

        $this->save();

        return $transaction;
    }

    /**
     * Expire points
     */
    public function expirePoints(int $points, array $options = []): PointsTransaction
    {
        $points = min($points, $this->current_balance);

        $transaction = PointsTransaction::create([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'type' => 'expired',
            'points' => -$points,
            'balance_after' => $this->current_balance - $points,
            'action_type' => 'expiration',
            'description' => $options['description'] ?? ['en' => "{$points} points expired", 'ro' => "{$points} puncte au expirat"],
            'admin_note' => $options['admin_note'] ?? null,
            'created_by' => $options['created_by'] ?? null,
        ]);

        // Update balances
        $this->total_expired += $points;
        $this->current_balance -= $points;

        $this->save();

        return $transaction;
    }

    /**
     * Manual adjustment (admin)
     */
    public function adjustPoints(int $points, string $reason, ?int $createdBy = null): PointsTransaction
    {
        $transaction = PointsTransaction::create([
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'type' => 'adjusted',
            'points' => $points,
            'balance_after' => $this->current_balance + $points,
            'action_type' => 'manual_adjustment',
            'description' => ['en' => 'Manual adjustment', 'ro' => 'Ajustare manuala'],
            'admin_note' => $reason,
            'created_by' => $createdBy,
        ]);

        if ($points > 0) {
            $this->total_earned += $points;
        } else {
            $this->total_spent += abs($points);
        }

        $this->current_balance += $points;

        // Ensure balance doesn't go negative
        if ($this->current_balance < 0) {
            $this->current_balance = 0;
        }

        $this->save();

        return $transaction;
    }

    /**
     * Update customer tier based on points
     */
    public function updateTier(): void
    {
        $config = GamificationConfig::where('tenant_id', $this->tenant_id)->first();

        if (!$config || empty($config->tiers)) {
            return;
        }

        $newTier = $config->getTierForPoints($this->tier_points);

        if ($newTier && $newTier['name'] !== $this->current_tier) {
            $this->current_tier = $newTier['name'];
            $this->tier_updated_at = now();
        }
    }

    /**
     * Process referral bonus
     */
    public function processReferralBonus(Customer $referredCustomer): void
    {
        $config = GamificationConfig::where('tenant_id', $this->tenant_id)->first();

        if (!$config || !$config->is_active) {
            return;
        }

        // Add points to referrer
        $this->addPoints($config->referral_bonus_points, GamificationAction::ACTION_REFERRAL, [
            'reference_type' => Customer::class,
            'reference_id' => $referredCustomer->id,
            'description' => ['en' => "Referral bonus for {$referredCustomer->email}", 'ro' => "Bonus referire pentru {$referredCustomer->email}"],
        ]);

        $this->referral_count++;
        $this->referral_points_earned += $config->referral_bonus_points;
        $this->save();

        // Add points to referred customer
        $referredPoints = self::getOrCreate($this->tenant_id, $referredCustomer->id);
        $referredPoints->addPoints($config->referred_bonus_points, GamificationAction::ACTION_REFERRAL, [
            'reference_type' => Customer::class,
            'reference_id' => $this->customer_id,
            'description' => ['en' => 'Welcome bonus from referral', 'ro' => 'Bonus de bun venit din referire'],
        ]);
    }

    /**
     * Get referral link
     */
    public function getReferralLink(): string
    {
        $tenant = $this->tenant;
        $domain = $tenant->domains()->where('is_primary', true)->first();

        if ($domain) {
            return "https://{$domain->domain}/ref/{$this->referral_code}";
        }

        return "/ref/{$this->referral_code}";
    }
    /**
     * Get the marketplace client that owns this record
     */
    public function marketplaceClient()
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

}
