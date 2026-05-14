<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceShareLink extends Model
{
    protected $fillable = [
        'code',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'name',
        'event_ids',
        'is_active',
        'has_password',
        'password_hash',
        'show_participants',
        'show_revenue',
        'ticket_data',
        'participants_data',
        'ticket_data_updated_at',
        'access_count',
        'last_accessed_at',
    ];

    protected $casts = [
        'event_ids' => 'array',
        'ticket_data' => 'array',
        'participants_data' => 'array',
        'is_active' => 'boolean',
        'has_password' => 'boolean',
        'show_participants' => 'boolean',
        'show_revenue' => 'boolean',
        'ticket_data_updated_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
    }
}
