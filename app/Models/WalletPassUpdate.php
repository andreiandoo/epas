<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletPassUpdate extends Model
{
    use HasFactory;

    protected $table = 'wallet_pass_updates';

    protected $fillable = [
        'pass_id',
        'update_type',
        'changes',
        'pushed',
        'pushed_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'pushed' => 'boolean',
        'pushed_at' => 'datetime',
    ];

    // Update types
    const TYPE_EVENT_CHANGED = 'event_changed';
    const TYPE_TICKET_CANCELLED = 'ticket_cancelled';
    const TYPE_VENUE_CHANGED = 'venue_changed';
    const TYPE_TIME_CHANGED = 'time_changed';
    const TYPE_GATE_CHANGED = 'gate_changed';

    // Relationships
    public function pass(): BelongsTo
    {
        return $this->belongsTo(WalletPass::class, 'pass_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('pushed', false);
    }

    public function scopePushed($query)
    {
        return $query->where('pushed', true);
    }

    // Helper methods
    public function markPushed(): void
    {
        $this->update([
            'pushed' => true,
            'pushed_at' => now(),
        ]);
    }
}
