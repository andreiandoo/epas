<?php

namespace App\Models\Leisure;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One legal entity (CIF/CUI) under a tenant from which invoices can be issued.
 * A tenant may have N registries; products are routed to one via
 * TicketType.tenant_tax_registry_id.
 */
class TenantTaxRegistry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'company_name', 'cui', 'reg_com', 'vat_payer', 'vat_number',
        'country', 'state', 'city', 'postal_code', 'address',
        'bank_name', 'bank_account',
        'invoice_series', 'invoice_next_number',
        'is_default', 'is_active', 'meta',
    ];

    protected $casts = [
        'vat_payer' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'invoice_next_number' => 'integer',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function nextInvoiceNumber(): string
    {
        $number = $this->invoice_next_number;
        $this->increment('invoice_next_number');
        $series = $this->invoice_series ?: 'INV';
        return sprintf('%s-%06d', $series, $number);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeDefault($q)
    {
        return $q->where('is_default', true);
    }
}
