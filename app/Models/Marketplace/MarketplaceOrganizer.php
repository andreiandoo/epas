<?php

namespace App\Models\Marketplace;

use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * MarketplaceOrganizer Model
 *
 * Represents an event organizer within a marketplace tenant.
 * Organizers can create events, manage their sales, and receive payouts.
 */
class MarketplaceOrganizer extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'status',
        'description',
        // Company details
        'company_name',
        'cui',
        'reg_com',
        'address',
        'city',
        'county',
        'country',
        'postal_code',
        // Contact
        'contact_name',
        'contact_email',
        'contact_phone',
        // Branding
        'logo',
        'cover_image',
        'website_url',
        'facebook_url',
        'instagram_url',
        // Commission
        'commission_type',
        'commission_percent',
        'commission_fixed',
        // Payout
        'payout_method',
        'payout_details',
        'payout_frequency',
        'minimum_payout',
        'payout_currency',
        // Settings
        'settings',
        'allowed_features',
        // Verification
        'is_verified',
        'verified_at',
        'verified_by',
        // Contract
        'contract_status',
        'contract_signed_at',
        'contract_signature_ip',
        'contract_signature_data',
        // Statistics
        'total_events',
        'total_orders',
        'total_revenue',
        'pending_payout',
    ];

    protected $casts = [
        'payout_details' => 'array',
        'settings' => 'array',
        'allowed_features' => 'array',
        'commission_percent' => 'decimal:2',
        'commission_fixed' => 'decimal:2',
        'minimum_payout' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'pending_payout' => 'decimal:2',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'contract_signed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    /**
     * Commission type constants
     */
    public const COMMISSION_PERCENT = 'percent';
    public const COMMISSION_FIXED = 'fixed';
    public const COMMISSION_BOTH = 'both';

    /**
     * Payout method constants
     */
    public const PAYOUT_BANK_TRANSFER = 'bank_transfer';
    public const PAYOUT_PAYPAL = 'paypal';
    public const PAYOUT_STRIPE_CONNECT = 'stripe_connect';

    /**
     * Payout frequency constants
     */
    public const PAYOUT_WEEKLY = 'weekly';
    public const PAYOUT_BIWEEKLY = 'biweekly';
    public const PAYOUT_MONTHLY = 'monthly';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the marketplace (tenant) this organizer belongs to.
     */
    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Alias for marketplace relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->marketplace();
    }

    /**
     * Get the users who manage this organizer.
     */
    public function users(): HasMany
    {
        return $this->hasMany(MarketplaceOrganizerUser::class, 'organizer_id');
    }

    /**
     * Get the active users for this organizer.
     */
    public function activeUsers(): HasMany
    {
        return $this->users()->where('is_active', true);
    }

    /**
     * Get the admin users for this organizer.
     */
    public function adminUsers(): HasMany
    {
        return $this->users()->where('role', 'admin');
    }

    /**
     * Get the events created by this organizer.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    /**
     * Get the published/active events.
     */
    public function activeEvents(): HasMany
    {
        return $this->events()->where('is_cancelled', false);
    }

    /**
     * Get the upcoming events.
     */
    public function upcomingEvents(): HasMany
    {
        return $this->events()->upcoming();
    }

    /**
     * Get all orders for this organizer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'organizer_id');
    }

    /**
     * Get paid orders.
     */
    public function paidOrders(): HasMany
    {
        return $this->orders()->whereIn('status', ['paid', 'confirmed', 'completed']);
    }

    /**
     * Get payouts for this organizer.
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(MarketplacePayout::class, 'organizer_id');
    }

    /**
     * Get pending payouts.
     */
    public function pendingPayouts(): HasMany
    {
        return $this->payouts()->where('status', 'pending');
    }

    /**
     * Get venues created by this organizer.
     */
    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class, 'organizer_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get active organizers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get verified organizers.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to get pending approval organizers.
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    /**
     * Scope to filter by marketplace tenant.
     */
    public function scopeForMarketplace($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // =========================================================================
    // COMMISSION HELPERS
    // =========================================================================

    /**
     * Get the effective commission type (organizer's or marketplace's default).
     */
    public function getEffectiveCommissionType(): ?string
    {
        return $this->commission_type ?? $this->marketplace->marketplace_commission_type;
    }

    /**
     * Get the effective commission percentage.
     */
    public function getEffectiveCommissionPercent(): float
    {
        if ($this->commission_type !== null) {
            return (float) ($this->commission_percent ?? 0);
        }
        return (float) ($this->marketplace->marketplace_commission_percent ?? 0);
    }

    /**
     * Get the effective fixed commission amount.
     */
    public function getEffectiveCommissionFixed(): float
    {
        if ($this->commission_type !== null) {
            return (float) ($this->commission_fixed ?? 0);
        }
        return (float) ($this->marketplace->marketplace_commission_fixed ?? 0);
    }

    /**
     * Check if organizer has custom commission settings.
     */
    public function hasCustomCommission(): bool
    {
        return $this->commission_type !== null;
    }

    /**
     * Calculate commission for a given order total.
     * Returns array with breakdown.
     */
    public function calculateCommission(float $orderTotal): array
    {
        return $this->marketplace->calculateMarketplaceCommission($orderTotal, $this);
    }

    // =========================================================================
    // REVENUE HELPERS
    // =========================================================================

    /**
     * Get pending revenue (paid orders not yet in a payout).
     */
    public function getPendingRevenue(): float
    {
        return (float) $this->orders()
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNull('payout_id')
            ->sum('organizer_revenue');
    }

    /**
     * Get total revenue (all time).
     */
    public function getTotalRevenue(): float
    {
        return (float) $this->orders()
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->sum('organizer_revenue');
    }

    /**
     * Get total paid out amount.
     */
    public function getTotalPaidOut(): float
    {
        return (float) $this->payouts()
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Get revenue for a specific period.
     */
    public function getRevenueForPeriod(\Carbon\Carbon $start, \Carbon\Carbon $end): float
    {
        return (float) $this->orders()
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('organizer_revenue');
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    /**
     * Check if organizer is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if organizer is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if organizer is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Approve the organizer.
     */
    public function approve(?int $approvedBy = null): bool
    {
        $this->status = self::STATUS_ACTIVE;
        $this->is_verified = true;
        $this->verified_at = now();
        $this->verified_by = $approvedBy;
        return $this->save();
    }

    /**
     * Suspend the organizer.
     */
    public function suspend(): bool
    {
        $this->status = self::STATUS_SUSPENDED;
        return $this->save();
    }

    /**
     * Reactivate a suspended organizer.
     */
    public function reactivate(): bool
    {
        $this->status = self::STATUS_ACTIVE;
        return $this->save();
    }

    // =========================================================================
    // FEATURE HELPERS
    // =========================================================================

    /**
     * Check if organizer has access to a feature.
     */
    public function hasFeature(string $feature): bool
    {
        $allowed = $this->allowed_features ?? [];
        return in_array($feature, $allowed);
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    // =========================================================================
    // STATISTICS HELPERS
    // =========================================================================

    /**
     * Recalculate and update cached statistics.
     */
    public function refreshStatistics(): void
    {
        $this->total_events = $this->events()->count();
        $this->total_orders = $this->paidOrders()->count();
        $this->total_revenue = $this->getTotalRevenue();
        $this->pending_payout = $this->getPendingRevenue();
        $this->save();
    }

    // =========================================================================
    // BOOT & OBSERVERS
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (MarketplaceOrganizer $organizer) {
            // Auto-generate slug if not provided
            if (empty($organizer->slug)) {
                $organizer->slug = Str::slug($organizer->name);
            }

            // Ensure unique slug within marketplace
            $baseSlug = $organizer->slug;
            $counter = 1;
            while (static::where('tenant_id', $organizer->tenant_id)
                ->where('slug', $organizer->slug)
                ->exists()) {
                $organizer->slug = $baseSlug . '-' . $counter++;
            }
        });
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'status', 'is_verified', 'commission_type',
                'commission_percent', 'commission_fixed', 'payout_method'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Organizer {$eventName}")
            ->useLogName('marketplace');
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->put('tenant_id', $this->tenant_id);
    }
}
