<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalSponsor extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'slug',
        'tier',
        'description',
        'logo_url',
        'website_url',
        'placements',
        'sponsored_stage_ids',
        'sponsored_day_ids',
        'contract_value_cents',
        'currency',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'placements'           => 'array',
        'sponsored_stage_ids'  => 'array',
        'sponsored_day_ids'    => 'array',
        'contract_value_cents' => 'integer',
        'meta'                 => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getContractValueAttribute(): float
    {
        return ($this->contract_value_cents ?? 0) / 100;
    }

    public static function tierLabels(): array
    {
        return [
            'title'     => 'Title Sponsor',
            'platinum'  => 'Platinum',
            'gold'      => 'Gold',
            'silver'    => 'Silver',
            'bronze'    => 'Bronze',
            'media'     => 'Media Partner',
            'community' => 'Community Partner',
        ];
    }

    public static function tierSortOrder(): array
    {
        return [
            'title'     => 1,
            'platinum'  => 2,
            'gold'      => 3,
            'silver'    => 4,
            'bronze'    => 5,
            'media'     => 6,
            'community' => 7,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("FIELD(tier, 'title','platinum','gold','silver','bronze','media','community')")
            ->orderBy('sort_order');
    }
}
