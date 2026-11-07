<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O intrare la poarta intr-o zi anume. Abonamentele au cate una pe zi;
 * restul biletelor au una singura. Vezi App\Services\Checkin\TicketCheckin.
 */
class TicketDayCheckin extends Model
{
    protected $fillable = [
        'ticket_id',
        'event_id',
        'day',
        'checked_in_at',
        'checked_in_by',
        'checked_in_via',
    ];

    protected $casts = [
        'day' => 'date',
        'checked_in_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
