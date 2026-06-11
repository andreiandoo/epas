<?php

namespace App\Models\Integrations\TikTokAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TikTokAdsAudience extends Model
{
    protected $fillable = [
        'connection_id',
        'audience_id',
        'name',
        'audience_type',
        'size',
        'is_auto_sync',
        'last_synced_at',
    ];

    protected $casts = [
        'is_auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TikTokAdsConnection::class, 'connection_id');
    }

    public function scopeAutoSync($query)
    {
        return $query->where('is_auto_sync', true);
    }
}
