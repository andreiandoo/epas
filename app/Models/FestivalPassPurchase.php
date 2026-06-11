<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class FestivalPassPurchase extends Model
{
    protected $fillable = [
        'festival_pass_id',
        'tenant_id',
        'customer_id',
        'order_id',
        'code',
        'holder_name',
        'holder_email',
        'holder_phone',
        'status',
        'activated_at',
        'checked_in_at',
        'checked_in_gate',
        'cancelled_at',
        'cancel_reason',
        'day_checkins',
        'meta',
    ];

    protected $casts = [
        'activated_at'  => 'datetime',
        'checked_in_at' => 'datetime',
        'cancelled_at'  => 'datetime',
        'day_checkins'  => 'array',
        'meta'          => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $purchase) {
            if (empty($purchase->code)) {
                $purchase->code = 'FEST-' . strtoupper(Str::random(10));
            }
        });
    }

    public function festivalPass(): BelongsTo
    {
        return $this->belongsTo(FestivalPass::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function wristband(): HasOne
    {
        return $this->hasOne(Wristband::class);
    }

    public function addonPurchases(): HasMany
    {
        return $this->hasMany(FestivalAddonPurchase::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function checkInForDay(int $dayId, ?string $gate = null): void
    {
        $checkins = $this->day_checkins ?? [];
        $checkins[$dayId] = now()->toIso8601String();

        $this->update([
            'day_checkins'   => $checkins,
            'checked_in_at'  => $this->checked_in_at ?? now(),
            'checked_in_gate' => $gate,
            'status'         => 'checked_in',
        ]);
    }

    public function hasCheckedInForDay(int $dayId): bool
    {
        return isset($this->day_checkins[$dayId]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
        ]);

        $pass = $this->festivalPass;
        if ($pass && $pass->quota_sold > 0) {
            $pass->decrement('quota_sold');
        }
    }
}
