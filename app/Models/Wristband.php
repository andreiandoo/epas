<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function transactions(): HasMany
    {
        return $this->hasMany(WristbandTransaction::class);
    }

    public function topUp(int $amountCents, ?string $paymentMethod = null, ?string $operator = null): WristbandTransaction
    {
        $balanceBefore = $this->balance_cents;
        $this->increment('balance_cents', $amountCents);

        return $this->transactions()->create([
            'tenant_id'           => $this->tenant_id,
            'customer_id'         => $this->customer_id,
            'transaction_type'    => 'topup',
            'amount_cents'        => $amountCents,
            'balance_before_cents' => $balanceBefore,
            'balance_after_cents'  => $balanceBefore + $amountCents,
            'currency'            => $this->currency,
            'payment_method'      => $paymentMethod,
            'operator'            => $operator,
        ]);
    }

    public function charge(int $amountCents, ?string $vendorName = null, ?string $vendorLocation = null, ?string $description = null): bool
    {
        if ($this->balance_cents < $amountCents) {
            return false;
        }

        $balanceBefore = $this->balance_cents;
        $this->decrement('balance_cents', $amountCents);

        $this->transactions()->create([
            'tenant_id'           => $this->tenant_id,
            'customer_id'         => $this->customer_id,
            'transaction_type'    => 'payment',
            'amount_cents'        => $amountCents,
            'balance_before_cents' => $balanceBefore,
            'balance_after_cents'  => $balanceBefore - $amountCents,
            'currency'            => $this->currency,
            'vendor_name'         => $vendorName,
            'vendor_location'     => $vendorLocation,
            'description'         => $description,
        ]);

        return true;
    }

    public function refund(int $amountCents, ?string $description = null, ?string $operator = null): WristbandTransaction
    {
        $balanceBefore = $this->balance_cents;
        $this->increment('balance_cents', $amountCents);

        return $this->transactions()->create([
            'tenant_id'           => $this->tenant_id,
            'customer_id'         => $this->customer_id,
            'transaction_type'    => 'refund',
            'amount_cents'        => $amountCents,
            'balance_before_cents' => $balanceBefore,
            'balance_after_cents'  => $balanceBefore + $amountCents,
            'currency'            => $this->currency,
            'description'         => $description,
            'operator'            => $operator,
        ]);
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
