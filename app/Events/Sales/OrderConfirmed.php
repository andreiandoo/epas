<?php

namespace App\Events\Sales;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on `event.{eventId}.sales` whenever an order's payment is
 * confirmed (status transitions to confirmed / paid / completed). Mobile
 * clients subscribed to that channel hear it within a few hundred ms
 * and trigger an immediate refresh of stats + ticket types instead of
 * waiting for the next 30 s polling tick.
 *
 * Public channel: sales counts are not sensitive — any buyer on the
 * event page can see "X tickets remaining" so there's no auth concern.
 *
 * Payload:
 *   { event_id, order_id, source, ticket_count, at }
 */
class OrderConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $eventId;
    public int $orderId;
    public string $source;
    public int $ticketCount;

    public function __construct(int $eventId, int $orderId, string $source = 'unknown', int $ticketCount = 0)
    {
        $this->eventId = $eventId;
        $this->orderId = $orderId;
        $this->source = $source;
        $this->ticketCount = $ticketCount;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('event.' . $this->eventId . '.sales');
    }

    /**
     * Wire-event name. Mobile listens for '.order.confirmed' — the leading
     * dot tells Echo to skip Laravel's App\\Events\\ namespace prefix.
     */
    public function broadcastAs(): string
    {
        return 'order.confirmed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'order_id' => $this->orderId,
            'source' => $this->source,
            'ticket_count' => $this->ticketCount,
            'at' => now()->toIso8601String(),
        ];
    }
}
