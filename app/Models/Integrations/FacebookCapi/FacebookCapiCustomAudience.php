<?php

namespace App\Models\Integrations\FacebookCapi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookCapiCustomAudience extends Model
{
    protected $table = 'facebook_capi_custom_audiences';

    protected $fillable = [
        'connection_id',
        'audience_id',
        'name',
        'description',
        'subtype',
        'data_source',
        'filters',
        'is_auto_sync',
        'approximate_count',
        'last_synced_at',
        'metadata',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_auto_sync' => 'boolean',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(FacebookCapiConnection::class, 'connection_id');
    }

    public function isCustomList(): bool
    {
        return $this->subtype === 'CUSTOM';
    }

    public function isWebsiteAudience(): bool
    {
        return $this->subtype === 'WEBSITE';
    }

    public function needsSync(): bool
    {
        if (!$this->is_auto_sync) {
            return false;
        }

        if (!$this->last_synced_at) {
            return true;
        }

        // Sync daily by default
        return $this->last_synced_at->diffInHours(now()) >= 24;
    }
}
