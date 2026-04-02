<?php

namespace App\Models;

use App\Models\Cashless\SupplierBrand;
use App\Models\Cashless\SupplierProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseSupplier extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'company_name',
        'cui',
        'reg_com',
        'fiscal_address',
        'county',
        'city',
        'country',
        'is_vat_payer',
        'contact_person',
        'phone',
        'email',
        'bank_name',
        'iban',
        'contract_number',
        'contract_start',
        'contract_end',
        'payment_terms_days',
        'status',
        'logo_url',
        'website',
        'meta',
    ];

    protected $casts = [
        'is_vat_payer'      => 'boolean',
        'contract_start'    => 'date',
        'contract_end'      => 'date',
        'payment_terms_days' => 'integer',
        'meta'              => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MerchandiseItem::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(SupplierBrand::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
