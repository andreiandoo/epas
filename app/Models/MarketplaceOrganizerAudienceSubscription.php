<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrganizerAudienceSubscription extends Model
{
    protected $table = 'marketplace_organizer_audience_subscriptions';

    protected $fillable = [
        'marketplace_organizer_id',
        'audience_segment_id',
        'is_active',
        'meta_audience_id',
        'meta_audience_name',
        'last_synced_at',
        'last_sync_status',
        'last_sync_error',
        'member_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CustomerAudienceSegment::class, 'audience_segment_id');
    }
}
