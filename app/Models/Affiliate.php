<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Notifications\AffiliateApprovedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Affiliate extends Model
{
    use Notifiable;
    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING = 'pending'; // Pending approval for self-registered

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'code',
        'name',
        'contact_email',
        'status',
        'commission_rate',
        'commission_type',
        'meta',
        'payment_method',
        'payment_details',
        'pending_balance',
        'available_balance',
        'total_withdrawn',
        'last_withdrawal_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'payment_details' => 'array',
        'commission_rate' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'last_withdrawal_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($affiliate) {
            if (!$affiliate->tenant_id && auth()->check() && isset(auth()->user()->tenant_id)) {
                $affiliate->tenant_id = auth()->user()->tenant_id;
            }

            // Auto-generate code if not provided
            if (empty($affiliate->code)) {
                $affiliate->code = static::generateUniqueCode();
            }
        });
    }

    /**
     * Generate a unique affiliate code
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        } while (static::withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }

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

    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(AffiliateCoupon::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(AffiliateWithdrawal::class);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope for active affiliates
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for pending approval affiliates
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope for customer-owned affiliates
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // ==========================================
    // COMMISSION METHODS
    // ==========================================

    /**
     * Calculate commission for a given order amount
     */
    public function calculateCommission(float $amount): float
    {
        if ($this->commission_type === 'fixed') {
            return (float) $this->commission_rate;
        }

        return round(($amount * $this->commission_rate) / 100, 2);
    }

    /**
     * Check if email matches affiliate's contact email (self-purchase check)
     */
    public function isSelfPurchase(string $buyerEmail): bool
    {
        // Check affiliate email
        if (strtolower($this->contact_email) === strtolower($buyerEmail)) {
            return true;
        }

        // Check linked customer email
        if ($this->customer && strtolower($this->customer->email) === strtolower($buyerEmail)) {
            return true;
        }

        return false;
    }

    /**
     * Get total approved commission
     */
    public function getTotalCommissionAttribute(): float
    {
        return (float) $this->conversions()
            ->where('status', 'approved')
            ->sum('commission_value');
    }

    /**
     * Get total pending commission
     */
    public function getPendingCommissionAttribute(): float
    {
        return (float) $this->conversions()
            ->where('status', 'pending')
            ->sum('commission_value');
    }

    /**
     * Get total sales generated
     */
    public function getTotalSalesAttribute(): float
    {
        return (float) $this->conversions()
            ->whereIn('status', ['approved', 'pending'])
            ->sum('amount');
    }

    // ==========================================
    // BALANCE METHODS
    // ==========================================

    /**
     * Add commission to pending balance (when conversion is created)
     */
    public function addPendingCommission(float $amount): void
    {
        $this->increment('pending_balance', $amount);
    }

    /**
     * Release commission from pending to available (after hold period)
     */
    public function releaseCommission(float $amount): void
    {
        $this->decrement('pending_balance', min($amount, $this->pending_balance));
        $this->increment('available_balance', $amount);
    }

    /**
     * Reverse commission (when conversion is reversed)
     */
    public function reverseCommission(float $amount, bool $wasPending = true): void
    {
        if ($wasPending) {
            $this->decrement('pending_balance', min($amount, $this->pending_balance));
        } else {
            $this->decrement('available_balance', min($amount, $this->available_balance));
        }
    }

    /**
     * Request withdrawal
     */
    public function requestWithdrawal(float $amount, string $paymentMethod, array $paymentDetails, ?string $ip = null): ?AffiliateWithdrawal
    {
        if ($amount > $this->available_balance) {
            return null;
        }

        // Deduct from available balance
        $this->decrement('available_balance', $amount);

        // Create withdrawal request
        return AffiliateWithdrawal::create([
            'tenant_id' => $this->tenant_id,
            'affiliate_id' => $this->id,
            'reference' => AffiliateWithdrawal::generateReference(),
            'amount' => $amount,
            'currency' => $this->tenant->settings['currency'] ?? 'RON',
            'status' => AffiliateWithdrawal::STATUS_PENDING,
            'payment_method' => $paymentMethod,
            'payment_details' => $paymentDetails,
            'requested_ip' => $ip,
        ]);
    }

    /**
     * Recalculate balances from conversions
     */
    public function recalculateBalances(): void
    {
        $settings = AffiliateSettings::where('tenant_id', $this->tenant_id)->first();
        $holdDays = $settings?->commission_hold_days ?? 30;
        $holdDate = now()->subDays($holdDays);

        // Pending: approved conversions within hold period
        $pendingBalance = $this->conversions()
            ->where('status', 'approved')
            ->where('created_at', '>=', $holdDate)
            ->sum('commission_value');

        // Available: approved conversions past hold period, minus withdrawn
        $totalApproved = $this->conversions()
            ->where('status', 'approved')
            ->sum('commission_value');

        // Get total pending withdrawals
        $pendingWithdrawals = $this->withdrawals()
            ->whereIn('status', [AffiliateWithdrawal::STATUS_PENDING, AffiliateWithdrawal::STATUS_PROCESSING])
            ->sum('amount');

        $availableBalance = $totalApproved - $pendingBalance - $this->total_withdrawn - $pendingWithdrawals;

        $this->update([
            'pending_balance' => max(0, $pendingBalance),
            'available_balance' => max(0, $availableBalance),
        ]);
    }

    // ==========================================
    // STATUS METHODS
    // ==========================================

    /**
     * Approve pending affiliate
     */
    public function approve(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update(['status' => self::STATUS_ACTIVE]);

        // Send notification
        try {
            $this->notify(new AffiliateApprovedNotification($this));
        } catch (\Exception $e) {
            // Log but don't fail approval
            \Log::warning('Failed to send affiliate approval notification', [
                'affiliate_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Check if affiliate is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if affiliate is pending approval
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Get tracking URL
     */
    public function getTrackingUrl(): string
    {
        $domain = $this->tenant->domains()->where('is_primary', true)->first();

        if ($domain) {
            return "https://{$domain->domain}?aff={$this->code}";
        }

        return "?aff={$this->code}";
    }

    /**
     * Get status badge color
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_INACTIVE => 'gray',
            self::STATUS_SUSPENDED => 'danger',
            self::STATUS_PENDING => 'warning',
            default => 'gray',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        $locale = app()->getLocale();

        return match ($this->status) {
            self::STATUS_ACTIVE => $locale === 'ro' ? 'Activ' : 'Active',
            self::STATUS_INACTIVE => $locale === 'ro' ? 'Inactiv' : 'Inactive',
            self::STATUS_SUSPENDED => $locale === 'ro' ? 'Suspendat' : 'Suspended',
            self::STATUS_PENDING => $locale === 'ro' ? 'In asteptare' : 'Pending',
            default => $this->status,
        };
    }

    /**
     * Route notifications for mail channel
     */
    public function routeNotificationForMail(): string
    {
        return $this->contact_email;
    }

    /**
     * Format commission display
     */
    public function getFormattedCommission(): string
    {
        if ($this->commission_type === 'percent') {
            return number_format($this->commission_rate, 0) . '%';
        }

        return number_format($this->commission_rate, 2) . ' ' . ($this->tenant->settings['currency'] ?? 'RON');
    }
}
