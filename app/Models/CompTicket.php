<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CompTicket extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'performance_id',
        'issued_by',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'recipient_organization',
        'category',
        'quantity',
        'seat_labels',
        'section_preference',
        'status',
        'access_code',
        'claimed_at',
        'expires_at',
        'internal_notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'quantity' => 'integer',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CompTicket $comp) {
            if (empty($comp->access_code)) {
                $comp->access_code = Str::upper(Str::random(8));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Mark as claimed by the recipient.
     */
    public function claim(): bool
    {
        if ($this->status !== 'issued' && $this->status !== 'sent') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            $this->update(['status' => 'expired']);
            return false;
        }

        return $this->update([
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);
    }

    /**
     * Mark as used (at the door).
     */
    public function markUsed(): bool
    {
        return $this->update(['status' => 'used']);
    }

    /**
     * Revoke this comp ticket.
     */
    public function revoke(): bool
    {
        return $this->update(['status' => 'revoked']);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['issued', 'sent', 'claimed']);
    }
}
