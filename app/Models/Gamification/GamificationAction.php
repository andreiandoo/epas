<?php

namespace App\Models\Gamification;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class GamificationAction extends Model
{
    use HasFactory, HasTranslations;

    public array $translatable = ['name', 'description'];

    // Available action types
    public const ACTION_ORDER = 'order';
    public const ACTION_BIRTHDAY = 'birthday';
    public const ACTION_REFERRAL = 'referral';
    public const ACTION_SIGNUP = 'signup';
    public const ACTION_REVIEW = 'review';
    public const ACTION_SOCIAL_SHARE = 'social_share';
    public const ACTION_PROFILE_COMPLETE = 'profile_complete';
    public const ACTION_FIRST_ORDER = 'first_order';
    public const ACTION_REPEAT_PURCHASE = 'repeat_purchase';
    public const ACTION_EVENT_CHECKIN = 'event_checkin';

    protected $fillable = [
        'tenant_id',
        'action_type',
        'name',
        'description',
        'points_type',
        'points_amount',
        'multiplier',
        'min_order_cents',
        'max_points_per_action',
        'max_times_per_day',
        'max_times_per_customer',
        'cooldown_hours',
        'valid_from',
        'valid_until',
        'valid_days',
        'customer_tiers',
        'new_customers_only',
        'min_orders_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'multiplier' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'valid_days' => 'array',
        'customer_tiers' => 'array',
        'new_customers_only' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('action_type', $type);
    }

