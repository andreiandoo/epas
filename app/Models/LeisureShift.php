<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeisureShift extends Model
{
    protected $fillable = [
        'marketplace_organizer_id',
        'event_id',
        'team_member_id',
        'start_at',
        'end_at',
        'role',
        'gate',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizerTeamMember::class, 'team_member_id');
    }
}
