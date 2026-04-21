<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueOwnerNote extends Model
{
    public const TARGET_TICKET = 'ticket';
    public const TARGET_ORDER = 'order';
    public const TARGET_CUSTOMER = 'customer';

    public const TARGET_TYPES = [
        self::TARGET_TICKET,
        self::TARGET_ORDER,
        self::TARGET_CUSTOMER,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'target_type',
        'target_id',
        'note',
    ];

    protected $casts = [
        'target_id' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope: all notes attached to a specific ticket — includes notes targeted
     * directly at the ticket, at its order, or at its customer identity.
     * This is the canonical lookup for "what does the venue owner know about
     * this ticket?" and is reused by scan/detail responses.
     *
     * @param  int|null  $customerId  Normalized customer id (marketplace_customer preferred, core customer fallback).
     * @param  string    $customerType 'marketplace_customer' | 'customer' | 'customer' generic
     */
    public static function forTicketContext(int $tenantId, Ticket $ticket): \Illuminate\Database\Eloquent\Collection
    {
        $ticketId = (int) $ticket->id;
        $orderId = (int) ($ticket->order_id ?? 0);

        // Customer target: prefer marketplace_customer_id, fall back to customer_id.
        $order = $ticket->order ?: ($orderId ? Order::find($orderId) : null);
        $customerId = $order?->marketplace_customer_id ?? $order?->customer_id ?? null;

        $query = static::where('tenant_id', $tenantId)
            ->where(function ($q) use ($ticketId, $orderId, $customerId) {
                $q->where(function ($sq) use ($ticketId) {
                    $sq->where('target_type', self::TARGET_TICKET)->where('target_id', $ticketId);
                });
                if ($orderId > 0) {
                    $q->orWhere(function ($sq) use ($orderId) {
                        $sq->where('target_type', self::TARGET_ORDER)->where('target_id', $orderId);
                    });
                }
                if ($customerId) {
                    $q->orWhere(function ($sq) use ($customerId) {
                        $sq->where('target_type', self::TARGET_CUSTOMER)->where('target_id', $customerId);
                    });
                }
            })
            ->with('author:id,name')
            ->orderByDesc('created_at');

        return $query->get();
    }
}
