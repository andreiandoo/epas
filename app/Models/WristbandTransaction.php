<?php

namespace App\Models;

use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\TopUpLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WristbandTransaction extends Model
{
    protected $fillable = [
        'wristband_id',
        'tenant_id',
        'festival_edition_id',
        'customer_id',
        'transaction_type',
        'amount_cents',
        'balance_before_cents',
        'balance_after_cents',
        'currency',
        'description',
        'vendor_name',
        'vendor_location',
        'vendor_id',
        'vendor_pos_device_id',
        'payment_method',
        'reference',
        'related_wristband_id',
        'operator',
        'meta',
        'sync_source',
        'offline_ref',
        'offline_transacted_at',
        'is_reconciled',
        // Cashless fields
        'channel',
        'topup_method',
        'topup_location_id',
        'cashless_account_id',
        'balance_snapshot_cents',
        'customer_email',
        'customer_name',
        'cashout_channel',
        'cashout_method',
        'cashout_reference',
        'cashout_processed_at',
        'cashout_status',
    ];

    protected $casts = [
        'amount_cents'           => 'integer',
        'balance_before_cents'   => 'integer',
        'balance_after_cents'    => 'integer',
        'balance_snapshot_cents' => 'integer',
        'meta'                   => 'array',
        'offline_transacted_at'  => 'datetime',
        'cashout_processed_at'   => 'datetime',
        'is_reconciled'          => 'boolean',
    ];

    public function wristband(): BelongsTo
    {
        return $this->belongsTo(Wristband::class);
    }

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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(VendorPosDevice::class, 'vendor_pos_device_id');
    }

    public function relatedWristband(): BelongsTo
    {
        return $this->belongsTo(Wristband::class, 'related_wristband_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(VendorSaleItem::class);
    }

    public function cashlessAccount(): BelongsTo
    {
        return $this->belongsTo(CashlessAccount::class);
    }

    public function topupLocation(): BelongsTo
    {
        return $this->belongsTo(TopUpLocation::class, 'topup_location_id');
    }

    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    public function isDebit(): bool
    {
        return in_array($this->transaction_type, ['payment', 'transfer_out', 'cashout']);
    }

    public function isCredit(): bool
    {
        return in_array($this->transaction_type, ['topup', 'refund', 'transfer_in', 'correction', 'voucher_credit', 'promotional_credit', 'compensation_credit']);
    }

    public function scopePayments($query)
    {
        return $query->where('transaction_type', 'payment');
    }

    public function scopeTopups($query)
    {
        return $query->where('transaction_type', 'topup');
    }

    public function scopeByVendor($query, string $vendorName)
    {
        return $query->where('vendor_name', $vendorName);
    }

    public function scopeByVendorId($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeForDay($query, string $date)
    {
        return $query->whereDate('created_at', $date);
    }

    public function scopeForEdition($query, int $editionId)
    {
        return $query->where('festival_edition_id', $editionId);
    }

    public function scopeOffline($query)
    {
        return $query->where('sync_source', 'offline_sync');
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('is_reconciled', false);
    }
}
