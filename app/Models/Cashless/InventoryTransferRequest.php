<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferRequest extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'supplier_product_id', 'quantity', 'unit_measure',
        'from_type', 'from_vendor_id', 'from_stand_id',
        'to_type', 'to_vendor_id', 'to_stand_id',
        'status', 'requested_by', 'requested_at', 'accepted_by', 'accepted_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'expires_at',
        'inventory_movement_id', 'notes', 'meta',
    ];

    protected $casts = [
        'quantity'      => 'decimal:3',
        'requested_at'  => 'datetime',
        'accepted_at'   => 'datetime',
        'rejected_at'   => 'datetime',
        'expires_at'    => 'datetime',
        'meta'          => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function supplierProduct(): BelongsTo { return $this->belongsTo(SupplierProduct::class); }
    public function fromVendor(): BelongsTo { return $this->belongsTo(Vendor::class, 'from_vendor_id'); }
    public function fromStand(): BelongsTo { return $this->belongsTo(VendorStand::class, 'from_stand_id'); }
    public function toVendor(): BelongsTo { return $this->belongsTo(Vendor::class, 'to_vendor_id'); }
    public function toStand(): BelongsTo { return $this->belongsTo(VendorStand::class, 'to_stand_id'); }
    public function movement(): BelongsTo { return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id'); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isExpired(): bool { return $this->expires_at && now()->gt($this->expires_at) && $this->isPending(); }

    public function scopePending($query) { return $query->where('status', 'pending'); }
}
