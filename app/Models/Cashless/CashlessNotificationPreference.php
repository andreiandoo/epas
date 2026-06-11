<?php

namespace App\Models\Cashless;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessNotificationPreference extends Model
{
    protected $fillable = [
        'customer_id', 'cashless_account_id',
        'push_enabled', 'email_enabled', 'sms_enabled',
        'notify_on_purchase', 'notify_on_topup', 'notify_on_cashout',
        'notify_on_transfer', 'notify_low_balance', 'daily_summary',
        'profiling_opt_out', 'meta',
    ];

    protected $casts = [
        'push_enabled'        => 'boolean',
        'email_enabled'       => 'boolean',
        'sms_enabled'         => 'boolean',
        'notify_on_purchase'  => 'boolean',
        'notify_on_topup'     => 'boolean',
        'notify_on_cashout'   => 'boolean',
        'notify_on_transfer'  => 'boolean',
        'notify_low_balance'  => 'boolean',
        'daily_summary'       => 'boolean',
        'profiling_opt_out'   => 'boolean',
        'meta'                => 'array',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function account(): BelongsTo { return $this->belongsTo(CashlessAccount::class, 'cashless_account_id'); }
}
