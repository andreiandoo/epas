<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistRider extends Model
{
    use Translatable;

    public array $translatable = ['title', 'content'];

    protected $fillable = [
        'tenant_id',
        'artist_id',
        'tenant_artist_id',
        'agency_artist_id',
        'type',
        'title',
        'content',
        'stage_plot_url',
        'input_list',
        'backline_requirements',
        'monitor_requirements',
        'catering',
        'accommodation',
        'transport',
        'attachments',
        'version',
        'is_active',
    ];

    protected $casts = [
        'title' => 'array',
        'content' => 'array',
        'stage_plot_url' => 'array',
        'input_list' => 'array',
        'backline_requirements' => 'array',
        'monitor_requirements' => 'array',
        'catering' => 'array',
        'accommodation' => 'array',
        'transport' => 'array',
        'attachments' => 'array',
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function tenantArtist(): BelongsTo
    {
        return $this->belongsTo(TenantArtist::class);
    }

    public function agencyArtist(): BelongsTo
    {
        return $this->belongsTo(AgencyArtist::class);
    }

    /**
     * Get the total channel count from the input list.
     */
    public function getTotalChannelsAttribute(): int
    {
        return is_array($this->input_list) ? count($this->input_list) : 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTechnical($query)
    {
        return $query->where('type', 'technical');
    }

    public function scopeHospitality($query)
    {
        return $query->where('type', 'hospitality');
    }
}
