<?php

namespace App\Models\Cashless;

use App\Enums\AccountStatus;
use App\Models\Customer;
use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Wristband;
use App\Models\WristbandTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashlessAccount extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['balance_cents', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('cashless');
    }
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'customer_id',
        'wristband_id',
        'festival_pass_purchase_id',
        'account_number',
        'balance_cents',
        'total_topped_up_cents',
        'total_spent_cents',
        'total_cashed_out_cents',
        'currency',
        'status',
        'activated_at',
        'closed_at',
        'meta',
    ];

    protected $casts = [
        'balance_cents'           => 'integer',
        'total_topped_up_cents'   => 'integer',
        'total_spent_cents'       => 'integer',
        'total_cashed_out_cents'  => 'integer',
        'status'                  => AccountStatus::class,
        'activated_at'            => 'datetime',
        'closed_at'               => 'datetime',
        'meta'                    => 'array',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function wristband(): BelongsTo
    {
        return $this->belongsTo(Wristband::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WristbandTransaction::class, 'cashless_account_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(CashlessSale::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(CashlessSettings::class, 'festival_edition_id', 'festival_edition_id')
            ->where('tenant_id', $this->tenant_id);
    }

    public function voucherRedemptions(): HasMany
    {
        return $this->hasMany(CashlessVoucherRedemption::class);
    }

    // ── Helpers ──

    public function canTransact(): bool
    {
        return $this->status->canTransact();
    }

    public function hasSufficientBalance(int $amountCents): bool
    {
        return $this->balance_cents >= $amountCents;
    }

    public function getBalanceAttribute(): float
    {
        return $this->balance_cents / 100;
    }

    public static function generateAccountNumber(): string
    {
        do {
            $number = 'CA-' . strtoupper(bin2hex(random_bytes(5)));
        } while (static::where('account_number', $number)->exists());

        return $number;
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', AccountStatus::Active);
    }

    public function scopeForEdition($query, int $editionId)
    {
        return $query->where('festival_edition_id', $editionId);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('balance_cents', '>', 0);
    }
}
