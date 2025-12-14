<?php

namespace App\Models\Gamification;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Referral extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SIGNED_UP = 'signed_up';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'referrer_customer_id',
        'referred_customer_id',
        'referred_email',
        'referral_code',
        'status',
        'referrer_points_awarded',
        'referred_points_awarded',
        'points_processed',
        'reference_type',
        'reference_id',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'ip_address',
        'user_agent',
        'referred_at',
        'signed_up_at',
        'converted_at',
        'expires_at',
    ];

    protected $casts = [
        'points_processed' => 'boolean',
        'referred_at' => 'datetime',
        'signed_up_at' => 'datetime',
        'converted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    public function referrerPoints(): BelongsTo
    {
        return $this->belongsTo(CustomerPoints::class, 'referrer_customer_id', 'customer_id')
            ->where('tenant_id', $this->tenant_id);
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

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSignedUp($query)
    {
        return $query->where('status', self::STATUS_SIGNED_UP);
    }

    public function scopeConverted($query)
    {
        return $query->where('status', self::STATUS_CONVERTED);
    }

    public function scopeForReferrer($query, int $customerId)
    {
        return $query->where('referrer_customer_id', $customerId);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('points_processed', false);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Create a pending referral when link is clicked
     */
    public static function createPending(int $tenantId, int $referrerId, string $referralCode, array $metadata = []): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'referrer_customer_id' => $referrerId,
            'referral_code' => $referralCode,
            'status' => self::STATUS_PENDING,
            'referred_email' => $metadata['email'] ?? null,
            'source' => $metadata['source'] ?? 'direct',
            'utm_source' => $metadata['utm_source'] ?? null,
            'utm_medium' => $metadata['utm_medium'] ?? null,
            'utm_campaign' => $metadata['utm_campaign'] ?? null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'user_agent' => $metadata['user_agent'] ?? null,
            'referred_at' => now(),
            'expires_at' => now()->addDays(30), // 30-day cookie
        ]);
    }

    /**
     * Mark as signed up when referred customer creates account
     */
    public function markSignedUp(Customer $referredCustomer): self
    {
        $this->update([
            'referred_customer_id' => $referredCustomer->id,
            'referred_email' => $referredCustomer->email,
            'status' => self::STATUS_SIGNED_UP,
            'signed_up_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark as converted when referred customer makes qualifying order
     */
    public function markConverted(string $referenceType, int $referenceId): self
    {
        $this->update([
            'status' => self::STATUS_CONVERTED,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'converted_at' => now(),
        ]);

        return $this;
    }

    /**
     * Process and award points
     */
    public function processPoints(): bool
    {
        if ($this->points_processed || $this->status !== self::STATUS_CONVERTED) {
            return false;
        }

        $config = GamificationConfig::where('tenant_id', $this->tenant_id)->first();

        if (!$config || !$config->is_active) {
            return false;
        }

        // Award points to referrer
        $referrerPoints = CustomerPoints::getOrCreate($this->tenant_id, $this->referrer_customer_id);
        $referrerPoints->addPoints($config->referral_bonus_points, GamificationAction::ACTION_REFERRAL, [
            'reference_type' => Customer::class,
            'reference_id' => $this->referred_customer_id,
            'description' => ['en' => 'Referral bonus', 'ro' => 'Bonus referire'],
            'metadata' => ['referral_id' => $this->id],
        ]);

        // Update referrer stats
        $referrerPoints->increment('referral_count');
        $referrerPoints->increment('referral_points_earned', $config->referral_bonus_points);

        // Award points to referred
        if ($this->referred_customer_id) {
            $referredPoints = CustomerPoints::getOrCreate($this->tenant_id, $this->referred_customer_id);
            $referredPoints->addPoints($config->referred_bonus_points, GamificationAction::ACTION_REFERRAL, [
                'reference_type' => Customer::class,
                'reference_id' => $this->referrer_customer_id,
                'description' => ['en' => 'Welcome bonus from referral', 'ro' => 'Bonus de bun venit din referire'],
                'metadata' => ['referral_id' => $this->id],
            ]);
        }

        // Update referral record
        $this->update([
            'referrer_points_awarded' => $config->referral_bonus_points,
            'referred_points_awarded' => $config->referred_bonus_points,
            'points_processed' => true,
        ]);

        return true;
    }

    /**
     * Check if referral is expired
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->expires_at && $this->expires_at->lt(now())) {
            return true;
        }

        return false;
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_SIGNED_UP => 'bg-blue-100 text-blue-800',
            self::STATUS_CONVERTED => 'bg-green-100 text-green-800',
            self::STATUS_EXPIRED => 'bg-gray-100 text-gray-800',
            self::STATUS_CANCELLED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        $locale = app()->getLocale();

        return match ($this->status) {
            self::STATUS_PENDING => $locale === 'ro' ? 'In asteptare' : 'Pending',
            self::STATUS_SIGNED_UP => $locale === 'ro' ? 'Cont creat' : 'Signed Up',
            self::STATUS_CONVERTED => $locale === 'ro' ? 'Convertit' : 'Converted',
            self::STATUS_EXPIRED => $locale === 'ro' ? 'Expirat' : 'Expired',
            self::STATUS_CANCELLED => $locale === 'ro' ? 'Anulat' : 'Cancelled',
            default => $this->status,
        };
    }

    /**
     * Find active referral by code for a tenant
     */
    public static function findByCode(int $tenantId, string $code): ?self
    {
        // First try to find by referral_code
        $referral = self::where('tenant_id', $tenantId)
            ->where('referral_code', $code)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SIGNED_UP])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($referral) {
            return $referral;
        }

        // Try to find customer with this referral code
        $customerPoints = CustomerPoints::where('tenant_id', $tenantId)
            ->where('referral_code', $code)
            ->first();

        if ($customerPoints) {
            // Create a new pending referral
            return self::createPending($tenantId, $customerPoints->customer_id, $code);
        }

        return null;
    }
}
