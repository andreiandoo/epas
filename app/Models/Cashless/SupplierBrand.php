<?php

namespace App\Models\Cashless;

use App\Models\MerchandiseSupplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBrand extends Model
{
    protected $fillable = [
        'tenant_id',
        'merchandise_supplier_id',
        'name',
        'slug',
        'logo_url',
        'category',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta'      => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(MerchandiseSupplier::class, 'merchandise_supplier_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
