<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wristband extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_pass_purchase_id',
        'customer_id',
        'uid',
        'wristband_type',
        'status',
        'balance_cents',
        'currency',
        'assigned_at',
        'activated_at',
        'disabled_at',
        'disabled_reason',
        'access_zones',
        'scan_log',
        'meta',
    ];

    protected $casts = [
        'balance_cents' => 'integer',
        'assigned_at'   => 'datetime',
        'activated_at'  => 'datetime',
        'disabled_at'   => 'datetime',
        'access_zones'  => 'array',
        'scan_log'      => 'array',
        'meta'          => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function passPurchase(): BelongsTo
    {
        return $this->belongsTo(FestivalPassPurchase::class, 'festival_pass_purchase_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getBalanceAttribute(): float
    {
        return $this->balance_cents / 100;
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['assigned', 'activated']);
    }

    public function activate(): void
    {
        $this->update([
            'status'       => 'activated',
            'activated_at' => now(),
        ]);
    }

    public function disable(string $reason = 'manual'): void
    {
        $this->update([
            'status'          => 'disabled',
            'disabled_at'     => now(),
            'disabled_reason' => $reason,
        ]);
    }

    public function assignTo(FestivalPassPurchase $purchase): void
    {
        $this->update([
            'festival_pass_purchase_id' => $purchase->id,
            'customer_id'               => $purchase->customer_id,
            'status'                    => 'assigned',
            'assigned_at'               => now(),
        ]);
    }

    public function topUp(int $amountCents): void
    {
        $this->increment('balance_cents', $amountCents);
    }

    public function charge(int $amountCents): bool
    {
        if ($this->balance_cents < $amountCents) {
            return false;
        }

        $this->decrement('balance_cents', $amountCents);
        return true;
    }

    public function canAccessZone(string $zone): bool
    {
        if (empty($this->access_zones)) {
            return true;
        }

        return in_array($zone, $this->access_zones);
    }

    public function logScan(string $location, string $result = 'ok'): void
    {
        $log = $this->scan_log ?? [];
        array_unshift($log, [
            'at'       => now()->toIso8601String(),
            'location' => $location,
            'result'   => $result,
        ]);
        $this->update(['scan_log' => array_slice($log, 0, 50)]);
    }
}
