<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WaitlistEntry extends Model
{
    use HasFactory;

    protected $table = 'event_waitlist';

    protected $fillable = [
        'tenant_id', 'event_id', 'ticket_type_id', 'customer_id',
        'email', 'name', 'quantity', 'priority', 'status',
        'notified_at', 'expires_at', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'notified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    const STATUS_WAITING = 'waiting';
    const STATUS_NOTIFIED = 'notified';
    const STATUS_PURCHASED = 'purchased';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function ticketType(): BelongsTo { return $this->belongsTo(TicketType::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeForEvent($query, $eventId) { return $query->where('event_id', $eventId); }
    public function scopeWaiting($query) { return $query->where('status', self::STATUS_WAITING); }
    public function scopeByPriority($query) { return $query->orderBy('priority', 'desc')->orderBy('created_at', 'asc'); }

    public function isWaiting(): bool { return $this->status === self::STATUS_WAITING; }
    public function isExpired(): bool { return $this->expires_at && now()->isAfter($this->expires_at); }
}
