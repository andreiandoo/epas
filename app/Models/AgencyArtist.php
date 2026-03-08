<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyArtist extends Model
{
    protected $fillable = [
        'tenant_id',
        'artist_id',
        'contract_type',
        'contract_start',
        'contract_end',
        'commission_rate',
        'territory',
        'notes',
        'status',
        'meta',
    ];

    protected $casts = [
        'territory' => 'array',
        'meta' => 'array',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'commission_rate' => 'decimal:2',
    ];

    /**
     * The agency (tenant) that represents this artist.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The global artist record.
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Check if the contract is currently active.
     */
    public function hasActiveContract(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (!$this->contract_start) {
            return true;
        }

        $now = now()->toDateString();
        $started = $this->contract_start <= $now;
        $notEnded = !$this->contract_end || $this->contract_end >= $now;

        return $started && $notEnded;
    }

    /**
     * Check if the contract covers a specific territory.
     */
    public function coversTerritory(string $countryCode): bool
    {
        if (empty($this->territory)) {
            return true; // No territory restriction = worldwide
        }

        return in_array(strtoupper($countryCode), array_map('strtoupper', $this->territory));
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExclusive($query)
    {
        return $query->where('contract_type', 'exclusive');
    }
}
