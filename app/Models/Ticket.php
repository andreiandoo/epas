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
        'checked_in_at',
        'checked_in_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'checked_in_at' => 'datetime',
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

    public function refundItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MarketplaceRefundItem::class);
    }

    public function isRefunded(): bool
    {
        return ($this->refund_status ?? 'none') === 'refunded';
    }

    /**
     * Resolve the Event model through all available paths.
     * Marketplace tickets may have ticket_type_id=null, so we fall back to event_id.
     */
    public function resolveEvent(): ?Event
    {
        // 1. Try via TicketType relationship
        if ($this->ticket_type_id) {
            $event = $this->ticketType?->event;
            if ($event) {
                return $event;
            }
        }

        // 2. Fall back to direct event_id column
        if ($this->event_id) {
            return Event::find($this->event_id);
        }

        // 3. Try marketplace_event_id (same table as events)
        if ($this->marketplace_event_id) {
            return Event::find($this->marketplace_event_id);
        }

        return null;
    }

    /**
     * Get the ticket type name from either TicketType or MarketplaceTicketType.
     */
    public function resolveTicketTypeName(): string
    {
        if ($this->ticket_type_id) {
            $name = $this->ticketType?->name;
            if ($name) {
                return is_array($name) ? ($name['ro'] ?? $name['en'] ?? reset($name) ?: '') : $name;
            }
        }

        if ($this->marketplace_ticket_type_id) {
            $name = $this->marketplaceTicketType?->name;
            if ($name) {
                return is_array($name) ? ($name['ro'] ?? $name['en'] ?? reset($name) ?: '') : $name;
            }
        }

        return '';
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
     * Net per-ticket value (organizer's share — price minus included commission).
     *
     * Why this exists: tickets.price stores whatever the customer paid for
     * the line item, which can mean two different things depending on how
     * the order was placed:
     *   - For "added_on_top" / "on_top" online orders, the customer pays
     *     subtotal + commission. tickets.price = subtotal/qty = already net.
     *   - For "included" online orders, the customer pays subtotal flat;
     *     commission is carved out of the organizer's share. tickets.price
     *     = the sticker = net + commission baked in.
     *   - POS app orders (source=pos_app) are always included-style: the
     *     ticket price IS what the customer paid at the door, and
     *     commission is carved out of the organizer's payout.
     *
     * Per-ticket commission resolution priority:
     *   1. order.meta.commission_details[type_name].commission_amount / qty
     *      — exact amount written by online CheckoutController per type.
     *   2. Fallback: order.commission_rate as percentage of tickets.price
     *      — POS orders don't populate commission_details, so we derive
     *      from the stored order-level rate.
     *
     * Used by Vânzări and Participanți CSV exports to show the operator
     * the actual revenue per ticket (not the sticker), which is what
     * matters for accounting.
     */
    public function getNetPrice(): float
    {
        $price = (float) ($this->price ?? 0);
        if ($price <= 0) return 0.0;

        $order = $this->order;
        if (!$order) {
            // Invitations have no order; ticket.price is zero by design anyway.
            return $price;
        }

        $meta = is_array($order->meta) ? $order->meta : [];
        $source = $order->source ?? 'marketplace';
        $ticketType = $this->ticketType;
        $event = $ticketType?->event ?? $order->event ?? null;

        // Resolve commission mode per ticket type. Priority chain (mirrors
        // SalesBreakdownService so the export is consistent with what the
        // Sales tab shows):
        //   1. ticket_type.commission_mode (explicit override on the type)
        //   2. event.commission_mode
        //   3. organizer.default_commission_mode
        //   4. marketplace.commission_mode
        //   5. 'included' fallback
        // POS app orders are always treated as included (operator collected
        // cash at the door; commission is carved out of payout).
        $defaultMode = $event?->commission_mode
            ?? $event?->marketplaceOrganizer?->default_commission_mode
            ?? $event?->marketplaceClient?->commission_mode
            ?? 'included';

        $mode = $defaultMode;
        if ($ticketType && method_exists($ticketType, 'getEffectiveCommission')) {
            $defaultRate = (float) (
                $event?->commission_rate
                ?? $event?->marketplaceOrganizer?->commission_rate
                ?? $order->commission_rate
                ?? $event?->tenant?->commission_rate
                ?? $event?->marketplaceClient?->commission_rate
                ?? 5
            );
            $effective = $ticketType->getEffectiveCommission($defaultRate, $defaultMode);
            $mode = $effective['mode'] ?? $defaultMode;
        }

        $isIncluded = !in_array($mode, ['on_top', 'added_on_top'], true);
        if ($source === 'pos_app') {
            $isIncluded = true;
        }

        if (!$isIncluded) {
            // Commission was added on top of the ticket price — the price
            // stored on the row is already the net to the organizer.
            return $price;
        }

        // Try per-type commission_details first (recorded at sale time, most
        // accurate — accounts for discounts, fixed fees, etc.).
        $typeName = $ticketType?->name ?? '';
        $commissionPerUnit = null;
        foreach (($meta['commission_details'] ?? []) as $cd) {
            if (($cd['ticket_type'] ?? '') === $typeName) {
                $qty = max(1, (int) ($cd['quantity'] ?? 1));
                $commissionPerUnit = (float) ($cd['commission_amount'] ?? 0) / $qty;
                break;
            }
        }

        // Fallback: ticket type's own commission settings (per-type override
        // or organizer/marketplace chain).
        if ($commissionPerUnit === null && $ticketType && method_exists($ticketType, 'calculateCommission')) {
            $defaultRate = (float) (
                $event?->commission_rate
                ?? $event?->marketplaceOrganizer?->commission_rate
                ?? $order->commission_rate
                ?? 5
            );
            $commissionPerUnit = (float) $ticketType->calculateCommission($price, $defaultRate, $defaultMode);
        }

        // Last-ditch fallback: order-level commission_rate (the POS path).
        if ($commissionPerUnit === null) {
            $rate = (float) ($order->commission_rate ?? 0);
            $commissionPerUnit = round($price * $rate / 100, 2);
        }

        return round($price - $commissionPerUnit, 2);
    }

    /**
     * Cancel this ticket
     */
    public function cancel(?string $reason = null, ?int $refundRequestId = null): void
    {
        $this->update([
            'is_cancelled' => true,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'refund_request_id' => $refundRequestId,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Get the public verification URL for this ticket's QR code
     */
    public function getVerifyUrl(): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        return "{$baseUrl}/t/{$this->code}";
    }
}
