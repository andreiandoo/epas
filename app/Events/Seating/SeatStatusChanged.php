<?php

namespace App\Events\Seating;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on `event.{eventId}.seats` whenever one or more seats change
 * status (held/released/sold/blocked/etc.). Subscribers (browser canvas
 * widget AND mobile WebView via Laravel Echo) update their local seat
 * map in place — no full re-fetch, no polling.
 *
 * Public channel: seat availability is not sensitive, any buyer can see
 * it on the public ticket page.
 *
 * Payload shape (one of):
 *   { seat_uid: 'sec1-r1-3', status: 'held' }            // single seat
 *   { seats: [{ seat_uid, status }, ...] }               // batch
 *
 * The event implements ShouldBroadcast — dispatch is queued and won't
 * block the request that triggered it. If BROADCAST_CONNECTION isn't
 * configured (default 'null' driver), the event is silently dropped.
 */
class SeatStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $eventId;

    /** @var array<int, array{seat_uid: string, status: string}> */
    public array $seats;

    /**
     * @param  int  $eventId  events.id this batch belongs to
     * @param  array<int, array{seat_uid: string, status: string}>  $seats
     */
    public function __construct(int $eventId, array $seats)
    {
        $this->eventId = $eventId;
        $this->seats = $seats;
    }

    /**
     * Channel name: event.{id}.seats — keyed by the core event id, not the
     * event_seating_id, so the embed page subscribes by the same identifier
     * it already uses for the layout fetch.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('event.' . $this->eventId . '.seats');
    }

    /**
     * Override the event name on the wire (Echo subscribers listen for
     * '.seat.status.changed' with a leading dot to skip Laravel's
     * App\\Events prefix).
     */
    public function broadcastAs(): string
    {
        return 'seat.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'seats' => $this->seats,
            'at' => now()->toIso8601String(),
        ];
    }
}
