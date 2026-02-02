<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceTaxRegistry extends Model
{
    protected $table = 'marketplace_tax_registries';

    protected $fillable = [
        'marketplace_client_id',
        'country',
        'county',
        'city',
        'name',
        'subname',
        'address',
        'phone',
        'email',
        'cif',
        'iban',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForMarketplace($query, $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Get display name with subname
     */
    public function getFullNameAttribute(): string
    {
        if ($this->subname) {
            return "{$this->name} - {$this->subname}";
        }
        return $this->name;
    }

    /**
     * Get full location string
     */
    public function getLocationAttribute(): string
    {
        $parts = array_filter([
            $this->city,
            $this->county,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get all fields as array for template variables
     */
    public function toTemplateVariables(): array
    {
        return [
            'tax_registry_country' => $this->country ?? '',
            'tax_registry_county' => $this->county ?? '',
            'tax_registry_city' => $this->city ?? '',
            'tax_registry_name' => $this->name ?? '',
            'tax_registry_subname' => $this->subname ?? '',
            'tax_registry_address' => $this->address ?? '',
            'tax_registry_phone' => $this->phone ?? '',
            'tax_registry_email' => $this->email ?? '',
            'tax_registry_cif' => $this->cif ?? '',
            'tax_registry_iban' => $this->iban ?? '',
        ];
    }
}
