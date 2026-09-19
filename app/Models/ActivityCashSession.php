<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Activities module: a cash desk session at an operator's location (POS), from
 * opening (cash in the drawer) to closing (cash counted).
 */
class ActivityCashSession extends Model
{
    protected $table = 'activity_cash_sessions';

    protected $fillable = [
        'marketplace_client_id', 'marketplace_organizer_id', 'location_id',
        'opened_by', 'opened_at', 'opening_cash_cents',
        'closed_by', 'closed_at', 'counted_cash_cents', 'notes',
    ];

    protected $casts = [
        'opened_at'          => 'datetime',
        'closed_at'          => 'datetime',
        'opening_cash_cents' => 'integer',
        'counted_cash_cents' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(ActivityLocation::class, 'location_id');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
