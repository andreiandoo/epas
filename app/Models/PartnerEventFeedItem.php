<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One event as media partners see it. Maintained by App\Services\Partners\PartnerEventFeed.
 */
class PartnerEventFeedItem extends Model
{
    protected $table = 'partner_event_feed';

    protected $fillable = [
        'marketplace_client_id',
        'event_id',
        'status',
        'starts_at',
        'listed_until',
        'city_key',
        'category_id',
        'payload',
        'payload_hash',
        'changed_at',
        'removed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'starts_at' => 'datetime',
        'listed_until' => 'datetime',
        'changed_at' => 'datetime',
        'removed_at' => 'datetime',
    ];
}
