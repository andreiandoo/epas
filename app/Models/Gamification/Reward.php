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
use App\Support\Translatable;

class Reward extends Model
{
    use HasFactory;
    use Translatable;

    public array $translatable = ['name', 'description'];

    // Reward types
    public const TYPE_FIXED_DISCOUNT = 'fixed_discount';
    public const TYPE_PERCENTAGE_DISCOUNT = 'percentage_discount';
    public const TYPE_FREE_ITEM = 'free_item';
    public const TYPE_VOUCHER_CODE = 'voucher_code';

    public const TYPES = [
        self::TYPE_FIXED_DISCOUNT => 'Fixed Discount',
        self::TYPE_PERCENTAGE_DISCOUNT => 'Percentage Discount',
        self::TYPE_FREE_ITEM => 'Free Item',
        self::TYPE_VOUCHER_CODE => 'Voucher Code',
    ];

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'name',
        'description',
        'slug',
        'image_url',
        'type',
        'points_cost',
        'value',
        'currency',
        'voucher_prefix',
        'min_order_value',
        'max_redemptions_total',
        'max_redemptions_per_customer',
        'valid_from',
        'valid_until',
        'required_tiers',
        'min_level_required',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'points_cost' => 'integer',
        'value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'max_redemptions_total' => 'integer',
        'max_redemptions_per_customer' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'required_tiers' => 'array',
        'min_level_required' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
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

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
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

    public function scopeAvailable($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            });
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if customer can redeem this reward
     */
    public function canBeRedeemedBy(Customer $customer, ?CustomerPoints $customerPoints = null, ?CustomerExperience $customerExperience = null): array
    {
        $errors = [];

        // Check if active
        if (!$this->is_active) {
            $errors[] = 'Reward is not active';
        }

        // Check validity period
        if ($this->valid_from && $this->valid_from->isFuture()) {
            $errors[] = 'Reward is not yet available';
        }
        if ($this->valid_until && $this->valid_until->isPast()) {
            $errors[] = 'Reward has expired';
        }

        // Check total redemption limit
        if ($this->max_redemptions_total) {
            $totalRedemptions = $this->redemptions()->count();
            if ($totalRedemptions >= $this->max_redemptions_total) {
                $errors[] = 'Reward is sold out';
            }
        }

        // Check per-customer limit
        if ($this->max_redemptions_per_customer) {
            $customerRedemptions = $this->redemptions()
                ->where('customer_id', $customer->id)
                ->count();
            if ($customerRedemptions >= $this->max_redemptions_per_customer) {
                $errors[] = 'You have reached the maximum redemptions for this reward';
            }
        }

        // Check points balance
        if ($customerPoints) {
            if ($customerPoints->current_balance < $this->points_cost) {
                $errors[] = 'Insufficient points';
            }

            // Check tier requirement
            if (!empty($this->required_tiers) && $customerPoints->current_tier) {
                if (!in_array($customerPoints->current_tier, $this->required_tiers)) {
                    $errors[] = 'Your tier does not qualify for this reward';
                }
            }
        }

        // Check level requirement
        if ($this->min_level_required && $customerExperience) {
            if ($customerExperience->current_level < $this->min_level_required) {
                $errors[] = "You need to be level {$this->min_level_required} or higher";
            }
        }

        return [
            'can_redeem' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get formatted value display
     */
    public function getFormattedValueAttribute(): string
    {
        return match ($this->type) {
            'fixed_discount' => number_format($this->value, 2) . ' ' . $this->currency,
            'percentage_discount' => $this->value . '%',
            'free_item' => $this->getTranslation('name', app()->getLocale()),
            'voucher_code' => number_format($this->value, 2) . ' ' . $this->currency,
            default => (string) $this->value,
        };
    }

    /**
     * Generate unique voucher code
     */
    public function generateVoucherCode(): string
    {
        $prefix = $this->voucher_prefix ?: 'RWD';
        do {
            $code = $prefix . '-' . strtoupper(Str::random(8));
        } while (RewardRedemption::where('voucher_code', $code)->exists());

        return $code;
    }

    /**
     * Get remaining redemptions
     */
    public function getRemainingRedemptionsAttribute(): ?int
    {
        if (!$this->max_redemptions_total) {
            return null;
        }

        return max(0, $this->max_redemptions_total - $this->redemptions()->count());
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reward) {
            if (empty($reward->slug)) {
                $reward->slug = Str::slug($reward->getTranslation('name', 'en') ?? $reward->name);
            }
        });
    }
}
