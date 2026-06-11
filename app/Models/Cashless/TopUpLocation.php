<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\WristbandTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopUpLocation extends Model
{
    protected $table = 'topup_locations';

    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'name',
        'location_code',
        'coordinates',
        'zone',
        'is_active',
        'operating_hours',
        'meta',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'operating_hours'  => 'array',
        'meta'             => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WristbandTransaction::class, 'topup_location_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