    public function scopeCurrentlyValid($query)
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $now->toDateString());
        })->where(function ($q) use ($now) {
            $q->whereNull('valid_until')
              ->orWhere('valid_until', '>=', $now->toDateString());
        });
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get all available action types
     */
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_ORDER => ['en' => 'Order Completed', 'ro' => 'Comanda finalizata'],
            self::ACTION_BIRTHDAY => ['en' => 'Birthday Bonus', 'ro' => 'Bonus zi de nastere'],
            self::ACTION_REFERRAL => ['en' => 'Successful Referral', 'ro' => 'Referire reusita'],
            self::ACTION_SIGNUP => ['en' => 'Account Signup', 'ro' => 'Creare cont'],
            self::ACTION_REVIEW => ['en' => 'Product Review', 'ro' => 'Recenzie produs'],
            self::ACTION_SOCIAL_SHARE => ['en' => 'Social Share', 'ro' => 'Distribuire sociala'],
            self::ACTION_PROFILE_COMPLETE => ['en' => 'Complete Profile', 'ro' => 'Profil complet'],
            self::ACTION_FIRST_ORDER => ['en' => 'First Order Bonus', 'ro' => 'Bonus prima comanda'],
            self::ACTION_REPEAT_PURCHASE => ['en' => 'Repeat Purchase', 'ro' => 'Achizitie repetata'],
            self::ACTION_EVENT_CHECKIN => ['en' => 'Event Check-in', 'ro' => 'Check-in eveniment'],
        ];
    }

    /**
     * Calculate points for this action
     */
    public function calculatePoints(int $orderAmountCents = 0): int
    {
        switch ($this->points_type) {
            case 'fixed':
                $points = $this->points_amount;
                break;

            case 'percentage':
                $points = (int) floor(($orderAmountCents * $this->points_amount) / 100);
                break;

            case 'multiplier':
                // Base points from config * multiplier
                $config = GamificationConfig::where('tenant_id', $this->tenant_id)->first();
                if ($config) {
                    $basePoints = $config->calculateEarnedPoints($orderAmountCents);
                    $points = (int) floor($basePoints * $this->multiplier);
                } else {
                    $points = 0;
                }
                break;

            default:
                $points = $this->points_amount;
        }

        // Apply cap if set
        if ($this->max_points_per_action && $points > $this->max_points_per_action) {
            $points = $this->max_points_per_action;
        }

        return $points;
    }

    /**
     * Check if action is valid for the current date/time
     */
    public function isCurrentlyValid(): bool
    {
        $now = now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        if (!empty($this->valid_days)) {
            if (!in_array($now->dayOfWeek, $this->valid_days)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if customer is eligible for this action
     */
    public function isEligibleForCustomer(CustomerPoints $customerPoints): bool
    {
        // Check if action is active and valid
        if (!$this->is_active || !$this->isCurrentlyValid()) {
            return false;
        }

        // Check tier restriction
        if (!empty($this->customer_tiers)) {
            if (!in_array($customerPoints->current_tier, $this->customer_tiers)) {
                return false;
            }
        }

        // Check new customers only
        if ($this->new_customers_only) {
            // Consider new if less than 30 days since first transaction
            $firstTransaction = PointsTransaction::where('tenant_id', $this->tenant_id)
                ->where('customer_id', $customerPoints->customer_id)
                ->oldest()
                ->first();

            if ($firstTransaction && $firstTransaction->created_at->lt(now()->subDays(30))) {
                return false;
            }
        }

        // Check minimum orders required
        if ($this->min_orders_required > 0) {
            $orderCount = PointsTransaction::where('tenant_id', $this->tenant_id)
                ->where('customer_id', $customerPoints->customer_id)
                ->where('action_type', self::ACTION_ORDER)
                ->count();

            if ($orderCount < $this->min_orders_required) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check rate limits for this action
     */
    public function checkRateLimits(int $customerId): bool
    {
        // Check daily limit
        if ($this->max_times_per_day) {
            $todayCount = PointsTransaction::where('tenant_id', $this->tenant_id)
                ->where('customer_id', $customerId)
                ->where('action_type', $this->action_type)
                ->whereDate('created_at', today())
                ->count();

            if ($todayCount >= $this->max_times_per_day) {
                return false;
            }
        }

        // Check lifetime limit
        if ($this->max_times_per_customer) {
            $totalCount = PointsTransaction::where('tenant_id', $this->tenant_id)
                ->where('customer_id', $customerId)
                ->where('action_type', $this->action_type)
                ->count();

            if ($totalCount >= $this->max_times_per_customer) {
                return false;
            }
        }

        // Check cooldown
        if ($this->cooldown_hours) {
            $lastTransaction = PointsTransaction::where('tenant_id', $this->tenant_id)
                ->where('customer_id', $customerId)
                ->where('action_type', $this->action_type)
                ->latest()
                ->first();

            if ($lastTransaction && $lastTransaction->created_at->gt(now()->subHours($this->cooldown_hours))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Seed default actions for a tenant
     */
    public static function seedDefaultActions(int $tenantId): void
    {
        $defaults = [
            [
                'action_type' => self::ACTION_ORDER,
                'name' => ['en' => 'Order Points', 'ro' => 'Puncte din comenzi'],
                'description' => ['en' => 'Earn points on every order', 'ro' => 'Castiga puncte la fiecare comanda'],
                'points_type' => 'percentage',
                'points_amount' => 5, // 5% of order value
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'action_type' => self::ACTION_BIRTHDAY,
                'name' => ['en' => 'Birthday Bonus', 'ro' => 'Bonus zi de nastere'],
                'description' => ['en' => 'Special points on your birthday', 'ro' => 'Puncte speciale de ziua ta'],
                'points_type' => 'fixed',
                'points_amount' => 100,
                'max_times_per_customer' => 1,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'action_type' => self::ACTION_REFERRAL,
                'name' => ['en' => 'Referral Bonus', 'ro' => 'Bonus referire'],
                'description' => ['en' => 'Earn points when friends order', 'ro' => 'Castiga puncte cand prietenii comanda'],
                'points_type' => 'fixed',
                'points_amount' => 200,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'action_type' => self::ACTION_SIGNUP,
                'name' => ['en' => 'Welcome Bonus', 'ro' => 'Bonus de bun venit'],
                'description' => ['en' => 'Points for creating an account', 'ro' => 'Puncte pentru crearea contului'],
                'points_type' => 'fixed',
                'points_amount' => 50,
                'max_times_per_customer' => 1,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($defaults as $action) {
            self::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'action_type' => $action['action_type'],
                ],
                array_merge($action, ['tenant_id' => $tenantId])
            );
        }
    }
}
