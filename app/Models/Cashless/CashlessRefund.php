<?php

namespace App\Models\Cashless;

use App\Models\Customer;
use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use App\Models\WristbandTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashlessRefund extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_refund_cents', 'rejection_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('cashless');
    }
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'cashless_sale_id', 'cashless_account_id',
        'customer_id', 'vendor_id', 'refund_type', 'status',
        'requested_by_employee_id', 'approved_by_employee_id',
        'requested_at', 'approved_at', 'processed_at', 'rejected_at',
        'rejection_reason', 'total_refund_cents', 'currency',
        'wristband_transaction_id', 'reason', 'items', 'meta',
    ];

    protected $casts = [
        'requested_at'       => 'datetime',
        'approved_at'        => 'datetime',
        'processed_at'       => 'datetime',
        'rejected_at'        => 'datetime',
        'total_refund_cents' => 'integer',
        'items'              => 'array',
        'meta'               => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function sale(): BelongsTo { return $this->belongsTo(CashlessSale::class, 'cashless_sale_id'); }
    public function account(): BelongsTo { return $this->belongsTo(CashlessAccount::class, 'cashless_account_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(VendorEmployee::class, 'requested_by_employee_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(VendorEmployee::class, 'approved_by_employee_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(WristbandTransaction::class, 'wristband_transaction_id'); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isProcessed(): bool { return $this->status === 'processed'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function scopePending($query) { return $query->where('status', 'pending'); }
    public function scopeForVendor($query, int $id) { return $query->where('vendor_id', $id); }
}
