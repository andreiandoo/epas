<?php

namespace App\Models\Gamification;

use App\Models\Customer;
use App\Models\MarketplaceClient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomerBadge extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'badge_id',
        'customer_id',
        'marketplace_customer_id',
        'xp_awarded',
        'experience_transaction_id',
        'points_awarded',
        'points_transaction_id',
        'earned_context',
        'reference_type',
        'reference_id',
        'earned_at',
    ];

    protected $casts = [
        'xp_awarded' => 'integer',
        'points_awarded' => 'integer',
        'earned_context' => 'array',
        'earned_at' => 'datetime',
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

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function experienceTransaction(): BelongsTo
    {
        return $this->belongsTo(ExperienceTransaction::class);
    }

    public function pointsTransaction(): BelongsTo
    {
        return $this->belongsTo(PointsTransaction::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
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

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('earned_at', '>=', now()->subDays($days));
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get badge name
     */
    public function getBadgeNameAttribute(): string
    {
        return $this->badge?->getTranslation('name', app()->getLocale()) ?? 'Unknown Badge';
    }

    /**
     * Get badge icon
     */
    public function getBadgeIconAttribute(): ?string
    {
        return $this->badge?->icon_url;
    }

    /**
     * Get badge color
     */
    public function getBadgeColorAttribute(): string
    {
        return $this->badge?->color ?? '#6366F1';
    }

    /**
     * Get badge rarity
     */
    public function getBadgeRarityAttribute(): int
    {
        return $this->badge?->rarity_level ?? 1;
    }

    /**
     * Create customer badge record
     */
    public static function awardBadge(
        Badge $badge,
        Customer $customer,
        array $options = []
    ): self {
        return self::create([
            'tenant_id' => $badge->tenant_id,
            'marketplace_client_id' => $badge->marketplace_client_id,
            'badge_id' => $badge->id,
            'customer_id' => $customer->id,
            'xp_awarded' => $options['xp_awarded'] ?? $badge->xp_reward,
            'experience_transaction_id' => $options['experience_transaction_id'] ?? null,
            'points_awarded' => $options['points_awarded'] ?? $badge->bonus_points,
            'points_transaction_id' => $options['points_transaction_id'] ?? null,
            'earned_context' => $options['context'] ?? null,
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'earned_at' => $options['earned_at'] ?? now(),
        ]);
    }
}
