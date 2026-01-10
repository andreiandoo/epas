<?php

namespace App\Models\Gamification;

use App\Models\Customer;
use App\Models\MarketplaceClient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RewardRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'reward_id',
        'customer_id',
        'marketplace_customer_id',
        'points_spent',
        'points_transaction_id',
        'reward_snapshot',
        'voucher_code',
        'voucher_expires_at',
        'voucher_used',
        'voucher_used_at',
        'status',
        'reference_type',
        'reference_id',
        'discount_applied',
    ];

    protected $casts = [
        'points_spent' => 'integer',
        'reward_snapshot' => 'array',
        'voucher_expires_at' => 'datetime',
        'voucher_used' => 'boolean',
        'voucher_used_at' => 'datetime',
        'discount_applied' => 'decimal:2',
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

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

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

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if voucher is still valid
     */
    public function isVoucherValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->voucher_used) {
            return false;
        }

        if ($this->voucher_expires_at && $this->voucher_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Mark voucher as used
     */
    public function markAsUsed(string $referenceType = null, int $referenceId = null, float $discountApplied = null): self
    {
        $this->update([
            'status' => 'used',
            'voucher_used' => true,
            'voucher_used_at' => now(),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'discount_applied' => $discountApplied,
        ]);

        return $this;
    }

    /**
     * Mark as expired
     */
    public function markAsExpired(): self
    {
        $this->update(['status' => 'expired']);
        return $this;
    }

    /**
     * Cancel redemption (refund points)
     */
    public function cancel(?int $createdBy = null): self
    {
        if ($this->status === 'cancelled') {
            return $this;
        }

        // Refund points
        if ($this->points_spent > 0) {
            $customerPoints = CustomerPoints::where('tenant_id', $this->tenant_id)
                ->where('customer_id', $this->customer_id)
                ->first();

            if ($customerPoints) {
                $customerPoints->adjustPoints($this->points_spent, 'Reward redemption cancelled', $createdBy);
            }
        }

        $this->update(['status' => 'cancelled']);

        return $this;
    }

    /**
     * Get reward name from snapshot
     */
    public function getRewardNameAttribute(): string
    {
        return $this->reward_snapshot['name'] ?? $this->reward?->getTranslation('name', app()->getLocale()) ?? 'Unknown Reward';
    }

    /**
     * Get reward type from snapshot
     */
    public function getRewardTypeAttribute(): string
    {
        return $this->reward_snapshot['type'] ?? $this->reward?->type ?? 'unknown';
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'active' => 'success',
            'used' => 'info',
            'expired' => 'gray',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
}
