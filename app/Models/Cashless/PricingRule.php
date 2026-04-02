<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingRule extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'supplier_product_id', 'supplier_brand_id',
        'product_category', 'name', 'is_mandatory', 'final_price_cents', 'currency',
        'is_active', 'valid_from', 'valid_until', 'notes', 'meta',
    ];

    protected $casts = [
        'is_mandatory'      => 'boolean',
        'final_price_cents' => 'integer',
        'is_active'         => 'boolean',
        'valid_from'        => 'date',
        'valid_until'       => 'date',
        'meta'              => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function supplierProduct(): BelongsTo { return $this->belongsTo(SupplierProduct::class); }
    public function supplierBrand(): BelongsTo { return $this->belongsTo(SupplierBrand::class); }

    public function components(): HasMany
    {
        return $this->hasMany(PricingRuleComponent::class)->orderBy('sort_order');
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) return false;
        if ($this->valid_from && now()->lt($this->valid_from)) return false;
        if ($this->valid_until && now()->gt($this->valid_until)) return false;
        return true;
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeMandatory($query) { return $query->where('is_mandatory', true); }
    public function scopeForEdition($query, int $id) { return $query->where('festival_edition_id', $id); }
}
