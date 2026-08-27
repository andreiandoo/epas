<?php

namespace App\Events\Chat;

use App\Models\Chat\ChatConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on conversation status/assignment changes (queued → active → resolved).
 * Inert unless the marketplace opted into the Reverb transport.
 */
class ChatConversationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ChatConversation $conversation)
    {
    }

    public function broadcastWhen(): bool
    {
        return config('chat.transport') === 'reverb';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.' . $this->conversation->id),
            new PrivateChannel('chat.operators.' . $this->conversation->marketplace_client_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->conversation->id,
            'reference' => $this->conversation->reference,
            'status' => $this->conversation->status,
            'assigned_to' => $this->conversation->assigned_to_marketplace_admin_id,
        ];
    }
}
