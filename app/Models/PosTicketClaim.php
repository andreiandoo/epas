<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosTicketClaim extends Model
{
    protected $fillable = [
        'tenant_id',
        'order_id',
        'token',
        'status',
        'expires_at',
        'event_name',
        'event_date',
        'venue_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'gender',
        'date_of_birth',
        'claimed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'date_of_birth' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($claim) {
            if (empty($claim->token)) {
                $claim->token = Str::random(64);
            }
            if (empty($claim->expires_at)) {
                $claim->expires_at = now()->addHours(24);
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isClaimed(): bool
    {
        return $this->status === 'claimed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->isPending() && $this->expires_at->isPast());
    }

    public function isAvailable(): bool
    {
        return $this->isPending() && !$this->expires_at->isPast();
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'expired')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'pending')->where('expires_at', '<=', now());
              });
        });
    }

    // =========================================
    // Helpers
    // =========================================

    public function getClaimUrl(): string
    {
        return url('/claim/' . $this->token);
    }

    public function markClaimed(): void
    {
        $this->update([
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);
    }

    public function markExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }
}
