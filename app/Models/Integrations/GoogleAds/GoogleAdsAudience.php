<?php

namespace App\Models\Integrations\GoogleAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsAudience extends Model
{
    protected $fillable = [
        'connection_id',
        'resource_name',
        'name',
        'description',
        'membership_status',
        'size_for_display',
        'size_for_search',
        'is_auto_sync',
        'last_synced_at',
    ];

    protected $casts = [
        'is_auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsConnection::class, 'connection_id');
    }

    public function isOpen(): bool
    {
        return $this->membership_status === 'OPEN';
    }

    public function scopeAutoSync($query)
    {
        return $query->where('is_auto_sync', true);
    }

    public function scopeForConnection($query, int $connectionId)
    {
        return $query->where('connection_id', $connectionId);
    }
}
