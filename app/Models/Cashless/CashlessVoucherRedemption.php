<?php

namespace App\Models\Cashless;

use App\Models\Customer;
use App\Models\WristbandTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessVoucherRedemption extends Model
{
    protected $fillable = [
        'cashless_voucher_id',
        'cashless_account_id',
        'customer_id',
        'amount_cents',
        'wristband_transaction_id',
        'redeemed_at',
        'meta',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'redeemed_at'  => 'datetime',
        'meta'         => 'array',
    ];

    // ── Relationships ──

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(CashlessVoucher::class, 'cashless_voucher_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashlessAccount::class, 'cashless_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function wristbandTransaction(): BelongsTo
    {
        return $this->belongsTo(WristbandTransaction::class);
    }
}
