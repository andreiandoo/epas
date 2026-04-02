<?php

namespace App\Models\Cashless;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use App\Models\VendorPosDevice;
use App\Models\VendorSaleItem;
use App\Models\VendorShift;
use App\Models\WristbandTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashlessSale extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_cents', 'tip_cents'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('cashless');
    }
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'vendor_id',
        'cashless_account_id',
        'customer_id',
        'wristband_transaction_id',
        'vendor_employee_id',
        'vendor_pos_device_id',
        'vendor_shift_id',
        'sale_number',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'commission_cents',
        'tip_cents',
        'tip_percentage',
        'total_with_tip_cents',
        'currency',
        'items_count',
        'status',
        'sold_at',
        'meta',
    ];

    protected $casts = [
        'subtotal_cents'       => 'integer',
        'tax_cents'            => 'integer',
        'total_cents'          => 'integer',
        'commission_cents'     => 'integer',
        'tip_cents'            => 'integer',
        'tip_percentage'       => 'decimal:2',
        'total_with_tip_cents' => 'integer',
        'items_count'          => 'integer',
        'status'            => SaleStatus::class,
        'sold_at'           => 'datetime',
        'meta'              => 'array',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(VendorEmployee::class, 'vendor_employee_id');
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(VendorPosDevice::class, 'vendor_pos_device_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(VendorShift::class, 'vendor_shift_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorSaleItem::class, 'cashless_sale_id');
    }

    // ── Helpers ──

    public static function generateSaleNumber(): string
    {
        do {
            $number = 'SALE-' . strtoupper(bin2hex(random_bytes(4)));
        } while (static::where('sale_number', $number)->exists());

        return $number;
    }

    public function getTotalWithTipAttribute(): float
    {
        return ($this->total_cents + $this->tip_cents) / 100;
    }

    // ── Scopes ──

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeForDay($query, string $date)
    {
        return $query->whereDate('sold_at', $date);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', SaleStatus::Completed);
    }
}
