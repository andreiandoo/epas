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
        'commune',
        'name',
        'subname',
        'address',
        'directions',
        'phone',
        'email',
        'email2',
        'website_url',
        'cif',
        'iban',
        'siruta_code',
        'coat_of_arms',
        'tax_rate',
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
     * Find the active fiscal directorate (tax registry) whose location loosely
     * matches a venue's country/county/city. Shared by the event form's venue
     * afterStateUpdated handler and the event Duplicate action so both derive
     * the registry the exact same way. Returns null when nothing matches or the
     * venue has neither a city nor a county to match on.
     */
    public static function matchForVenue(?Venue $venue, ?int $marketplaceClientId): ?self
    {
        if (! $venue) {
            return null;
        }

        $normalize = fn ($s) => strtolower(trim(\Illuminate\Support\Str::ascii($s ?? '')));
        $vCountry = $normalize($venue->country);
        $vCounty = $normalize($venue->state);
        $vCity = $normalize($venue->city);

        if (! $vCity && ! $vCounty) {
            return null;
        }

        return static::where('marketplace_client_id', $marketplaceClientId)
            ->where('is_active', true)
            ->get()
            ->first(function ($r) use ($normalize, $vCountry, $vCounty, $vCity) {
                $rCountry = $normalize($r->country);
                $rCounty = $normalize($r->county);
                $rCity = $normalize($r->city);

                $countryMatch = ! $rCountry || ! $vCountry || str_contains($rCountry, $vCountry) || str_contains($vCountry, $rCountry);
                $countyMatch = ! $rCounty || ! $vCounty || str_contains($rCounty, $vCounty) || str_contains($vCounty, $rCounty);
                $cityMatch = ! $rCity || ! $vCity || str_contains($rCity, $vCity) || str_contains($vCity, $rCity);

                return $countryMatch && $countyMatch && $cityMatch;
            });
    }

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
            $this->commune,
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
        // Build coat of arms as complete <img> tag with base64 (DomPDF compatible)
        $coatOfArmsHtml = '';
        if ($this->coat_of_arms && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->coat_of_arms)) {
            $content = \Illuminate\Support\Facades\Storage::disk('public')->get($this->coat_of_arms);
            $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($this->coat_of_arms) ?: 'image/png';
            $b64 = 'data:' . $mime . ';base64,' . base64_encode($content);
            $coatOfArmsHtml = '<img src="' . $b64 . '" alt="Stema" style="max-height:80px;max-width:80px;display:block;" />';
        }

        return [
            'tax_registry_country' => $this->country ?? '',
            'tax_registry_county' => $this->county ?? '',
            'tax_registry_city' => $this->city ?? '',
            'tax_registry_commune' => $this->commune ?? '',
            'tax_registry_name' => $this->name ?? '',
            'tax_registry_subname' => $this->subname ?? '',
            'tax_registry_address' => $this->address ?? '',
            'tax_registry_directions' => $this->directions ?? '',
            'tax_registry_phone' => $this->phone ?? '',
            'tax_registry_email' => $this->email ?? '',
            'tax_registry_email2' => $this->email2 ?? '',
            'tax_registry_website_url' => $this->website_url ?? '',
            'tax_registry_cif' => $this->cif ?? '',
            'tax_registry_iban' => $this->iban ?? '',
            'tax_registry_siruta_code' => $this->siruta_code ?? '',
            'tax_registry_coat_of_arms' => $coatOfArmsHtml,
            'tax_registry_tax_rate' => $this->tax_rate !== null ? rtrim(rtrim(number_format((float) $this->tax_rate, 2, '.', ''), '0'), '.') . '%' : '',
        ];
    }
}
