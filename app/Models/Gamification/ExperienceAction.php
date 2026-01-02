<?php

namespace App\Models\Gamification;

use App\Models\MarketplaceClient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Translatable;

class ExperienceAction extends Model
{
    use HasFactory;
    use Translatable;

    public array $translatable = ['name', 'description'];

    // Action types
    public const ACTION_TICKET_PURCHASE = 'ticket_purchase';
    public const ACTION_EVENT_CHECKIN = 'event_checkin';
    public const ACTION_REVIEW_SUBMITTED = 'review_submitted';
    public const ACTION_REFERRAL_CONVERSION = 'referral_conversion';
    public const ACTION_PROFILE_COMPLETE = 'profile_complete';
    public const ACTION_FIRST_PURCHASE = 'first_purchase';
    public const ACTION_BADGE_EARNED = 'badge_earned';
    public const ACTION_SOCIAL_SHARE = 'social_share';

    public const ACTION_TYPES = [
        self::ACTION_TICKET_PURCHASE => 'Ticket Purchase',
        self::ACTION_EVENT_CHECKIN => 'Event Check-in',
        self::ACTION_REVIEW_SUBMITTED => 'Review Submitted',
        self::ACTION_REFERRAL_CONVERSION => 'Referral Conversion',
        self::ACTION_PROFILE_COMPLETE => 'Profile Complete',
        self::ACTION_FIRST_PURCHASE => 'First Purchase',
        self::ACTION_BADGE_EARNED => 'Badge Earned',
        self::ACTION_SOCIAL_SHARE => 'Social Share',
    ];

