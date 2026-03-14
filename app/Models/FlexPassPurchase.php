<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FlexPassPurchase extends Model
{
    protected $fillable = [
        'flex_pass_id',
        'tenant_id',
        'customer_id',
        'order_id',
        'code',
        'entries_remaining',
        'entries_used',
        'status',
        'activated_at',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'entries_remaining' => 'integer',
        'entries_used'      => 'integer',
        'activated_at'      => 'datetime',
        'expires_at'        => 'datetime',
        'meta'              => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (FlexPassPurchase $purchase) {
            if (empty($purchase->code)) {
                $purchase->code = 'FP-' . strtoupper(Str::random(10));
            }
        });
    }

    public function flexPass(): BelongsTo
    {
        return $this->belongsTo(FlexPass::class);
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

    public function redemptions(): HasMany
    {
        return $this->hasMany(FlexPassRedemption::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasEntriesRemaining(): bool
    {
        return $this->entries_remaining > 0;
    }

    public function canRedeemForEvent(int $eventId): bool
    {
        if (!$this->isActive() || !$this->hasEntriesRemaining()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if (!$this->flexPass->isEventEligible($eventId)) {
            return false;
        }

        $usedForEvent = $this->redemptions()
            ->where('event_id', $eventId)
            ->where('status', 'confirmed')
            ->count();

        return $usedForEvent < $this->flexPass->max_entries_per_event;
    }

    public function redeem(int $eventId, ?int $performanceId = null): FlexPassRedemption
    {
        $redemption = $this->redemptions()->create([
            'event_id'       => $eventId,
            'performance_id' => $performanceId,
            'status'         => 'confirmed',
            'redeemed_at'    => now(),
        ]);

        $this->decrement('entries_remaining');
        $this->increment('entries_used');

        if ($this->entries_remaining <= 0) {
            $this->update(['status' => 'fully_used']);
        }

        return $redemption;
    }
}
