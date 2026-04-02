<?php

namespace App\Models;

use App\Models\Cashless\CashlessAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Wristband extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'festival_pass_purchase_id',
        'customer_id',
        'uid',
        'wristband_type',
        'status',
        // balance_cents intentionally excluded — only modified via financial methods with row-level locking
        'currency',
        'pin_hash',
        'qr_payload',
        'last_transaction_at',
        'last_pos_device_uid',
        'assigned_at',
        'activated_at',
        'disabled_at',
        'disabled_reason',
        'access_zones',
        'scan_log',
        'meta',
    ];

    protected $hidden = ['pin_hash'];

    protected $casts = [
        'balance_cents'        => 'integer',
        'assigned_at'          => 'datetime',
        'activated_at'         => 'datetime',
        'disabled_at'          => 'datetime',
        'last_transaction_at'  => 'datetime',
        'access_zones'         => 'array',
        'scan_log'             => 'array',
        'meta'                 => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function passPurchase(): BelongsTo
    {
        return $this->belongsTo(FestivalPassPurchase::class, 'festival_pass_purchase_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WristbandTransaction::class);
    }

    public function cashlessAccount(): HasOne
    {
        return $this->hasOne(CashlessAccount::class);
    }

    // ── Accessors ──

    public function getBalanceAttribute(): float
    {
        return $this->balance_cents / 100;
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['assigned', 'activated']);
    }

    // ── State transitions ──

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

    // ── Financial operations ──
    // If a CashlessAccount exists, delegates to CashlessAccountService (source of truth).
    // Falls back to legacy direct-balance operations for wristbands without a CashlessAccount.

    public function topUp(int $amountCents, ?string $paymentMethod = null, ?string $operator = null): WristbandTransaction
    {
        if ($account = $this->cashlessAccount) {
            return app(\App\Services\Cashless\CashlessAccountService::class)->topUp(
                $account,
                $amountCents,
                \App\Enums\TopUpChannel::Physical,
                $paymentMethod ? \App\Enums\TopUpMethod::tryFrom($paymentMethod) : null,
                operator: $operator,
                paymentMethod: $paymentMethod,
            );
        }

        return $this->legacyTopUp($amountCents, $paymentMethod, $operator);
    }

    public function charge(
        int $amountCents,
        ?string $vendorName = null,
        ?string $vendorLocation = null,
        ?string $description = null,
        ?int $vendorId = null,
        ?int $posDeviceId = null
    ): bool|WristbandTransaction {
        if ($account = $this->cashlessAccount) {
            return app(\App\Services\Cashless\CashlessAccountService::class)->charge(
                $account,
                $amountCents,
                vendorName: $vendorName,
                vendorLocation: $vendorLocation,
                description: $description,
                vendorId: $vendorId,
                posDeviceId: $posDeviceId,
            );
        }

        return $this->legacyCharge($amountCents, $vendorName, $vendorLocation, $description, $vendorId, $posDeviceId);
    }

    public function refund(int $amountCents, ?string $description = null, ?string $operator = null): WristbandTransaction
    {
        if ($account = $this->cashlessAccount) {
            return app(\App\Services\Cashless\CashlessAccountService::class)->refund(
                $account, $amountCents, $description, $operator,
            );
        }

        return $this->legacyRefund($amountCents, $description, $operator);
    }

    public function transferTo(Wristband $target, ?string $operator = null): WristbandTransaction
    {
        $sourceAccount = $this->cashlessAccount;
        $targetAccount = $target->cashlessAccount;

        if ($sourceAccount && $targetAccount) {
            return app(\App\Services\Cashless\CashlessAccountService::class)->transfer(
                $sourceAccount, $targetAccount, $sourceAccount->balance_cents, $operator,
            );
        }

        return $this->legacyTransferTo($target, $operator);
    }

    public function cashout(?string $operator = null): WristbandTransaction
    {
        if ($account = $this->cashlessAccount) {
            return app(\App\Services\Cashless\CashlessAccountService::class)->cashout(
                $account, operator: $operator,
            );
        }

        return $this->legacyCashout($operator);
    }

    // ── Legacy financial operations (direct balance, no CashlessAccount) ──

    private function legacyTopUp(int $amountCents, ?string $paymentMethod, ?string $operator): WristbandTransaction
    {
        return DB::transaction(function () use ($amountCents, $paymentMethod, $operator) {
            $locked = self::lockForUpdate()->find($this->id);
            $balanceBefore = $locked->balance_cents;

            $locked->balance_cents = $balanceBefore + $amountCents;
            $locked->save();
            $this->balance_cents = $balanceBefore + $amountCents;

            return $this->transactions()->create([
                'tenant_id'            => $this->tenant_id,
                'festival_edition_id'  => $this->festival_edition_id,
                'customer_id'          => $this->customer_id,
                'transaction_type'     => 'topup',
                'amount_cents'         => $amountCents,
                'balance_before_cents' => $balanceBefore,
                'balance_after_cents'  => $balanceBefore + $amountCents,
                'currency'             => $this->currency,
                'payment_method'       => $paymentMethod,
                'operator'             => $operator,
            ]);
        });
    }

    private function legacyCharge(int $amountCents, ?string $vendorName, ?string $vendorLocation, ?string $description, ?int $vendorId, ?int $posDeviceId): bool|WristbandTransaction
    {
        return DB::transaction(function () use ($amountCents, $vendorName, $vendorLocation, $description, $vendorId, $posDeviceId) {
            $locked = self::lockForUpdate()->find($this->id);

            if ($locked->balance_cents < $amountCents) {
                return false;
            }

            $balanceBefore = $locked->balance_cents;
            $locked->balance_cents = $balanceBefore - $amountCents;
            $locked->save();
            $this->balance_cents = $balanceBefore - $amountCents;

            return $this->transactions()->create([
                'tenant_id'            => $this->tenant_id,
                'festival_edition_id'  => $this->festival_edition_id,
                'customer_id'          => $this->customer_id,
                'transaction_type'     => 'payment',
                'amount_cents'         => $amountCents,
                'balance_before_cents' => $balanceBefore,
                'balance_after_cents'  => $balanceBefore - $amountCents,
                'currency'             => $this->currency,
                'vendor_name'          => $vendorName,
                'vendor_location'      => $vendorLocation,
                'vendor_id'            => $vendorId,
                'vendor_pos_device_id' => $posDeviceId,
                'description'          => $description,
            ]);
        });
    }

    private function legacyRefund(int $amountCents, ?string $description, ?string $operator): WristbandTransaction
    {
        return DB::transaction(function () use ($amountCents, $description, $operator) {
            $locked = self::lockForUpdate()->find($this->id);
            $balanceBefore = $locked->balance_cents;

            $totalCharged = $locked->transactions()
                ->where('transaction_type', 'payment')
                ->sum('amount_cents');
            $totalRefunded = $locked->transactions()
                ->where('transaction_type', 'refund')
                ->sum('amount_cents');
            $maxRefundable = $totalCharged - $totalRefunded;

            if ($amountCents > $maxRefundable) {
                throw new \InvalidArgumentException(
                    "Refund amount ({$amountCents}) exceeds maximum refundable ({$maxRefundable})"
                );
            }

            $locked->balance_cents = $balanceBefore + $amountCents;
            $locked->save();
            $this->balance_cents = $balanceBefore + $amountCents;

            return $this->transactions()->create([
                'tenant_id'            => $this->tenant_id,
                'festival_edition_id'  => $this->festival_edition_id,
                'customer_id'          => $this->customer_id,
                'transaction_type'     => 'refund',
                'amount_cents'         => $amountCents,
                'balance_before_cents' => $balanceBefore,
                'balance_after_cents'  => $balanceBefore + $amountCents,
                'currency'             => $this->currency,
                'description'          => $description,
                'operator'             => $operator,
            ]);
        });
    }

    private function legacyTransferTo(Wristband $target, ?string $operator): WristbandTransaction
    {
        if ($this->tenant_id !== $target->tenant_id) {
            throw new \InvalidArgumentException('Cannot transfer between wristbands of different tenants.');
        }
        if ($this->festival_edition_id !== $target->festival_edition_id) {
            throw new \InvalidArgumentException('Cannot transfer between wristbands of different festival editions.');
        }

        return DB::transaction(function () use ($target, $operator) {
            $source = self::lockForUpdate()->find($this->id);
            $dest   = self::lockForUpdate()->find($target->id);

            $amountCents  = $source->balance_cents;
            $sourceBefore = $source->balance_cents;
            $destBefore   = $dest->balance_cents;

            $source->balance_cents = 0;
            $source->save();
            $dest->balance_cents = $destBefore + $amountCents;
            $dest->save();

            $this->balance_cents   = 0;
            $target->balance_cents = $destBefore + $amountCents;

            $this->transactions()->create([
                'tenant_id'            => $this->tenant_id,
                'festival_edition_id'  => $this->festival_edition_id,
                'customer_id'          => $this->customer_id,
                'transaction_type'     => 'transfer_out',
                'amount_cents'         => $amountCents,
                'balance_before_cents' => $sourceBefore,
                'balance_after_cents'  => 0,
                'currency'             => $this->currency,
                'related_wristband_id' => $target->id,
                'operator'             => $operator,
                'description'          => "Transfer to wristband {$target->uid}",
            ]);

            return $target->transactions()->create([
                'tenant_id'            => $target->tenant_id,
                'festival_edition_id'  => $target->festival_edition_id,
                'customer_id'          => $target->customer_id,
                'transaction_type'     => 'transfer_in',
                'amount_cents'         => $amountCents,
                'balance_before_cents' => $destBefore,
                'balance_after_cents'  => $destBefore + $amountCents,
                'currency'             => $target->currency,
                'related_wristband_id' => $this->id,
                'operator'             => $operator,
                'description'          => "Transfer from wristband {$this->uid}",
            ]);
        });
    }

    private function legacyCashout(?string $operator): WristbandTransaction
    {
        return DB::transaction(function () use ($operator) {
            $locked = self::lockForUpdate()->find($this->id);
            $balanceBefore = $locked->balance_cents;

            $locked->balance_cents = 0;
            $locked->save();
            $this->balance_cents = 0;

            return $this->transactions()->create([
                'tenant_id'            => $this->tenant_id,
                'festival_edition_id'  => $this->festival_edition_id,
                'customer_id'          => $this->customer_id,
                'transaction_type'     => 'cashout',
                'amount_cents'         => $balanceBefore,
                'balance_before_cents' => $balanceBefore,
                'balance_after_cents'  => 0,
                'currency'             => $this->currency,
                'description'          => 'Cashout — end of festival',
                'operator'             => $operator,
            ]);
        });
    }

    // ── Access control ──

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
