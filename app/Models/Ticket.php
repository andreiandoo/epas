<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'ticket_type_id',
        'performance_id',
        'event_id',
        'tenant_id',
        'marketplace_client_id',
        'marketplace_customer_id',
        'marketplace_event_id',
        'marketplace_ticket_type_id',
        'code',
        'barcode',
        'status',
        'seat_label',
        'price',
        'attendee_name',
        'attendee_email',
        'meta',
        'is_cancelled',
        'cancelled_at',
        'cancellation_reason',
        'refund_request_id',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function marketplaceTicketType(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTicketType::class);
    }

    public function marketplaceEvent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class);
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function event()
    {
        return $this->hasOneThrough(
            \App\Models\Event::class,
            \App\Models\TicketType::class,
            'id',            // TicketType key
            'id',            // Event key
            'ticket_type_id',// FK pe tickets
            'event_id'       // FK pe ticket_types
        );
    }

    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRefundRequest::class, 'refund_request_id');
    }

    /**
     * Resolve seat details from meta, EventSeat lookup, or seat_uid parsing.
     * Returns ['section_name' => ?, 'row_label' => ?, 'seat_number' => ?] or null.
     */
    public function getSeatDetails(): ?array
    {
        $meta = $this->meta ?? [];
        $seatUid = $meta['seat_uid'] ?? null;
        $section = $meta['section_name'] ?? null;
        $row = $meta['row_label'] ?? null;
        $seat = $meta['seat_number'] ?? null;

        // If meta has all fields, return them
        if ($section && $row && $seat) {
            return ['section_name' => $section, 'row_label' => $row, 'seat_number' => $seat];
        }

        // Try EventSeat lookup
        if ($seatUid) {
            $eventSeat = \App\Models\Seating\EventSeat::where('seat_uid', $seatUid)->first();
            if ($eventSeat) {
                $section = $section ?: $eventSeat->section_name;
                $row = $row ?: $eventSeat->row_label;
                $seat = $seat ?: $eventSeat->seat_label;
            }
        }

        // Last resort: parse seat_uid format "S{sectionId}-{rowLabel}-{seatNumber}"
        if ($seatUid && (!$row || !$seat)) {
            if (preg_match('/^S(\d+)-(.+)-(\d+)$/', $seatUid, $m)) {
                $row = $row ?: $m[2];
                $seat = $seat ?: $m[3];
                // Look up section name from SeatingSection
                if (!$section) {
                    $sectionModel = \App\Models\Seating\SeatingSection::find((int) $m[1]);
                    $section = $sectionModel?->name ?? null;
                }
            }
        }

        if (!$section && !$row && !$seat) {
            return null;
        }

        return ['section_name' => $section, 'row_label' => $row, 'seat_number' => $seat];
    }

    /**
     * Cancel this ticket
     */
    public function cancel(string $reason = null, int $refundRequestId = null): void
    {
        $this->update([
            'is_cancelled' => true,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'refund_request_id' => $refundRequestId,
            'status' => 'cancelled',
        ]);
    }
}
