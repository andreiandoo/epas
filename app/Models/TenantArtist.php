<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantArtist extends Model
{
    use Translatable;

    public array $translatable = ['bio'];

    protected $fillable = [
        'tenant_id',
        'artist_id',
        'name',
        'slug',
        'role',
        'bio',
        'photo_url',
        'phone',
        'email',
        'contract_start',
        'contract_end',
        'is_resident',
        'meta',
        'status',
    ];

    protected $casts = [
        'bio' => 'array',
        'meta' => 'array',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'is_resident' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Optional link to global artist record.
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Events this tenant artist performs in (cast/distribution).
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_tenant_artist')
            ->withPivot(['role_in_event', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Repertoire pieces this artist performs in (permanent cast).
     */
    public function repertoire(): BelongsToMany
    {
        return $this->belongsToMany(Repertoire::class, 'repertoire_tenant_artist')
            ->withPivot(['role_name', 'role_type', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Riders (technical/hospitality) for this tenant artist.
     */
    public function riders(): HasMany
    {
        return $this->hasMany(ArtistRider::class);
    }

    /**
     * Check if the artist's contract is currently active.
     */
    public function hasActiveContract(): bool
    {
        if (!$this->contract_start) {
            return $this->status === 'active';
        }

        $now = now()->toDateString();
        $started = $this->contract_start <= $now;
        $notEnded = !$this->contract_end || $this->contract_end >= $now;

        return $started && $notEnded;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeResident($query)
    {
        return $query->where('is_resident', true);
    }
}
