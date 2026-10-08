<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Șablonul unui document fiscal al unui tenant (copiat dintr-un șablon de marketplace și apoi editabil).
 * Variabilele {{ … }} sunt aceleași ca în MarketplaceTaxTemplate — vezi TenantFiscalDocuments.
 */
class TenantTaxTemplate extends Model
{
    public const TYPES = [
        'cerere_avizare'      => 'Cerere de vizare a biletelor',
        'declaratie_impozite' => 'Declarație impozit pe spectacole',
        'pv_distrugere'       => 'Proces-verbal de distrugere a biletelor',
    ];

    protected $fillable = [
        'tenant_id', 'type', 'name', 'html_content', 'html_content_page_2',
        'page_orientation', 'general_tax_ids', 'source_template_id', 'is_active',
    ];

    protected $casts = [
        'general_tax_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
