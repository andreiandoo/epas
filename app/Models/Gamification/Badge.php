<?php

namespace App\Models\Gamification;

use App\Models\Customer;
use App\Models\MarketplaceClient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

class Badge extends Model
{
    use HasFactory;
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    public const CATEGORY_MILESTONE = 'milestone';
    public const CATEGORY_ACTIVITY = 'activity';
    public const CATEGORY_SPECIAL = 'special';
    public const CATEGORY_EVENT = 'event';
    public const CATEGORY_LOYALTY = 'loyalty';
    public const CATEGORY_SOCIAL = 'social';

    public const CATEGORIES = [
        self::CATEGORY_MILESTONE => 'Milestone',
        self::CATEGORY_ACTIVITY => 'Activity',
        self::CATEGORY_SPECIAL => 'Special',
        self::CATEGORY_EVENT => 'Event',
        self::CATEGORY_LOYALTY => 'Loyalty',
        self::CATEGORY_SOCIAL => 'Social',
    ];

    public const RARITY_COMMON = 1;
    public const RARITY_UNCOMMON = 2;
    public const RARITY_RARE = 3;
    public const RARITY_EPIC = 4;
    public const RARITY_LEGENDARY = 5;

    public const RARITIES = [
        self::RARITY_COMMON => 'Common',
        self::RARITY_UNCOMMON => 'Uncommon',
        self::RARITY_RARE => 'Rare',
        self::RARITY_EPIC => 'Epic',
        self::RARITY_LEGENDARY => 'Legendary',
    ];

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'name',
        'description',
        'slug',
        'icon_url',
        'color',
        'category',
        'xp_reward',
        'bonus_points',
        'conditions',
        'is_secret',
        'is_active',
        'is_featured',
        'rarity_level',
        'sort_order',
    ];

    protected $casts = [
        'xp_reward' => 'integer',
        'bonus_points' => 'integer',
        'conditions' => 'array',
        'is_secret' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'rarity_level' => 'integer',
        'sort_order' => 'integer',
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

    public function customerBadges(): HasMany
    {
        return $this->hasMany(CustomerBadge::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_secret', false);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if customer has earned this badge
     */
    public function isEarnedBy(Customer $customer): bool
    {
        return $this->customerBadges()
            ->where('customer_id', $customer->id)
            ->exists();
    }

    /**
     * Get rarity name
     */
    public function getRarityNameAttribute(): string
    {
        return self::RARITIES[$this->rarity_level] ?? 'Unknown';
    }

    /**
     * Get rarity color
     */
    public function getRarityColorAttribute(): string
    {
        return match ($this->rarity_level) {
            self::RARITY_COMMON => '#9CA3AF',     // Gray
            self::RARITY_UNCOMMON => '#10B981',   // Green
            self::RARITY_RARE => '#3B82F6',       // Blue
            self::RARITY_EPIC => '#8B5CF6',       // Purple
            self::RARITY_LEGENDARY => '#F59E0B',  // Gold
            default => '#9CA3AF',
        };
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Get total customers who earned this badge
     */
    public function getEarnedCountAttribute(): int
    {
        return $this->customerBadges()->count();
    }

    /**
     * Evaluate conditions for a customer
     * Returns true if customer meets all conditions
     */
    public function evaluateConditions(Customer $customer, array $context = []): bool
    {
        if (empty($this->conditions)) {
            return false; // No conditions means badge cannot be auto-awarded
        }

        return $this->evaluateRule($this->conditions, $customer, $context);
    }

    /**
     * Recursively evaluate a condition rule
     */
    protected function evaluateRule(array $rule, Customer $customer, array $context): bool
    {
        // Compound rule (AND/OR)
        if (isset($rule['type']) && $rule['type'] === 'compound') {
            $operator = $rule['operator'] ?? 'AND';
            $rules = $rule['rules'] ?? [];

            if ($operator === 'AND') {
                foreach ($rules as $subRule) {
                    if (!$this->evaluateRule($subRule, $customer, $context)) {
                        return false;
                    }
                }
                return true;
            } else { // OR
                foreach ($rules as $subRule) {
                    if ($this->evaluateRule($subRule, $customer, $context)) {
                        return true;
                    }
                }
                return false;
            }
        }

        // Simple rule
        $metric = $rule['metric'] ?? null;
        $operator = $rule['operator'] ?? '>=';
        $value = $rule['value'] ?? 0;
        $params = $rule['params'] ?? [];

        $actualValue = $this->getMetricValue($metric, $customer, $context, $params);

        return match ($operator) {
            '=' => $actualValue == $value,
            '!=' => $actualValue != $value,
            '>' => $actualValue > $value,
            '>=' => $actualValue >= $value,
            '<' => $actualValue < $value,
            '<=' => $actualValue <= $value,
            'in' => in_array($actualValue, (array) $value),
            'not_in' => !in_array($actualValue, (array) $value),
            default => false,
        };
    }

    /**
     * Get metric value for condition evaluation
     */
    protected function getMetricValue(string $metric, Customer $customer, array $context, array $params): mixed
    {
        // Get customer experience record for stats
        $experience = CustomerExperience::where('customer_id', $customer->id)
            ->where(function ($q) {
                if ($this->tenant_id) {
                    $q->where('tenant_id', $this->tenant_id);
                }
                if ($this->marketplace_client_id) {
                    $q->where('marketplace_client_id', $this->marketplace_client_id);
                }
            })
            ->first();

        return match ($metric) {
            'events_attended' => $experience?->events_attended ?? 0,
            'reviews_submitted' => $experience?->reviews_submitted ?? 0,
            'referrals_converted' => $experience?->referrals_converted ?? 0,
            'total_badges_earned' => $experience?->total_badges_earned ?? 0,
            'current_level' => $experience?->current_level ?? 1,
            'total_xp' => $experience?->total_xp ?? 0,
            'genre_attendance' => $context['genre_attendance'][$params['genre_id'] ?? 0] ?? 0,
            'event_type_attendance' => $context['event_type_attendance'][$params['event_type_id'] ?? 0] ?? 0,
            'total_spent' => $context['total_spent'] ?? 0,
            'orders_count' => $context['orders_count'] ?? 0,
            'first_purchase' => $context['first_purchase'] ?? false,
            default => $context[$metric] ?? 0,
        };
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($badge) {
            if (empty($badge->slug)) {
                $badge->slug = Str::slug($badge->getTranslation('name', 'en') ?? $badge->name);
            }
        });
    }
}
