<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorFinanceSummary extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'vendor_id', 'period_date',
        'gross_sales_cents', 'net_sales_cents', 'commission_cents', 'fees_cents',
        'tax_collected_cents', 'sgr_collected_cents', 'tips_cents',
        'vendor_payout_cents', 'transactions_count', 'meta',
    ];

    protected $casts = [
        'period_date'          => 'date',
        'gross_sales_cents'    => 'integer',
        'net_sales_cents'      => 'integer',
        'commission_cents'     => 'integer',
        'fees_cents'           => 'integer',
        'tax_collected_cents'  => 'integer',
        'sgr_collected_cents'  => 'integer',
        'tips_cents'           => 'integer',
        'vendor_payout_cents'  => 'integer',
        'transactions_count'   => 'integer',
        'meta'                 => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }

    public function scopeForEdition($query, int $id) { return $query->where('festival_edition_id', $id); }
    public function scopeForVendor($query, int $id) { return $query->where('vendor_id', $id); }
}
