<?php

namespace App\Models\Integrations\LinkedInAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkedInAdsAudience extends Model
{
    protected $fillable = [
        'connection_id',
        'dmp_segment_id',
        'name',
        'audience_type',
        'matched_count',
        'is_auto_sync',
        'last_synced_at',
    ];

    protected $casts = [
        'is_auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(LinkedInAdsConnection::class, 'connection_id');
    }

    public function scopeAutoSync($query)
    {
        return $query->where('is_auto_sync', true);
    }
}