    // XP types
    public const XP_TYPE_FIXED = 'fixed';
    public const XP_TYPE_PER_CURRENCY = 'per_currency';
    public const XP_TYPE_MULTIPLIER = 'multiplier';

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'action_type',
        'name',
        'description',
        'xp_type',
        'xp_amount',
        'xp_per_currency_unit',
        'max_xp_per_action',
        'max_times_per_day',
        'cooldown_hours',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'xp_amount' => 'integer',
        'xp_per_currency_unit' => 'decimal:2',
        'max_xp_per_action' => 'integer',
        'max_times_per_day' => 'integer',
        'cooldown_hours' => 'integer',
        'is_active' => 'boolean',
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

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMarketplace($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeForAction($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Calculate XP for this action
     */
    public function calculateXp(float $currencyAmount = 0): int
    {
        $xp = match ($this->xp_type) {
            self::XP_TYPE_FIXED => $this->xp_amount,
            self::XP_TYPE_PER_CURRENCY => (int) floor($currencyAmount * $this->xp_per_currency_unit),
            self::XP_TYPE_MULTIPLIER => (int) floor($this->xp_amount * $this->xp_per_currency_unit),
            default => $this->xp_amount,
        };

        // Apply cap if set
        if ($this->max_xp_per_action && $xp > $this->max_xp_per_action) {
            $xp = $this->max_xp_per_action;
        }

        return max(0, $xp);
    }

    /**
     * Check if customer can earn XP from this action (rate limiting)
     */
    public function canCustomerEarn(int $customerId): array
    {
        $canEarn = true;
        $reason = null;

        // Check daily limit
        if ($this->max_times_per_day) {
            $todayCount = ExperienceTransaction::where('customer_id', $customerId)
                ->where('action_type', $this->action_type)
                ->whereDate('created_at', today())
                ->count();

            if ($todayCount >= $this->max_times_per_day) {
                $canEarn = false;
                $reason = 'Daily limit reached';
            }
        }

        // Check cooldown
        if ($canEarn && $this->cooldown_hours) {
            $lastTransaction = ExperienceTransaction::where('customer_id', $customerId)
                ->where('action_type', $this->action_type)
                ->latest()
                ->first();

            if ($lastTransaction && $lastTransaction->created_at->addHours($this->cooldown_hours)->isFuture()) {
                $canEarn = false;
                $nextAvailable = $lastTransaction->created_at->addHours($this->cooldown_hours);
                $reason = "Cooldown active until {$nextAvailable->format('H:i')}";
            }
        }

        return [
            'can_earn' => $canEarn,
            'reason' => $reason,
        ];
    }

    /**
     * Get action type label
     */
    public function getActionTypeLabelAttribute(): string
    {
        return self::ACTION_TYPES[$this->action_type] ?? ucfirst(str_replace('_', ' ', $this->action_type));
    }

    /**
     * Get XP type label
     */
    public function getXpTypeLabelAttribute(): string
    {
        return match ($this->xp_type) {
            self::XP_TYPE_FIXED => 'Fixed Amount',
            self::XP_TYPE_PER_CURRENCY => 'Per Currency Unit',
            self::XP_TYPE_MULTIPLIER => 'Multiplier',
            default => ucfirst($this->xp_type),
        };
    }

    /**
     * Create default actions for tenant
     */
    public static function createDefaultsForTenant(int $tenantId): void
    {
        $defaults = [
            [
                'action_type' => self::ACTION_TICKET_PURCHASE,
                'name' => ['en' => 'Ticket Purchase', 'ro' => 'Cumpărare bilet'],
                'description' => ['en' => 'Earn XP for every RON spent', 'ro' => 'Câștigă XP pentru fiecare RON cheltuit'],
                'xp_type' => self::XP_TYPE_PER_CURRENCY,
                'xp_amount' => 0,
                'xp_per_currency_unit' => 1,
            ],
            [
                'action_type' => self::ACTION_EVENT_CHECKIN,
                'name' => ['en' => 'Event Check-in', 'ro' => 'Check-in eveniment'],
                'description' => ['en' => 'Earn XP when you check in to an event', 'ro' => 'Câștigă XP când faci check-in la un eveniment'],
                'xp_type' => self::XP_TYPE_FIXED,
                'xp_amount' => 50,
                'max_times_per_day' => 3,
            ],
            [
                'action_type' => self::ACTION_REVIEW_SUBMITTED,
                'name' => ['en' => 'Review Submitted', 'ro' => 'Recenzie trimisă'],
                'description' => ['en' => 'Earn XP for leaving a review', 'ro' => 'Câștigă XP pentru o recenzie lăsată'],
                'xp_type' => self::XP_TYPE_FIXED,
                'xp_amount' => 25,
                'max_times_per_day' => 5,
            ],
            [
                'action_type' => self::ACTION_REFERRAL_CONVERSION,
                'name' => ['en' => 'Referral Conversion', 'ro' => 'Conversie referire'],
                'description' => ['en' => 'Earn XP when a friend you referred makes a purchase', 'ro' => 'Câștigă XP când un prieten referit face o achiziție'],
                'xp_type' => self::XP_TYPE_FIXED,
                'xp_amount' => 100,
            ],
            [
                'action_type' => self::ACTION_FIRST_PURCHASE,
                'name' => ['en' => 'First Purchase', 'ro' => 'Prima achiziție'],
                'description' => ['en' => 'Bonus XP for your first purchase', 'ro' => 'XP bonus pentru prima ta achiziție'],
                'xp_type' => self::XP_TYPE_FIXED,
                'xp_amount' => 50,
            ],
        ];

        foreach ($defaults as $default) {
            self::firstOrCreate(
                ['tenant_id' => $tenantId, 'action_type' => $default['action_type']],
                array_merge($default, ['is_active' => true])
            );
        }
    }

    /**
     * Create default actions for marketplace
     */
    public static function createDefaultsForMarketplace(int $marketplaceClientId): void
    {
        $defaults = [
            [
                'action_type' => self::ACTION_TICKET_PURCHASE,
                'name' => ['en' => 'Ticket Purchase', 'ro' => 'Cumpărare bilet'],
                'description' => ['en' => 'Earn XP for every RON spent', 'ro' => 'Câștigă XP pentru fiecare RON cheltuit'],
                'xp_type' => self::XP_TYPE_PER_CURRENCY,
                'xp_amount' => 0,
                'xp_per_currency_unit' => 1,
            ],
            [
                'action_type' => self::ACTION_EVENT_CHECKIN,
                'name' => ['en' => 'Event Check-in', 'ro' => 'Check-in eveniment'],
                'description' => ['en' => 'Earn XP when you check in to an event', 'ro' => 'Câștigă XP când faci check-in la un eveniment'],
                'xp_type' => self::XP_TYPE_FIXED,
                'xp_amount' => 50,
                'max_times_per_day' => 3,
            ],
            [
                'action_type' => self::ACTION_REVIEW_SUBMITTED,
                'name' => ['en' => 'Review Submitted', 'ro' => 'Recenzie trimisă'],
                'description' => ['en' => 'Earn XP for leaving a review', 'ro' => 'Câștigă XP pentru o recenzie lăsată'],
                'xp_type' => self::XP_TYPE_FIXED,
                'xp_amount' => 25,
                'max_times_per_day' => 5,
            ],
            [
                'action_type' => self::ACTION_REFERRAL_CONVERSION,
                'name' => ['en' => 'Referral Conversion', 'ro' => 'Conversie referire'],
                'description' => ['en' => 'Earn XP when a friend you referred makes a purchase', 'ro' => 'Câștigă XP când un prieten referit face o achiziție'],
                'xp_type' => self::XP_TYPE_FIXED,
                'xp_amount' => 100,
            ],
            [
                'action_type' => self::ACTION_FIRST_PURCHASE,
                'name' => ['en' => 'First Purchase', 'ro' => 'Prima achiziție'],
                'description' => ['en' => 'Bonus XP for your first purchase', 'ro' => 'XP bonus pentru prima ta achiziție'],
                'xp_type' => self::XP_TYPE_FIXED,
                'xp_amount' => 50,
            ],
        ];

        foreach ($defaults as $default) {
            self::firstOrCreate(
                ['marketplace_client_id' => $marketplaceClientId, 'action_type' => $default['action_type']],
                array_merge($default, ['is_active' => true])
            );
        }
    }
}
