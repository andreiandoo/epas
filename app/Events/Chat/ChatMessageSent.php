<?php

namespace App\Events\Chat;

use App\Models\Chat\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a message is appended to a conversation. Broadcasts only when the
 * marketplace has opted into the Reverb transport (chat.transport=reverb);
 * otherwise it is completely inert and the widget/console stay on polling.
 */
class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ChatMessage $message)
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
        $channels = [
            new PrivateChannel('chat.conversation.' . $this->message->chat_conversation_id),
        ];

        // Notify the operator pool of the marketplace so consoles light up.
        $channels[] = new PrivateChannel('chat.operators.' . $this->message->marketplace_client_id);

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Internal notes never leave to the conversation channel; the operator
        // channel is staff-only so it may carry them. We keep the payload lean
        // and let clients refetch details if needed.
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->chat_conversation_id,
            'reference' => $this->message->conversation?->reference,
            'from' => $this->message->isFromStaff() ? 'operator' : ($this->message->isSystem() ? 'system' : 'visitor'),
            'is_internal' => (bool) $this->message->is_internal,
            'author' => $this->message->author_label,
            'body' => $this->message->is_internal ? null : $this->message->body,
            'created_at' => optional($this->message->created_at)->toIso8601String(),
        ];
    }
}
