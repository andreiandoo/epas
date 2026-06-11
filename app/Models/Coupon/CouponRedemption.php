<?php

namespace App\Models\Coupon;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    use HasUuids;

    protected $table = 'coupon_redemptions';

    protected $fillable = [
        'tenant_id',
        'coupon_id',
        'user_id',
        'order_id',
        'discount_applied',
        'original_amount',
        'final_amount',
        'currency',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'status',
        'refunded_at',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CouponCode::class, 'coupon_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function complete(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function refund(): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        // Decrement coupon usage
        $this->coupon->decrement('current_uses');

        // If campaign exists, refund budget
        if ($this->coupon->campaign) {
            $this->coupon->campaign->decrement('budget_used', $this->discount_applied);
            $this->coupon->campaign->decrement('redemption_count');
        }
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);

        // Decrement coupon usage
        $this->coupon->decrement('current_uses');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForCoupon($query, $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
