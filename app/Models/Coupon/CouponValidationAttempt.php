<?php

namespace App\Models\Coupon;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponValidationAttempt extends Model
{
    protected $table = 'coupon_validation_attempts';

    protected $fillable = [
        'tenant_id',
        'coupon_code',
        'user_id',
        'cart_amount',
        'is_valid',
        'rejection_reason',
        'ip_address',
        'device_fingerprint',
    ];

    protected $casts = [
        'cart_amount' => 'decimal:2',
        'is_valid' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check for suspicious activity from same IP
     */
    public static function isSuspiciousIp(string $tenantId, string $ipAddress, int $threshold = 10): bool
    {
        $recentAttempts = static::where('tenant_id', $tenantId)
            ->where('ip_address', $ipAddress)
            ->where('is_valid', false)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentAttempts >= $threshold;
    }

    /**
     * Check for suspicious activity from same user
     */
    public static function isSuspiciousUser(string $tenantId, int $userId, int $threshold = 5): bool
    {
        $recentAttempts = static::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_valid', false)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentAttempts >= $threshold;
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
