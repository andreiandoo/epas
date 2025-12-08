<?php

namespace App\Models\Integrations\TikTokAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TikTokAdsEventConfig extends Model
{
    protected $fillable = [
        'connection_id',
        'event_name',
        'is_enabled',
        'trigger_on',
        'content_mapping',
        'user_data_mapping',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'content_mapping' => 'array',
        'user_data_mapping' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TikTokAdsConnection::class, 'connection_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForTrigger($query, string $trigger)
    {
        return $query->where('trigger_on', $trigger);
    }
}
