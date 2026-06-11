<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistSetNotification extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'festival_lineup_slot_id',
        'festival_schedule_favorite_id',
        'channel',
        'status',
        'scheduled_at',
        'sent_at',
        'failure_reason',
        'meta',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
        'meta'         => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineupSlot(): BelongsTo
    {
        return $this->belongsTo(FestivalLineupSlot::class, 'festival_lineup_slot_id');
    }

    public function favorite(): BelongsTo
    {
        return $this->belongsTo(FestivalScheduleFavorite::class, 'festival_schedule_favorite_id');
    }

    public function markSent(): void
    {
        $this->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status'         => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDue($query)
    {
        return $query->pending()->where('scheduled_at', '<=', now());
    }
}
