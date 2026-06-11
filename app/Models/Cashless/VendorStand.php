<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\VendorPosDevice;
use App\Models\VendorProduct;
use App\Models\VendorShift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorStand extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'vendor_id', 'name', 'slug',
        'location', 'location_coordinates', 'zone', 'fiscal_device_id',
        'status', 'operating_hours', 'capacity', 'contact_phone', 'meta',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'capacity'        => 'integer',
        'meta'            => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }

    public function posDevices(): HasMany { return $this->hasMany(VendorPosDevice::class); }
    public function shifts(): HasMany { return $this->hasMany(VendorShift::class); }
    public function sales(): HasMany { return $this->hasMany(CashlessSale::class); }
    public function standProducts(): HasMany { return $this->hasMany(VendorStandProduct::class); }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(VendorProduct::class, 'vendor_stand_products')
            ->withPivot('is_available', 'override_price_cents', 'sort_order')
            ->withTimestamps();
    }

    public function inventoryStocks(): HasMany { return $this->hasMany(InventoryStock::class); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function scopeActive($query) { return $query->where('status', 'active'); }
}
