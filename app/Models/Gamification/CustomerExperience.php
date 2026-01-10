<?php

namespace App\Models\Gamification;

use App\Models\Customer;
use App\Models\MarketplaceClient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerExperience extends Model
{
    use HasFactory;

    protected $table = 'customer_experience';

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'customer_id',
        'marketplace_customer_id',
        'total_xp',
        'current_level',
        'xp_to_next_level',
        'xp_in_current_level',
        'current_level_group',
        'total_badges_earned',
        'events_attended',
        'reviews_submitted',
        'referrals_converted',
        'last_xp_earned_at',
        'last_level_up_at',
    ];

    protected $casts = [
        'total_xp' => 'integer',
        'current_level' => 'integer',
        'xp_to_next_level' => 'integer',
        'xp_in_current_level' => 'integer',
        'total_badges_earned' => 'integer',
        'events_attended' => 'integer',
        'reviews_submitted' => 'integer',
        'referrals_converted' => 'integer',
        'last_xp_earned_at' => 'datetime',
        'last_level_up_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function marketplaceCustomer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MarketplaceCustomer::class);
    }

    public function transactions(): HasMany
    {
        if ($this->tenant_id) {
            return $this->hasMany(ExperienceTransaction::class, 'customer_id', 'customer_id')
                ->where('tenant_id', $this->tenant_id);
        }
        return $this->hasMany(ExperienceTransaction::class, 'marketplace_customer_id', 'marketplace_customer_id')
            ->where('marketplace_client_id', $this->marketplace_client_id);
    }

    public function badges(): HasMany
    {
        if ($this->tenant_id) {
            return $this->hasMany(CustomerBadge::class, 'customer_id', 'customer_id')
                ->where('tenant_id', $this->tenant_id);
        }
        return $this->hasMany(CustomerBadge::class, 'marketplace_customer_id', 'marketplace_customer_id')
            ->where('marketplace_client_id', $this->marketplace_client_id);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByLevel($query, int $level)
    {
        return $query->where('current_level', $level);
    }

    public function scopeByLevelGroup($query, string $group)
    {
        return $query->where('current_level_group', $group);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get or create customer experience record for tenant
     */
    public static function getOrCreateForTenant(int $tenantId, int $customerId): self
    {
        $config = ExperienceConfig::getOrCreateForTenant($tenantId);

        return self::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
            ],
            [
                'total_xp' => 0,
                'current_level' => 1,
                'xp_to_next_level' => $config->getXpRequiredForLevel(2),
                'xp_in_current_level' => 0,
                'current_level_group' => $config->getLevelGroup(1)['name'] ?? null,
                'total_badges_earned' => 0,
                'events_attended' => 0,
                'reviews_submitted' => 0,
                'referrals_converted' => 0,
            ]
        );
    }

    /**
     * Get or create customer experience record for marketplace
     */
    public static function getOrCreateForMarketplace(int $marketplaceClientId, int $marketplaceCustomerId): self
    {
        $config = ExperienceConfig::getOrCreateForMarketplace($marketplaceClientId);

        return self::firstOrCreate(
            [
                'marketplace_client_id' => $marketplaceClientId,
                'marketplace_customer_id' => $marketplaceCustomerId,
            ],
            [
                'total_xp' => 0,
                'current_level' => 1,
                'xp_to_next_level' => $config->getXpRequiredForLevel(2),
                'xp_in_current_level' => 0,
                'current_level_group' => $config->getLevelGroup(1)['name'] ?? null,
                'total_badges_earned' => 0,
                'events_attended' => 0,
                'reviews_submitted' => 0,
                'referrals_converted' => 0,
            ]
        );
    }

    /**
     * Add XP and handle level ups
     */
    public function addXp(int $xp, string $actionType, array $options = []): ExperienceTransaction
    {
        $config = $this->getConfig();
        $oldLevel = $this->current_level;
        $oldLevelGroup = $this->current_level_group;

        // Update totals
        $this->total_xp += $xp;
        $this->xp_in_current_level += $xp;
        $this->last_xp_earned_at = now();

        // Check for level up
        $triggeredLevelUp = false;
        $newLevel = $oldLevel;

        while ($this->xp_in_current_level >= $this->xp_to_next_level && $this->current_level < $config->max_level) {
            $this->xp_in_current_level -= $this->xp_to_next_level;
            $this->current_level++;
            $this->xp_to_next_level = $config->getXpRequiredForLevel($this->current_level + 1);
            $triggeredLevelUp = true;
            $newLevel = $this->current_level;
        }

        // Update level group
        $newLevelGroup = null;
        if ($triggeredLevelUp) {
            $group = $config->getLevelGroup($this->current_level);
            $this->current_level_group = $group['name'] ?? null;
            $newLevelGroup = $this->current_level_group;
            $this->last_level_up_at = now();
        }

        // Update stat counters
        if ($actionType === ExperienceAction::ACTION_EVENT_CHECKIN) {
            $this->events_attended++;
        } elseif ($actionType === ExperienceAction::ACTION_REVIEW_SUBMITTED) {
            $this->reviews_submitted++;
        } elseif ($actionType === ExperienceAction::ACTION_REFERRAL_CONVERSION) {
            $this->referrals_converted++;
        }

        $this->save();

        // Create transaction
        $transaction = ExperienceTransaction::create([
            'tenant_id' => $this->tenant_id,
            'marketplace_client_id' => $this->marketplace_client_id,
            'customer_id' => $this->customer_id,
            'marketplace_customer_id' => $this->marketplace_customer_id,
            'xp' => $xp,
            'xp_balance_after' => $this->total_xp,
            'level_after' => $this->current_level,
            'triggered_level_up' => $triggeredLevelUp,
            'old_level' => $triggeredLevelUp ? $oldLevel : null,
            'new_level' => $triggeredLevelUp ? $newLevel : null,
            'old_level_group' => $triggeredLevelUp ? $oldLevelGroup : null,
            'new_level_group' => $triggeredLevelUp ? $newLevelGroup : null,
            'action_type' => $actionType,
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'description' => $options['description'] ?? null,
            'created_by' => $options['created_by'] ?? null,
        ]);

        return $transaction;
    }

    /**
     * Get experience config
     */
    public function getConfig(): ExperienceConfig
    {
        if ($this->tenant_id) {
            return ExperienceConfig::getOrCreateForTenant($this->tenant_id);
        }
        return ExperienceConfig::getOrCreateForMarketplace($this->marketplace_client_id);
    }

    /**
     * Get progress to next level (0-100)
     */
    public function getLevelProgressAttribute(): float
    {
        if ($this->xp_to_next_level <= 0) {
            return 100;
        }

        return min(100, round(($this->xp_in_current_level / $this->xp_to_next_level) * 100, 1));
    }

    /**
     * Get level group color
     */
    public function getLevelGroupColorAttribute(): string
    {
        $config = $this->getConfig();
        $group = $config->getLevelGroup($this->current_level);

        return $group['color'] ?? '#6366F1';
    }

    /**
     * Get level group icon
     */
    public function getLevelGroupIconAttribute(): ?string
    {
        $config = $this->getConfig();
        $group = $config->getLevelGroup($this->current_level);

        return $group['icon'] ?? null;
    }

    /**
     * Increment badge count
     */
    public function incrementBadgeCount(): void
    {
        $this->increment('total_badges_earned');
    }
}
