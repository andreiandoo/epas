<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeisureResourceLock extends Model
{
    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'order_id',
        'order_item_id',
        'start_at',
        'end_at',
        'qty',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'qty' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Câte unități fizice rămân libere pentru un interval dat
     */
    public static function availableForInterval(int $ticketTypeId, int $totalCount, \Carbon\Carbon $start, \Carbon\Carbon $end): int
    {
        $usedQty = (int) static::query()
            ->where('ticket_type_id', $ticketTypeId)
            ->where('status', 'active')
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->sum('qty');
        return max(0, $totalCount - $usedQty);
    }
}
