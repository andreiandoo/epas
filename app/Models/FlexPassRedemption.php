<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlexPassRedemption extends Model
{
    protected $fillable = [
        'flex_pass_purchase_id',
        'event_id',
        'performance_id',
        'ticket_id',
        'status',
        'redeemed_at',
        'cancelled_at',
        'cancelled_reason',
        'meta',
    ];

    protected $casts = [
        'redeemed_at'  => 'datetime',
        'cancelled_at' => 'datetime',
        'meta'         => 'array',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(FlexPassPurchase::class, 'flex_pass_purchase_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status'           => 'cancelled',
            'cancelled_at'     => now(),
            'cancelled_reason' => $reason,
        ]);

        $this->purchase->increment('entries_remaining');
        $this->purchase->decrement('entries_used');

        if ($this->purchase->status === 'fully_used') {
            $this->purchase->update(['status' => 'active']);
        }
    }
}
