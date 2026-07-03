<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeisureCashierSession extends Model
{
    use HasFactory;

    protected $table = 'leisure_cashier_sessions';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'event_id',
        'team_member_id',
        'opened_at',
        'closed_at',
        'opened_label',
        'closing_snapshot',
        'opening_notes',
        'closing_notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'closing_snapshot' => 'array',
    ];

    public function isOpen(): bool
    {
        return $this->closed_at === null;
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
