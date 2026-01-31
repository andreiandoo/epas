<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketplaceTicketTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_client_id',
        'ticket_id',
        'from_customer_id',
        'from_email',
        'from_name',
        'to_email',
        'to_name',
        'to_customer_id',
        'token',
        'status',
        'message',
        'expires_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (empty($transfer->token)) {
                $transfer->token = Str::random(64);
            }
            if (empty($transfer->expires_at)) {
                $transfer->expires_at = now()->addDays(7);
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function fromCustomer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'from_customer_id');
    }

    public function toCustomer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'to_customer_id');
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->isPending() && $this->expires_at->isPast());
    }

    public function canBeAccepted(): bool
    {
        return $this->isPending() && !$this->expires_at->isPast();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    // =========================================
    // Actions
    // =========================================

    public function accept(?MarketplaceCustomer $recipient = null): void
    {
        $this->update([
            'status' => 'accepted',
            'to_customer_id' => $recipient?->id,
            'accepted_at' => now(),
        ]);

        // Update ticket ownership
        $this->ticket->update([
            'customer_id' => $recipient?->customer_id ?? $this->ticket->customer_id,
            'marketplace_customer_id' => $recipient?->id,
            'holder_name' => $this->to_name,
            'holder_email' => $this->to_email,
        ]);
    }

    public function reject(): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    public function markExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }

    // =========================================
    // Helpers
    // =========================================

    public function getAcceptUrl(): string
    {
        $client = $this->marketplaceClient;
        $baseUrl = $client?->domain ? rtrim($client->domain, '/') : config('app.url');

        return $baseUrl . '/tickets/transfer/accept?token=' . $this->token;
    }
}
